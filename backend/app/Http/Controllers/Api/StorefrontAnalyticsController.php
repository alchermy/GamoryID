<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CurrentShop;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StorefrontAnalyticsController extends Controller
{
    /**
     * Storefront view counts over time, bucketed by day / month / year.
     * Gated by the `analytics` plan feature via route middleware.
     */
    public function views(Request $request, CurrentShop $currentShop): JsonResponse
    {
        $shop = $currentShop->from($request);
        $granularity = $request->query('granularity', 'day');
        if (! in_array($granularity, ['day', 'month', 'year'], true)) {
            $granularity = 'day';
        }

        $today = CarbonImmutable::now()->startOfDay();
        [$start, $steps, $step, $fmt] = match ($granularity) {
            'year' => [$today->subYears(4)->startOfYear(), 5, 'addYear', 'Y'],
            'month' => [$today->subMonths(11)->startOfMonth(), 12, 'addMonth', 'Y-m'],
            default => [$today->subDays(29), 30, 'addDay', 'Y-m-d'],
        };

        // Group the raw daily rows in PHP (DATE_FORMAT is not portable to SQLite).
        $rows = DB::table('shop_view_daily')
            ->where('shop_id', $shop->id)
            ->where('date', '>=', $start->toDateString())
            ->get(['date', 'views']);

        $totals = [];
        foreach ($rows as $row) {
            $key = CarbonImmutable::parse($row->date)->format($fmt);
            $totals[$key] = ($totals[$key] ?? 0) + (int) $row->views;
        }

        $data = [];
        $cursor = $start;
        for ($i = 0; $i < $steps; $i++) {
            $key = $cursor->format($fmt);
            $data[] = ['period' => $key, 'views' => $totals[$key] ?? 0];
            $cursor = $cursor->{$step}();
        }

        return response()->json([
            'granularity' => $granularity,
            'total' => (int) $shop->storefront_view_count,
            'data' => $data,
        ]);
    }
}
