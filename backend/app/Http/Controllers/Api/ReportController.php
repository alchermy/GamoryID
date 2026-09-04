<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\CurrentShop;
use App\Services\PlanEntitlements;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Revenue / sales count (and profit, when allowed) bucketed by
     * day / month / year, with the matching previous window for ▲▼ deltas.
     *
     * Not plan-gated: revenue and count are the shop's own data. Profit is
     * gated exactly like the dashboard — `profit.view` permission AND the
     * `analytics` feature.
     */
    public function sales(Request $request, CurrentShop $currentShop, PlanEntitlements $entitlements): JsonResponse
    {
        $shop = $currentShop->from($request);
        $granularity = $request->query('granularity', 'day');
        if (! in_array($granularity, ['day', 'month', 'year'], true)) {
            $granularity = 'day';
        }

        $showProfit = $request->user()->hasShopPermission($shop, 'profit.view')
            && $entitlements->can($shop, 'analytics');

        $today = CarbonImmutable::now()->startOfDay();
        [$start, $steps, $step, $fmt, $back] = match ($granularity) {
            'year' => [$today->subYears(4)->startOfYear(), 5, 'addYear', 'Y', 'subYears'],
            'month' => [$today->subMonths(11)->startOfMonth(), 12, 'addMonth', 'Y-m', 'subMonths'],
            default => [$today->subDays(29), 30, 'addDay', 'Y-m-d', 'subDays'],
        };
        // Same-length window immediately before `$start`.
        $prevStart = $start->{$back}($steps);

        $rows = Sale::query()
            ->where('shop_id', $shop->id)
            ->where('sold_at', '>=', $prevStart)
            ->get(['sold_at', 'sold_price', 'profit']);

        $buckets = [];      // period key => [revenue, sales, profit]
        $previous = ['revenue' => 0.0, 'sales' => 0, 'profit' => 0.0];
        foreach ($rows as $row) {
            $soldAt = CarbonImmutable::parse($row->sold_at);
            if ($soldAt->lt($start)) {
                $previous['revenue'] += (float) $row->sold_price;
                $previous['sales']++;
                $previous['profit'] += (float) $row->profit;

                continue;
            }
            $key = $soldAt->format($fmt);
            $buckets[$key] ??= ['revenue' => 0.0, 'sales' => 0, 'profit' => 0.0];
            $buckets[$key]['revenue'] += (float) $row->sold_price;
            $buckets[$key]['sales']++;
            $buckets[$key]['profit'] += (float) $row->profit;
        }

        $data = [];
        $totals = ['revenue' => 0.0, 'sales' => 0, 'profit' => 0.0];
        $cursor = $start;
        for ($i = 0; $i < $steps; $i++) {
            $key = $cursor->format($fmt);
            $bucket = $buckets[$key] ?? ['revenue' => 0.0, 'sales' => 0, 'profit' => 0.0];
            $totals['revenue'] += $bucket['revenue'];
            $totals['sales'] += $bucket['sales'];
            $totals['profit'] += $bucket['profit'];
            $data[] = [
                'period' => $key,
                'revenue' => round($bucket['revenue'], 2),
                'sales' => $bucket['sales'],
                'profit' => $showProfit ? round($bucket['profit'], 2) : null,
            ];
            $cursor = $cursor->{$step}();
        }

        return response()->json([
            'granularity' => $granularity,
            'totals' => [
                'revenue' => round($totals['revenue'], 2),
                'sales' => $totals['sales'],
                'profit' => $showProfit ? round($totals['profit'], 2) : null,
            ],
            'previous' => [
                'revenue' => round($previous['revenue'], 2),
                'sales' => $previous['sales'],
                'profit' => $showProfit ? round($previous['profit'], 2) : null,
            ],
            'data' => $data,
        ]);
    }
}
