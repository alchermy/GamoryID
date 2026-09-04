<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\CurrentShop;
use App\Services\PlanEntitlements;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

    /**
     * Deep sales report for the chosen date range: summary KPIs plus breakdowns
     * by rank, price band, staff and top customers.
     *
     * Route-gated by `plan.feature:analytics`. Additionally requires the
     * `profit.view` or `team.manage` permission (owner passes). Profit-bearing
     * figures are null unless the caller has `profit.view` — same rule as the
     * dashboard.
     */
    public function analytics(Request $request, CurrentShop $currentShop, PlanEntitlements $entitlements): JsonResponse
    {
        $shop = $currentShop->from($request);
        $user = $request->user();
        abort_unless(
            $user->hasShopPermission($shop, 'profit.view') || $user->hasShopPermission($shop, 'team.manage'),
            403,
        );

        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from'])->startOfDay() : CarbonImmutable::now()->startOfMonth();
        $to = isset($data['to']) ? CarbonImmutable::parse($data['to'])->endOfDay() : CarbonImmutable::now()->endOfDay();
        if ($to->lt($from)) {
            throw ValidationException::withMessages(['to' => 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น']);
        }
        if ($from->diffInDays($to) > 370) {
            throw ValidationException::withMessages(['to' => 'ช่วงเวลาของรายงานต้องไม่เกิน 1 ปี']);
        }

        $canProfit = $user->hasShopPermission($shop, 'profit.view') && $entitlements->can($shop, 'analytics');

        $sales = Sale::query()
            ->where('shop_id', $shop->id)
            ->whereBetween('sold_at', [$from, $to])
            ->with([
                'inventoryItem' => fn ($q) => $q->withTrashed()->select('id', 'rank', 'created_at'),
                'customer:id,name',
                'creator:id,name',
            ])
            ->get(['id', 'sold_at', 'sold_price', 'profit', 'created_by', 'customer_id', 'inventory_item_id']);

        $revenue = 0.0;
        $profit = 0.0;
        $daysToSell = [];
        $rank = [];      // label => [sales, revenue, profit]
        $bands = [
            ['label' => 'ต่ำกว่า 1,000', 'min' => 0, 'max' => 1000],
            ['label' => '1,000–2,999', 'min' => 1000, 'max' => 3000],
            ['label' => '3,000–4,999', 'min' => 3000, 'max' => 5000],
            ['label' => '5,000–9,999', 'min' => 5000, 'max' => 10000],
            ['label' => '10,000 ขึ้นไป', 'min' => 10000, 'max' => null],
        ];
        $bandTotals = array_fill(0, count($bands), ['sales' => 0, 'revenue' => 0.0]);
        $staff = [];     // created_by => [name, sales, revenue, profit]
        $customers = []; // customer_id => [name, sales, revenue, last]

        foreach ($sales as $sale) {
            $price = (float) $sale->sold_price;
            $revenue += $price;
            $profit += (float) $sale->profit;

            if ($sale->inventoryItem?->created_at) {
                $daysToSell[] = CarbonImmutable::parse($sale->sold_at)
                    ->diffInDays(CarbonImmutable::parse($sale->inventoryItem->created_at), absolute: true);
            }

            $rankLabel = $sale->inventoryItem?->rank ?: 'ไม่ระบุแรงก์';
            $rank[$rankLabel] ??= ['sales' => 0, 'revenue' => 0.0, 'profit' => 0.0];
            $rank[$rankLabel]['sales']++;
            $rank[$rankLabel]['revenue'] += $price;
            $rank[$rankLabel]['profit'] += (float) $sale->profit;

            foreach ($bands as $i => $band) {
                if ($price >= $band['min'] && ($band['max'] === null || $price < $band['max'])) {
                    $bandTotals[$i]['sales']++;
                    $bandTotals[$i]['revenue'] += $price;
                    break;
                }
            }

            $staffKey = $sale->created_by ?? 0;
            $staff[$staffKey] ??= ['id' => $sale->created_by, 'name' => $sale->creator?->name ?: 'ไม่ทราบ', 'sales' => 0, 'revenue' => 0.0, 'profit' => 0.0];
            $staff[$staffKey]['sales']++;
            $staff[$staffKey]['revenue'] += $price;
            $staff[$staffKey]['profit'] += (float) $sale->profit;

            if ($sale->customer_id) {
                $customers[$sale->customer_id] ??= ['id' => $sale->customer_id, 'name' => $sale->customer?->name ?: 'ไม่ระบุ', 'sales' => 0, 'revenue' => 0.0, 'last_bought_at' => null];
                $customers[$sale->customer_id]['sales']++;
                $customers[$sale->customer_id]['revenue'] += $price;
                $soldAt = (string) $sale->sold_at;
                if ($customers[$sale->customer_id]['last_bought_at'] === null || $soldAt > $customers[$sale->customer_id]['last_bought_at']) {
                    $customers[$sale->customer_id]['last_bought_at'] = $soldAt;
                }
            }
        }

        $count = $sales->count();
        $byRevenue = fn (array $a, array $b) => $b['revenue'] <=> $a['revenue'];

        $byRank = [];
        foreach ($rank as $label => $r) {
            $byRank[] = [
                'label' => $label,
                'sales' => $r['sales'],
                'revenue' => round($r['revenue'], 2),
                'profit' => $canProfit ? round($r['profit'], 2) : null,
            ];
        }
        usort($byRank, $byRevenue);

        $byStaff = array_values($staff);
        foreach ($byStaff as &$s) {
            $s['revenue'] = round($s['revenue'], 2);
            $s['profit'] = $canProfit ? round($s['profit'], 2) : null;
        }
        unset($s);
        usort($byStaff, $byRevenue);

        $topCustomers = array_values($customers);
        foreach ($topCustomers as &$c) {
            $c['revenue'] = round($c['revenue'], 2);
        }
        unset($c);
        usort($topCustomers, $byRevenue);

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'revenue' => round($revenue, 2),
                'sales' => $count,
                'profit' => $canProfit ? round($profit, 2) : null,
                'margin_pct' => ($canProfit && $revenue > 0) ? round($profit / $revenue * 100, 1) : null,
                'avg_price' => $count > 0 ? round($revenue / $count, 2) : 0,
                'avg_days_to_sell' => $daysToSell !== [] ? round(array_sum($daysToSell) / count($daysToSell), 1) : null,
            ],
            'by_rank' => array_slice($byRank, 0, 8),
            'by_price_band' => array_map(fn ($band, $i) => [
                'label' => $band['label'],
                'min' => $band['min'],
                'sales' => $bandTotals[$i]['sales'],
                'revenue' => round($bandTotals[$i]['revenue'], 2),
            ], $bands, array_keys($bands)),
            'by_staff' => $byStaff,
            'top_customers' => array_slice($topCustomers, 0, 8),
        ]);
    }
}
