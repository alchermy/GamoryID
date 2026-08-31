<?php

namespace App\Http\Controllers\Api;

use App\Enums\InventoryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SellInventoryRequest;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Reservation;
use App\Models\Sale;
use App\Services\AuditLogger;
use App\Services\CurrentShop;
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
                'inventoryItem:id,tag,title',
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

        return response()->json($query->paginate($validated['per_page'] ?? 25)->withQueryString());
    }

    public function store(SellInventoryRequest $request, int $inventory, CurrentShop $currentShop, AuditLogger $audit)
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

        return response()->json(['data' => $sale], 201);
    }
}
