<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\Shop;

class PlanGate
{
    public function ensureInventoryCapacity(Shop $shop, int $additional = 1): void
    {
        $limit = $shop->subscriptions()->latest()->with('plan')->first()?->plan?->active_inventory_limit ?? 1000;
        $active = InventoryItem::forShop($shop)->whereIn('status', ['available', 'reserved'])->count();
        abort_if($active + $additional > $limit, 422, "สต็อกพร้อมขายเต็มตามแพ็กเกจ ({$limit} รายการ)");
    }
}
