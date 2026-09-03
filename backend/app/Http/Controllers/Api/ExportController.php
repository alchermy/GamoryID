<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Sale;
use App\Services\AuditLogger;
use App\Services\CurrentShop;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /** UTF-8 BOM so Excel opens Thai text without mojibake. */
    private const BOM = "\xEF\xBB\xBF";

    public function inventory(Request $request, CurrentShop $currentShop, AuditLogger $audit): StreamedResponse
    {
        $shop = $currentShop->from($request);
        $canViewProfit = $request->user()->hasShopPermission($shop, 'profit.view');
        $audit->record($request, $shop, 'inventory.exported', null, ['format' => 'csv']);

        return response()->streamDownload(function () use ($shop, $canViewProfit) {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, self::BOM);
            fputcsv($stream, ['tag', 'riot_id', 'username', 'rank', 'status', 'list_price', ...($canViewProfit ? ['cost'] : [])]);
            InventoryItem::withTrashed()->where('shop_id', $shop->id)->orderBy('id')->chunk(500, function ($items) use ($stream, $canViewProfit) {
                foreach ($items as $item) {
                    fputcsv($stream, ['#'.$item->tag, $item->riot_id, $item->username, $item->rank, $item->status->value, $item->list_price, ...($canViewProfit ? [$item->cost] : [])]);
                }
            });
            fclose($stream);
        }, "gamoryid-{$shop->slug}-inventory.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function sales(Request $request, CurrentShop $currentShop, AuditLogger $audit): StreamedResponse
    {
        $shop = $currentShop->from($request);
        $canViewProfit = $request->user()->hasShopPermission($shop, 'profit.view');

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
            throw ValidationException::withMessages(['to' => 'ช่วงเวลาที่ส่งออกต้องไม่เกิน 1 ปี']);
        }

        $audit->record($request, $shop, 'sales.exported', null, [
            'format' => 'csv',
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);

        $filename = "gamoryid-{$shop->slug}-sales-{$from->toDateString()}_{$to->toDateString()}.csv";

        return response()->streamDownload(function () use ($shop, $canViewProfit, $from, $to) {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, self::BOM);
            fputcsv($stream, [
                'sold_at', 'tag', 'title', 'sold_price',
                ...($canViewProfit ? ['cost', 'profit'] : []),
                'customer', 'has_warranty', 'warranty_ends_at', 'sold_by',
            ]);
            Sale::query()
                ->where('shop_id', $shop->id)
                ->whereBetween('sold_at', [$from, $to])
                ->with(['inventoryItem:id,tag,title', 'customer:id,name', 'creator:id,name'])
                ->orderBy('sold_at')
                ->chunk(500, function ($sales) use ($stream, $canViewProfit) {
                    foreach ($sales as $sale) {
                        fputcsv($stream, [
                            $sale->sold_at?->toDateTimeString(),
                            $sale->inventoryItem ? '#'.$sale->inventoryItem->tag : null,
                            $sale->inventoryItem?->title,
                            $sale->sold_price,
                            ...($canViewProfit ? [$sale->cost_snapshot, $sale->profit] : []),
                            $sale->customer?->name,
                            $sale->has_warranty ? 'yes' : 'no',
                            $sale->warranty_ends_at?->toDateString(),
                            $sale->creator?->name,
                        ]);
                    }
                });
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
