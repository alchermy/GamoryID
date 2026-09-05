<?php

namespace App\Http\Controllers\Api;

use App\Enums\InventoryStatus;
use App\Enums\ShopPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryRequest;
use App\Http\Requests\UpdateInventoryNoteRequest;
use App\Http\Resources\InventoryItemResource;
use App\Jobs\SendDiscordShopNotification;
use App\Models\InventoryCredential;
use App\Models\InventoryItem;
use App\Services\AuditLogger;
use App\Services\CredentialCipher;
use App\Services\CurrentShop;
use App\Services\Discord\DiscordNotificationMessageBuilder;
use App\Services\PlanEntitlements;
use App\Services\TagGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index(Request $request, CurrentShop $currentShop)
    {
        $shop = $currentShop->from($request);
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:available,reserved,sold,archived'],
            'sort' => ['nullable', 'in:updated_at,tag,list_price,rank,view_count'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
        ]);
        $query = InventoryItem::forShop($shop)->withExists('credentials');
        if ($q = trim((string) ($validated['q'] ?? ''))) {
            $normalizedTag = ltrim(mb_strtoupper($q), '#');
            $query->where(function ($builder) use ($q, $normalizedTag) {
                $builder->where('tag', $normalizedTag)
                    ->orWhere('title', 'like', "%{$q}%")
                    ->orWhere('riot_id', 'like', "%{$q}%")
                    ->orWhere('username', 'like', "%{$q}%")
                    ->orWhere('rank', 'like', "%{$q}%");
            });
        }
        if ($status = $validated['status'] ?? null) {
            $query->where('status', $status);
        } else {
            $query->where('status', '!=', InventoryStatus::Archived);
        }
        $query->orderBy($validated['sort'] ?? 'updated_at', $validated['direction'] ?? 'desc');

        return InventoryItemResource::collection($query->paginate($validated['per_page'] ?? 25)->withQueryString());
    }

    public function store(StoreInventoryRequest $request, CurrentShop $currentShop, TagGenerator $tags, CredentialCipher $cipher, AuditLogger $audit, PlanEntitlements $planGate, DiscordNotificationMessageBuilder $discordMessages)
    {
        $shop = $currentShop->from($request);
        $planGate->ensureInventoryCapacity($shop);
        $item = DB::transaction(function () use ($request, $shop, $tags, $cipher) {
            $credentials = $request->validated('credentials');
            $data = Arr::except($request->validated(), 'credentials');
            $data['region'] = 'TH';
            $data['title'] = $data['title'] ?? $data['riot_id'];
            $data['username'] = $data['username'] ?? $credentials['username'] ?? null;
            // skin_count is nullable in the request but the column defaults to 0 —
            // Eloquent doesn't know about that DB-level default until the model is
            // reloaded, so without this the freshly-created item's API response
            // (built from the in-memory instance below, not a fresh fetch) carries
            // skin_count: null instead of 0, which crashes the merchant UI.
            $data['skin_count'] = $data['skin_count'] ?? 0;
            $item = InventoryItem::create([...$data, 'shop_id' => $shop->id, 'created_by' => $request->user()->id, 'tag' => $tags->generate(), 'status' => InventoryStatus::Available]);
            if ($credentials) {
                $encrypted = $cipher->encrypt($credentials);
                InventoryCredential::create([
                    'inventory_item_id' => $item->id,
                    'encrypted_payload' => $encrypted['payload'],
                    'key_version' => $encrypted['key_version'],
                ]);
            }

            return $item;
        });
        $audit->record($request, $shop, 'inventory.created', $item, ['tag' => '#'.$item->tag]);
        SendDiscordShopNotification::dispatch(
            $shop->id,
            'inventory',
            'เพิ่มไอดีใหม่เข้าคลัง',
            $discordMessages->inventoryCreated($item, $request->user()),
        );

        return (new InventoryItemResource($item->load(['shop', 'media'])))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $inventory, CurrentShop $currentShop): InventoryItemResource
    {
        $shop = $currentShop->from($request);
        $item = InventoryItem::forShop($shop)->withExists('credentials')->findOrFail($inventory);

        return new InventoryItemResource($item->load(['shop', 'media']));
    }

    public function update(StoreInventoryRequest $request, int $inventory, CurrentShop $currentShop, CredentialCipher $cipher, AuditLogger $audit): InventoryItemResource
    {
        $shop = $currentShop->from($request);
        $item = InventoryItem::forShop($shop)->findOrFail($inventory);
        DB::transaction(function () use ($request, $item, $cipher) {
            $credentials = $request->validated('credentials');
            $data = Arr::except($request->validated(), 'credentials');
            $data['region'] = 'TH';
            $data['title'] = $data['title'] ?? $data['riot_id'] ?? $item->title;
            $data['username'] = $data['username'] ?? $credentials['username'] ?? $item->username;
            $item->update([...$data, 'lock_version' => $item->lock_version + 1]);
            if ($credentials) {
                $encrypted = $cipher->encrypt($credentials);
                InventoryCredential::updateOrCreate(
                    ['inventory_item_id' => $item->id],
                    ['encrypted_payload' => $encrypted['payload'], 'key_version' => $encrypted['key_version']],
                );
            }
        });
        $audit->record($request, $shop, 'inventory.updated', $item, ['tag' => '#'.$item->tag]);

        return new InventoryItemResource($item->fresh()->load(['shop', 'media']));
    }

    public function updateNote(UpdateInventoryNoteRequest $request, int $inventory, CurrentShop $currentShop, AuditLogger $audit): InventoryItemResource
    {
        $shop = $currentShop->from($request);
        $canWriteNote = $request->user()->hasShopPermission($shop, ShopPermission::InventoryManage)
            || $request->user()->hasShopPermission($shop, ShopPermission::InventorySell);
        abort_unless($canWriteNote, 403, 'คุณไม่มีสิทธิ์บันทึกโน้ตของไอดี');

        $note = trim((string) ($request->validated('notes') ?? ''));
        $item = DB::transaction(function () use ($shop, $inventory, $note) {
            $item = InventoryItem::forShop($shop)->lockForUpdate()->findOrFail($inventory);
            $item->update([
                'notes' => $note !== '' ? $note : null,
                'lock_version' => $item->lock_version + 1,
            ]);

            return $item;
        }, 3);

        $audit->record($request, $shop, 'inventory.note_updated', $item, [
            'tag' => '#'.$item->tag,
            'has_note' => $note !== '',
        ]);

        return new InventoryItemResource($item->fresh()->load(['shop', 'media']));
    }

    public function destroy(Request $request, int $inventory, CurrentShop $currentShop, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $item = InventoryItem::forShop($shop)->findOrFail($inventory);
        $item->update(['status' => InventoryStatus::Archived, 'archived_at' => now(), 'lock_version' => $item->lock_version + 1]);
        $audit->record($request, $shop, 'inventory.archived', $item, ['tag' => '#'.$item->tag]);

        return response()->json(['message' => 'เก็บรายการถาวรแล้ว']);
    }
}
