<?php

namespace App\Http\Controllers\Api;

use App\Enums\InventoryStatus;
use App\Http\Controllers\Controller;
use App\Models\InventoryMedia;
use Illuminate\Support\Facades\Storage;

class PublicMediaController extends Controller
{
    /**
     * Stream an inventory image without authentication — only for images that
     * belong to an "available" item of a shop that has opted its storefront in.
     */
    public function show(InventoryMedia $media)
    {
        $item = $media->inventoryItem()->with('shop')->firstOrFail();

        abort_unless(
            $item->shop
                && $item->shop->storefront_enabled
                && $item->status === InventoryStatus::Available,
            404,
        );

        return Storage::disk($media->disk)->response(
            $media->path,
            $media->original_name ?: basename($media->path),
            [
                'Content-Type' => $media->mime_type,
                'Cache-Control' => 'public, max-age=3600',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
