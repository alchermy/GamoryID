<?php

namespace App\Http\Controllers\Api;

use App\Enums\InventoryStatus;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryMedia;
use App\Models\Shop;
use App\Services\PlanEntitlements;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicStorefrontController extends Controller
{
    public function __construct(private readonly PlanEntitlements $entitlements)
    {
    }

    public function show(Request $request, Shop $shop)
    {
        $this->ensureVisible($shop);
        $this->recordView($request, 'shop', $shop->id);

        $base = rtrim(config('app.storefront_url'), '/');

        return response()
            ->json(['data' => [
                'name' => $shop->name,
                'slug' => $shop->slug,
                'description' => $shop->description,
                'facebook_url' => $shop->facebook_url,
                'line_url' => $shop->line_url,
                'phone' => $shop->phone,
                'inventory_copy_footer' => $shop->inventory_copy_footer,
                'logo_url' => $shop->logoUrl(),
                'banner_url' => $shop->bannerUrl(),
                'timezone' => $shop->timezone,
                'og_title' => $shop->name.' — ร้านไอดีเกมบน GamoryID',
                'og_description' => Str::limit($shop->description ?: "ดูไอดีพร้อมขายและช่องทางติดต่อร้าน {$shop->name}", 155),
                'og_image' => $shop->bannerUrl() ?? $shop->logoUrl(),
                'canonical' => "{$base}/s/{$shop->slug}",
            ]])
            ->header('Cache-Control', 'public, max-age=60');
    }

    /** Stream the shop's storefront logo or banner (public, gated on opt-in). */
    public function branding(Shop $shop, string $target)
    {
        abort_unless(in_array($target, ['logo', 'banner'], true), 404);
        $this->ensureVisible($shop);
        $path = $shop->{"{$target}_path"};
        abort_if(! $path, 404);

        return Storage::disk('private')->response($path, null, [
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function inventory(Request $request, Shop $shop)
    {
        $this->ensureVisible($shop);

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
        $this->ensureVisible($shop);

        $item = InventoryItem::forShop($shop)
            ->where('status', InventoryStatus::Available)
            ->where('tag', ltrim($tag, '#'))
            ->with('media')
            ->firstOrFail();

        $this->recordView($request, 'item', $item->id, $shop->id);

        $media = $item->media->map(fn (InventoryMedia $m) => [
            'id' => $m->id,
            'role' => $m->role,
            'image_url' => url('/api/v1/public/media/'.$m->id),
        ])->all();
        $display = $item->media->firstWhere('role', InventoryMedia::DISPLAY);
        $base = rtrim(config('app.storefront_url'), '/');

        return response()
            ->json(['data' => $this->listingPayload($item) + [
                'media' => $media,
                'og_title' => trim("#{$item->tag} ".($item->title ?? '')).' — '.$shop->name,
                'og_description' => Str::limit($item->description ?: "ไอดี #{$item->tag} จากร้าน {$shop->name} บน GamoryID", 155),
                'og_image' => $display ? url('/api/v1/public/media/'.$display->id) : $shop->bannerUrl(),
                'canonical' => "{$base}/s/{$shop->slug}/{$item->tag}",
            ]])
            ->header('Cache-Control', 'public, max-age=60');
    }

    /**
     * Every "available" item across all opted-in shops, one flat grid.
     */
    public function listings(Request $request)
    {
        $sort = $request->query('sort', 'newest');

        // "storefront" is a plan feature, not a column — resolve the eligible
        // shop ids up front. Fine at launch scale; revisit with a denormalised
        // flag if the number of opted-in shops grows large.
        $eligibleShopIds = Shop::query()
            ->where('storefront_enabled', true)
            ->where('hidden_from_directory', false)
            ->get()
            ->filter(fn (Shop $shop) => $this->entitlements->can($shop, 'storefront'))
            ->pluck('id');

        $query = InventoryItem::query()
            ->where('status', InventoryStatus::Available)
            ->where('hidden_from_directory', false)
            ->whereIn('shop_id', $eligibleShopIds)
            ->with([
                'media' => fn ($media) => $media->where('role', InventoryMedia::DISPLAY),
                'shop:id,name,slug,logo_path',
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
                    'shop' => [
                        'name' => $item->shop?->name,
                        'slug' => $item->shop?->slug,
                        'logo_url' => $item->shop?->logoUrl(),
                    ],
                ])->all(),
                'meta' => $this->meta($page),
            ])
            ->header('Cache-Control', 'public, max-age=60');
    }

    private function ensureVisible(Shop $shop): void
    {
        abort_unless(
            $shop->storefront_enabled && $this->entitlements->can($shop, 'storefront'),
            404,
        );
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
                $this->bumpDailyShopView($id);
            } else {
                DB::table('inventory_items')->where('id', $id)->increment('view_count');
            }
        }

        if ($type === 'item' && $shopId !== null) {
            $this->recordView($request, 'shop', $shopId);
        }
    }

    /**
     * Add today's shop view to the per-day rollup (portable increment-or-insert,
     * MySQL + SQLite). Only reached for views that already passed the 6h dedup.
     */
    private function bumpDailyShopView(int $shopId): void
    {
        $today = now()->toDateString();
        $updated = DB::table('shop_view_daily')
            ->where('shop_id', $shopId)->where('date', $today)
            ->increment('views');

        if ($updated === 0) {
            try {
                DB::table('shop_view_daily')->insert([
                    'shop_id' => $shopId,
                    'date' => $today,
                    'views' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Illuminate\Database\QueryException) {
                // Lost the insert race — the row exists now, just bump it.
                DB::table('shop_view_daily')
                    ->where('shop_id', $shopId)->where('date', $today)
                    ->increment('views');
            }
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
