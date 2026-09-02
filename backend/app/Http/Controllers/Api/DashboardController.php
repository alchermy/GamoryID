<?php

namespace App\Http\Controllers\Api;

use App\Enums\InventoryStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\InventoryItem;
use App\Models\Sale;
use App\Services\CurrentShop;
use App\Services\PlanEntitlements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CurrentShop $currentShop, PlanEntitlements $entitlements): JsonResponse
    {
        $shop = $currentShop->from($request);
        $showProfit = $request->user()->hasShopPermission($shop, 'profit.view') && $entitlements->can($shop, 'analytics');
        $counts = InventoryItem::forShop($shop)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
        $monthly = Sale::query()->where('shop_id', $shop->id)->where('sold_at', '>=', now()->startOfMonth());
        $trendStart = now()->subDays(6)->startOfDay();
        $dailySales = Sale::query()
            ->where('shop_id', $shop->id)
            ->where('sold_at', '>=', $trendStart)
            ->selectRaw('DATE(sold_at) as date, COUNT(*) as sales, COALESCE(SUM(sold_price), 0) as revenue')
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        return response()->json([
            'summary' => [
                'available' => (int) ($counts[InventoryStatus::Available->value] ?? 0),
                'reserved' => (int) ($counts[InventoryStatus::Reserved->value] ?? 0),
                'sold_this_month' => (clone $monthly)->count(),
                'sold_total' => (int) ($counts[InventoryStatus::Sold->value] ?? 0),
                'revenue_this_month' => (float) (clone $monthly)->sum('sold_price'),
                'profit_this_month' => $showProfit ? (float) (clone $monthly)->sum('profit') : null,
                'inventory_value' => $showProfit
                    ? (float) InventoryItem::forShop($shop)->whereIn('status', ['available', 'reserved'])->sum('cost')
                    : null,
            ],
            'activity' => ActivityLog::query()->where('shop_id', $shop->id)->latest('created_at')->limit(8)->get(),
            'sales_last_7_days' => collect(range(6, 0))->map(function (int $daysAgo) use ($dailySales) {
                $day = now()->subDays($daysAgo);
                $record = $dailySales->get($day->toDateString());

                return [
                    'date' => $day->toDateString(),
                    'sales' => (int) ($record->sales ?? 0),
                    'revenue' => (float) ($record->revenue ?? 0),
                ];
            })->values(),
            'subscription' => $entitlements->summary($shop),
        ]);
    }
}
