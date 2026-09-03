<?php

namespace App\Http\Controllers\Api;

use App\Enums\InventoryStatus;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryMedia;
use App\Models\Shop;
use Illuminate\Http\Request;

class PublicStorefrontController extends Controller
{
    public function show(Shop $shop)
    {
        abort_unless($shop->storefront_enabled, 404);

        return response()
            ->json(['data' => [
                'name' => $shop->name,
                'slug' => $shop->slug,
                'description' => $shop->description,
                'facebook_url' => $shop->facebook_url,
                'line_url' => $shop->line_url,
                'phone' => $shop->phone,
                'inventory_copy_footer' => $shop->inventory_copy_footer,
                'timezone' => $shop->timezone,
            ]])
            ->header('Cache-Control', 'public, max-age=60');
    }

    public function inventory(Request $request, Shop $shop)
    {
        abort_unless($shop->storefront_enabled, 404);

        $page = InventoryItem::forShop($shop)
            ->where('status', InventoryStatus::Available)
            ->with(['media' => fn ($query) => $query->where('role', InventoryMedia::DISPLAY)])
            ->orderByDesc('updated_at')
            ->paginate(24);

        return response()
            ->json([
                'data' => $page->getCollection()->map(fn (InventoryItem $item) => $this->listingPayload($item))->all(),
                'meta' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'total' => $page->total(),
                ],
            ])
            ->header('Cache-Control', 'public, max-age=60');
    }

    public function item(Shop $shop, string $tag)
    {
        abort_unless($shop->storefront_enabled, 404);

        $item = InventoryItem::forShop($shop)
            ->where('status', InventoryStatus::Available)
            ->where('tag', ltrim($tag, '#'))
            ->with('media')
            ->firstOrFail();

        return response()
            ->json(['data' => $this->listingPayload($item) + [
                'media' => $item->media->map(fn (InventoryMedia $media) => [
                    'id' => $media->id,
                    'role' => $media->role,
                    'image_url' => url('/api/v1/public/media/'.$media->id),
                ])->all(),
            ]])
            ->header('Cache-Control', 'public, max-age=60');
    }

    /**
     * Public-safe fields only — cost, username, riot_id, notes, custom_values,
     * and credential flags are deliberately omitted.
     */
    private function listingPayload(InventoryItem $item): array
    {
        $display = $item->relationLoaded('media')
            ? $item->media->firstWhere('role', InventoryMedia::DISPLAY)
            : null;

        return [
            'tag' => '#'.$item->tag,
            'title' => $item->title,
            'rank' => $item->rank,
            'level' => $item->level,
            'skin_count' => $item->skin_count,
            'battlepass_level' => $item->battlepass_level,
            'description' => $item->description,
            'list_price' => $item->list_price,
            'updated_at' => $item->updated_at,
            'image' => $display ? url('/api/v1/public/media/'.$display->id) : null,
        ];
    }
}
