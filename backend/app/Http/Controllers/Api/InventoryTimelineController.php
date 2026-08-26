<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\InventoryItem;
use App\Services\CurrentShop;
use Illuminate\Http\Request;

class InventoryTimelineController extends Controller
{
    public function __invoke(Request $request, int $inventory, CurrentShop $currentShop)
    {
        $shop = $currentShop->from($request);
        $item = InventoryItem::forShop($shop)->findOrFail($inventory);

        return response()->json(['data' => ActivityLog::where('shop_id', $shop->id)
            ->where('subject_type', $item->getMorphClass())->where('subject_id', $item->id)
            ->latest('created_at')->paginate(25)]);
    }
}
