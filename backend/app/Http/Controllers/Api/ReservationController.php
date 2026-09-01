<?php

namespace App\Http\Controllers\Api;

use App\Enums\InventoryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReserveInventoryRequest;
use App\Jobs\SendDiscordShopNotification;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Reservation;
use App\Services\AuditLogger;
use App\Services\CurrentShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    public function store(ReserveInventoryRequest $request, int $inventory, CurrentShop $currentShop, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $reservation = DB::transaction(function () use ($request, $shop, $inventory) {
            $item = InventoryItem::forShop($shop)->lockForUpdate()->findOrFail($inventory);
            if ($item->status !== InventoryStatus::Available) {
                abort(409, 'รายการนี้ไม่พร้อมให้จอง');
            }
            $customerId = $request->validated('customer_id');
            if (! $customerId && $request->validated('customer_name')) {
                $customerId = Customer::create(['shop_id' => $shop->id, 'name' => $request->validated('customer_name')])->id;
            }
            if ($customerId && ! Customer::where('shop_id', $shop->id)->whereKey($customerId)->exists()) {
                abort(422, 'ไม่พบลูกค้าในร้านนี้');
            }
            $reservation = Reservation::create([
                'shop_id' => $shop->id,
                'inventory_item_id' => $item->id,
                'customer_id' => $customerId,
                'created_by' => $request->user()->id,
                'notes' => $request->validated('notes'),
                'expires_at' => $request->validated('expires_at') ?: now()->addDay(),
            ]);
            $item->update(['status' => InventoryStatus::Reserved, 'lock_version' => $item->lock_version + 1]);

            return $reservation;
        });
        $audit->record($request, $shop, 'inventory.reserved', $reservation, ['inventory_id' => $inventory]);
        $item = InventoryItem::forShop($shop)->find($inventory);
        SendDiscordShopNotification::dispatch(
            $shop->id,
            'reservations',
            'มีการจองไอดี',
            $item ? "**#{$item->tag}** · {$item->riot_id}\nหมดเวลาจอง ".$reservation->expires_at->timezone('Asia/Bangkok')->format('d/m/Y H:i').' น.' : "รายการ #{$inventory}",
        );

        return response()->json(['data' => $reservation], 201);
    }

    public function release(Request $request, int $inventory, CurrentShop $currentShop, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $item = DB::transaction(function () use ($shop, $inventory) {
            $item = InventoryItem::forShop($shop)->lockForUpdate()->findOrFail($inventory);
            if ($item->status !== InventoryStatus::Reserved) {
                abort(409, 'รายการนี้ไม่ได้ถูกจอง');
            }
            Reservation::where('shop_id', $shop->id)->where('inventory_item_id', $item->id)->whereNull('released_at')->update(['released_at' => now()]);
            $item->update(['status' => InventoryStatus::Available, 'lock_version' => $item->lock_version + 1]);

            return $item;
        });
        $audit->record($request, $shop, 'inventory.reservation_released', $item, ['tag' => '#'.$item->tag]);
        SendDiscordShopNotification::dispatch(
            $shop->id,
            'reservations',
            'ยกเลิกการจองแล้ว',
            "**#{$item->tag}** · {$item->riot_id}\nรายการกลับเป็นสถานะพร้อมขาย",
        );

        return response()->json(['message' => 'ยกเลิกการจองแล้ว']);
    }
}
