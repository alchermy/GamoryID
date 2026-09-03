<?php

namespace App\Http\Controllers\Api;

use App\Enums\InventoryStatus;
use App\Http\Controllers\Controller;
use App\Models\InventoryMedia;
use App\Services\PlanEntitlements;
use Illuminate\Support\Facades\Storage;

class PublicMediaController extends Controller
{
    /**
     * Stream an inventory image without authentication — only for images that
     * belong to an "available" item of a shop whose plan includes the public
     * storefront and which has opted it in.
     */
    public function show(InventoryMedia $media, PlanEntitlements $entitlements)
    {
        $item = $media->inventoryItem()->with('shop')->firstOrFail();

        abort_unless(
            $item->shop
                && $item->shop->storefront_enabled
                && $entitlements->can($item->shop, 'storefront')
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
