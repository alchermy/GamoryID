<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Services\AuditLogger;
use App\Services\CurrentShop;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __invoke(Request $request, CurrentShop $currentShop, AuditLogger $audit): StreamedResponse
    {
        $shop = $currentShop->from($request);
        $canViewProfit = $request->user()->hasShopPermission($shop, 'profit.view');
        $audit->record($request, $shop, 'inventory.exported', null, ['format' => 'csv']);

        return response()->streamDownload(function () use ($shop, $canViewProfit) {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, ['tag', 'title', 'region', 'rank', 'status', 'list_price', ...($canViewProfit ? ['cost'] : [])]);
            InventoryItem::withTrashed()->where('shop_id', $shop->id)->orderBy('id')->chunk(500, function ($items) use ($stream, $canViewProfit) {
                foreach ($items as $item) {
                    fputcsv($stream, ['#'.$item->tag, $item->title, $item->region, $item->rank, $item->status->value, $item->list_price, ...($canViewProfit ? [$item->cost] : [])]);
                }
            });
            fclose($stream);
        }, "gamoryid-{$shop->slug}-inventory.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
