<?php

namespace App\Http\Controllers\Api;

use App\Enums\InventoryStatus;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryMedia;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PublicStorefrontController extends Controller
{
    public function show(Request $request, Shop $shop)
    {
        abort_unless($shop->storefront_enabled, 404);
        $this->recordView($request, 'shop', $shop->id);

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
                'meta' => $this->meta($page),
            ])
            ->header('Cache-Control', 'public, max-age=60');
    }

    public function item(Request $request, Shop $shop, string $tag)
    {
        abort_unless($shop->storefront_enabled, 404);

        $item = InventoryItem::forShop($shop)
            ->where('status', InventoryStatus::Available)
            ->where('tag', ltrim($tag, '#'))
            ->with('media')
            ->firstOrFail();

        $this->recordView($request, 'item', $item->id, $shop->id);

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
     * Every "available" item across all opted-in shops, one flat grid.
     */
    public function listings(Request $request)
    {
        $sort = $request->query('sort', 'newest');

        $query = InventoryItem::query()
            ->where('status', InventoryStatus::Available)
            ->whereHas('shop', fn ($shop) => $shop->where('storefront_enabled', true))
            ->with([
                'media' => fn ($media) => $media->where('role', InventoryMedia::DISPLAY),
                'shop:id,name,slug',
            ]);

        match ($sort) {
            'price_asc' => $query->orderBy('list_price')->orderByDesc('updated_at'),
            'price_desc' => $query->orderByDesc('list_price')->orderByDesc('updated_at'),
            'popular' => $query->orderByDesc('view_count')->orderByDesc('updated_at'),
            default => $query->orderByDesc('updated_at'),
        };

        $page = $query->paginate(24);

        return response()
            ->json([
                'data' => $page->getCollection()->map(fn (InventoryItem $item) => $this->listingPayload($item) + [
                    'shop' => ['name' => $item->shop?->name, 'slug' => $item->shop?->slug],
                ])->all(),
                'meta' => $this->meta($page),
            ])
            ->header('Cache-Control', 'public, max-age=60');
    }

    /**
     * Bump a storefront view counter, deduped per visitor (IP + UA) for 6h via
     * the cache store. Uses a raw atomic increment so `updated_at` / `lock_version`
     * on inventory rows are left untouched (the storefront sorts by `updated_at`).
     */
    private function recordView(Request $request, string $type, int $id, ?int $shopId = null): void
    {
        $hash = sha1($request->ip().'|'.substr((string) $request->userAgent(), 0, 200));

        if (Cache::add("sfv:$type:$id:$hash", true, now()->addHours(6))) {
            if ($type === 'shop') {
                DB::table('shops')->where('id', $id)->increment('storefront_view_count');
            } else {
                DB::table('inventory_items')->where('id', $id)->increment('view_count');
            }
        }

        if ($type === 'item' && $shopId !== null) {
            $this->recordView($request, 'shop', $shopId);
        }
    }

    private function meta(\Illuminate\Contracts\Pagination\LengthAwarePaginator $page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ];
    }

    /**
     * Public-safe fields only — cost, username, riot_id, notes, custom_values,
     * view_count, and credential flags are deliberately omitted.
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
