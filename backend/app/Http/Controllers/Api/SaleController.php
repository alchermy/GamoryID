<?php

namespace App\Http\Controllers\Api;

use App\Enums\InventoryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SellInventoryRequest;
use App\Jobs\SendDiscordShopNotification;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Reservation;
use App\Models\Sale;
use App\Services\AuditLogger;
use App\Services\CurrentShop;
use App\Services\Discord\DiscordNotificationMessageBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function index(Request $request, CurrentShop $currentShop)
    {
        $shop = $currentShop->from($request);
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
        ]);

        $query = Sale::query()
            ->where('shop_id', $shop->id)
            ->with([
                'inventoryItem:id,tag,title,riot_id,rank,level,list_price',
                'customer:id,name,phone,line_id,facebook_url',
                'creator:id,name',
            ])
            ->latest('sold_at');

        if ($q = trim((string) ($validated['q'] ?? ''))) {
            $query->where(function ($builder) use ($q) {
                $builder->whereHas('inventoryItem', function ($inventory) use ($q) {
                    $inventory->where('tag', ltrim(mb_strtoupper($q), '#'))
                        ->orWhere('title', 'like', "%{$q}%");
                })->orWhereHas('customer', function ($customer) use ($q) {
                    $customer->where('name', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('line_id', 'like', "%{$q}%");
                });
            });
        }

        $canViewProfit = $request->user()->hasShopPermission($shop, 'profit.view');
        $sales = $query->paginate($validated['per_page'] ?? 25)->withQueryString();
        $sales->through(fn (Sale $sale) => $this->serialize($sale, $canViewProfit));

        return response()->json($sales);
    }

    public function show(Request $request, int $sale, CurrentShop $currentShop)
    {
        $shop = $currentShop->from($request);
        $record = Sale::query()
            ->where('shop_id', $shop->id)
            ->with([
                'inventoryItem:id,tag,title,riot_id,rank,level,list_price',
                'customer:id,name,phone,line_id,facebook_url',
                'creator:id,name',
            ])
            ->findOrFail($sale);

        return response()->json([
            'data' => $this->serialize(
                $record,
                $request->user()->hasShopPermission($shop, 'profit.view'),
            ),
        ]);
    }

    public function store(SellInventoryRequest $request, int $inventory, CurrentShop $currentShop, AuditLogger $audit, DiscordNotificationMessageBuilder $discordMessages)
    {
        $shop = $currentShop->from($request);
        $sale = DB::transaction(function () use ($request, $shop, $inventory) {
            $item = InventoryItem::forShop($shop)->lockForUpdate()->findOrFail($inventory);
            if ($item->status === InventoryStatus::Sold || Sale::where('inventory_item_id', $item->id)->exists()) {
                abort(409, 'รายการนี้ถูกขายไปแล้ว');
            }
            if ($item->status === InventoryStatus::Archived) {
                abort(409, 'รายการที่เก็บถาวรไม่สามารถขายได้');
            }

            $customerId = $request->validated('customer_id');
            if ($customerId && ! Customer::where('shop_id', $shop->id)->whereKey($customerId)->exists()) {
                abort(422, 'ไม่พบลูกค้าในร้านนี้');
            }
            if (! $customerId) {
                $customerId = Customer::create(['shop_id' => $shop->id, ...$request->validated('customer')])->id;
            }
            $soldPrice = (float) $request->validated('sold_price');
            $cost = (float) $item->cost;
            $sale = Sale::create([
                'shop_id' => $shop->id,
                'inventory_item_id' => $item->id,
                'customer_id' => $customerId,
                'created_by' => $request->user()->id,
                'sold_price' => $soldPrice,
                'cost_snapshot' => $cost,
                'profit' => $soldPrice - $cost,
                'has_warranty' => (bool) $request->validated('has_warranty'),
                'warranty_ends_at' => $request->validated('has_warranty') ? $request->validated('warranty_ends_at') : null,
                'notes' => $request->validated('notes'),
                'sold_at' => now(),
            ]);
            Reservation::where('inventory_item_id', $item->id)->whereNull('released_at')->update(['released_at' => now()]);
            $item->update(['status' => InventoryStatus::Sold, 'lock_version' => $item->lock_version + 1]);

            return $sale;
        }, 3);
        $audit->record($request, $shop, 'inventory.sold', $sale, [
            'inventory_id' => $inventory,
            'sold_price' => $sale->sold_price,
            'has_warranty' => $sale->has_warranty,
            'warranty_ends_at' => $sale->warranty_ends_at?->toDateString(),
        ]);
        $sale->load([
            'inventoryItem:id,tag,title,riot_id,rank,level,list_price',
            'customer:id,name,phone,line_id,facebook_url',
            'creator:id,name',
        ]);
        SendDiscordShopNotification::dispatch(
            $shop->id,
            'sales',
            'ปิดการขายสำเร็จ',
            $discordMessages->saleCompleted($sale),
        );

        return response()->json(['data' => $sale], 201);
    }

    private function serialize(Sale $sale, bool $canViewProfit): array
    {
        return [
            'id' => $sale->id,
            'sold_price' => $sale->sold_price,
            'cost_snapshot' => $canViewProfit ? $sale->cost_snapshot : null,
            'profit' => $canViewProfit ? $sale->profit : null,
            'has_warranty' => $sale->has_warranty,
            'warranty_ends_at' => $sale->warranty_ends_at?->toDateString(),
            'notes' => $sale->notes,
            'sold_at' => $sale->sold_at?->toIso8601String(),
            'inventory_item' => $sale->inventoryItem ? [
                'id' => $sale->inventoryItem->id,
                'tag' => $sale->inventoryItem->tag,
                'title' => $sale->inventoryItem->title,
                'riot_id' => $sale->inventoryItem->riot_id,
                'rank' => $sale->inventoryItem->rank,
                'level' => $sale->inventoryItem->level,
                'list_price' => $sale->inventoryItem->list_price,
            ] : null,
            'customer' => $sale->customer ? [
                'id' => $sale->customer->id,
                'name' => $sale->customer->name,
                'phone' => $sale->customer->phone,
                'line_id' => $sale->customer->line_id,
                'facebook_url' => $sale->customer->facebook_url,
            ] : null,
            'creator' => $sale->creator ? [
                'id' => $sale->creator->id,
                'name' => $sale->creator->name,
            ] : null,
        ];
    }
}
