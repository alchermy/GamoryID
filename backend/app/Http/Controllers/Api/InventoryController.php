<?php

namespace App\Http\Controllers\Api;

use App\Enums\InventoryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryRequest;
use App\Http\Resources\InventoryItemResource;
use App\Models\InventoryCredential;
use App\Models\InventoryItem;
use App\Services\AuditLogger;
use App\Services\CredentialCipher;
use App\Services\CurrentShop;
use App\Services\PlanGate;
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
            'region' => ['nullable', 'string', 'max:12'],
            'sort' => ['nullable', 'in:updated_at,tag,list_price,rank'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
        ]);
        $query = InventoryItem::forShop($shop)->withExists('credentials');
        if ($q = trim((string) ($validated['q'] ?? ''))) {
            $normalizedTag = ltrim(mb_strtoupper($q), '#');
            $query->where(function ($builder) use ($q, $normalizedTag) {
                $builder->where('tag', $normalizedTag)
                    ->orWhere('title', 'like', "%{$q}%")
                    ->orWhere('rank', 'like', "%{$q}%")
                    ->orWhere('region', 'like', "%{$q}%");
            });
        }
        if ($status = $validated['status'] ?? null) {
            $query->where('status', $status);
        }
        if ($region = $validated['region'] ?? null) {
            $query->where('region', $region);
        }
        $query->orderBy($validated['sort'] ?? 'updated_at', $validated['direction'] ?? 'desc');

        return InventoryItemResource::collection($query->paginate($validated['per_page'] ?? 25)->withQueryString());
    }

    public function store(StoreInventoryRequest $request, CurrentShop $currentShop, TagGenerator $tags, CredentialCipher $cipher, AuditLogger $audit, PlanGate $planGate)
    {
        $shop = $currentShop->from($request);
        $planGate->ensureInventoryCapacity($shop);
        $item = DB::transaction(function () use ($request, $shop, $tags, $cipher) {
            $data = Arr::except($request->validated(), 'credentials');
            $item = InventoryItem::create([...$data, 'shop_id' => $shop->id, 'created_by' => $request->user()->id, 'tag' => $tags->generate(), 'status' => InventoryStatus::Available]);
            if ($credentials = $request->validated('credentials')) {
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

        return (new InventoryItemResource($item->load('shop')))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $inventory, CurrentShop $currentShop): InventoryItemResource
    {
        $shop = $currentShop->from($request);
        $item = InventoryItem::forShop($shop)->withExists('credentials')->findOrFail($inventory);

        return new InventoryItemResource($item->load('shop'));
    }

    public function update(StoreInventoryRequest $request, int $inventory, CurrentShop $currentShop, CredentialCipher $cipher, AuditLogger $audit): InventoryItemResource
    {
        $shop = $currentShop->from($request);
        $item = InventoryItem::forShop($shop)->findOrFail($inventory);
        DB::transaction(function () use ($request, $item, $cipher) {
            $item->update([...Arr::except($request->validated(), 'credentials'), 'lock_version' => $item->lock_version + 1]);
            if ($credentials = $request->validated('credentials')) {
                $encrypted = $cipher->encrypt($credentials);
                InventoryCredential::updateOrCreate(
                    ['inventory_item_id' => $item->id],
                    ['encrypted_payload' => $encrypted['payload'], 'key_version' => $encrypted['key_version']],
                );
            }
        });
        $audit->record($request, $shop, 'inventory.updated', $item, ['tag' => '#'.$item->tag]);

        return new InventoryItemResource($item->fresh()->load('shop'));
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
