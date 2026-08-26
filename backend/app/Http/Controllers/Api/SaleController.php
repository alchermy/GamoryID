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
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
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
                'notes' => $request->validated('notes'),
                'sold_at' => now(),
            ]);
            Reservation::where('inventory_item_id', $item->id)->whereNull('released_at')->update(['released_at' => now()]);
            $item->update(['status' => InventoryStatus::Sold, 'lock_version' => $item->lock_version + 1]);

            return $sale;
        }, 3);
        $audit->record($request, $shop, 'inventory.sold', $sale, ['inventory_id' => $inventory, 'sold_price' => $sale->sold_price]);

        return response()->json(['data' => $sale], 201);
    }
}
