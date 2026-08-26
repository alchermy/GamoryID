<?php

namespace App\Http\Controllers\Api;

use App\Enums\InventoryStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\InventoryItem;
use App\Models\Sale;
use App\Services\CurrentShop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CurrentShop $currentShop): JsonResponse
    {
        $shop = $currentShop->from($request);
        $counts = InventoryItem::forShop($shop)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
        $monthly = Sale::query()->where('shop_id', $shop->id)->where('sold_at', '>=', now()->startOfMonth());

        return response()->json([
            'summary' => [
                'available' => (int) ($counts[InventoryStatus::Available->value] ?? 0),
                'reserved' => (int) ($counts[InventoryStatus::Reserved->value] ?? 0),
                'sold_this_month' => (clone $monthly)->count(),
                'revenue_this_month' => (float) (clone $monthly)->sum('sold_price'),
                'profit_this_month' => $request->user()->hasShopPermission($shop, 'profit.view') ? (float) (clone $monthly)->sum('profit') : null,
                'inventory_value' => $request->user()->hasShopPermission($shop, 'profit.view')
                    ? (float) InventoryItem::forShop($shop)->whereIn('status', ['available', 'reserved'])->sum('cost')
                    : null,
            ],
            'activity' => ActivityLog::query()->where('shop_id', $shop->id)->latest('created_at')->limit(8)->get(),
            'subscription' => [
                'status' => $shop->status,
                'trial_ends_at' => $shop->trial_ends_at,
                'grace_ends_at' => $shop->grace_ends_at,
                'writable' => $shop->isWritable(),
            ],
        ]);
    }
}
