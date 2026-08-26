<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryMedia;
use App\Services\AuditLogger;
use App\Services\CurrentShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class InventoryMediaController extends Controller
{
    public function index(Request $request, int $inventory, CurrentShop $currentShop)
    {
        $shop = $currentShop->from($request);
        $item = InventoryItem::forShop($shop)->findOrFail($inventory);
        $media = InventoryMedia::where('inventory_item_id', $item->id)->orderBy('sort_order')->get()->map(fn ($file) => [
            'id' => $file->id,
            'mime_type' => $file->mime_type,
            'size_bytes' => $file->size_bytes,
            'url' => URL::temporarySignedRoute('api.media.show', now()->addMinutes(10), ['media' => $file->id]),
        ]);

        return response()->json(['data' => $media]);
    }

    public function store(Request $request, int $inventory, CurrentShop $currentShop, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $item = InventoryItem::forShop($shop)->findOrFail($inventory);
        $request->validate(['image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120']]);
        $upload = $request->file('image');
        $path = $upload->store("inventory/{$shop->id}/{$item->id}", 'private');
        $media = InventoryMedia::create([
            'inventory_item_id' => $item->id,
            'disk' => 'private',
            'path' => $path,
            'mime_type' => $upload->getMimeType(),
            'size_bytes' => $upload->getSize(),
            'sort_order' => InventoryMedia::where('inventory_item_id', $item->id)->max('sort_order') + 1,
        ]);
        $audit->record($request, $shop, 'inventory.media_added', $item, ['media_id' => $media->id]);

        return response()->json(['data' => $media], 201);
    }

    public function show(Request $request, InventoryMedia $media, CurrentShop $currentShop)
    {
        $shop = $currentShop->from($request);
        abort_unless($media->inventoryItem()->where('shop_id', $shop->id)->exists(), 404);

        return response()->file(Storage::disk($media->disk)->path($media->path), ['Content-Type' => $media->mime_type, 'Cache-Control' => 'private, max-age=600']);
    }

    public function destroy(Request $request, InventoryMedia $media, CurrentShop $currentShop, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $item = $media->inventoryItem()->where('shop_id', $shop->id)->firstOrFail();
        Storage::disk($media->disk)->delete($media->path);
        $audit->record($request, $shop, 'inventory.media_deleted', $item, ['media_id' => $media->id]);
        $media->delete();

        return response()->json(['message' => 'ลบรูปแล้ว']);
    }
}
