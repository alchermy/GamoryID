<?php

namespace App\Http\Controllers;

use App\Enums\InventoryStatus;
use App\Models\InventoryItem;
use App\Models\Shop;
use App\Services\PlanEntitlements;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function __construct(private readonly PlanEntitlements $entitlements)
    {
    }

    public function sitemap(): Response
    {
        $xml = Cache::remember('sitemap.storefront', 1800, fn () => $this->build());

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $body = "User-agent: *\nAllow: /\n\nSitemap: ".url('/sitemap.xml')."\n";

        return response($body, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    private function build(): string
    {
        $base = rtrim(config('app.storefront_url'), '/');

        $urls = [
            ['loc' => $base.'/', 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => $base.'/browse', 'changefreq' => 'hourly', 'priority' => '0.9'],
        ];

        $shops = Shop::query()
            ->where('storefront_enabled', true)
            ->where('hidden_from_directory', false)
            ->get()
            ->filter(fn (Shop $shop) => $this->entitlements->can($shop, 'storefront'));

        foreach ($shops as $shop) {
            $urls[] = [
                'loc' => "{$base}/s/{$shop->slug}",
                'lastmod' => $shop->updated_at?->toAtomString(),
                'changefreq' => 'daily',
                'priority' => '0.8',
            ];

            InventoryItem::forShop($shop)
                ->where('status', InventoryStatus::Available)
                ->where('hidden_from_directory', false)
                ->orderBy('id')
                ->get(['tag', 'updated_at'])
                ->each(function (InventoryItem $item) use (&$urls, $base, $shop) {
                    $urls[] = [
                        'loc' => "{$base}/s/{$shop->slug}/".rawurlencode($item->tag),
                        'lastmod' => $item->updated_at?->toAtomString(),
                        'changefreq' => 'weekly',
                        'priority' => '0.6',
                    ];
                });
        }

        $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $url) {
            $body .= '  <url>'."\n".'    <loc>'.htmlspecialchars($url['loc'], ENT_XML1).'</loc>'."\n";
            if (! empty($url['lastmod'])) {
                $body .= '    <lastmod>'.$url['lastmod'].'</lastmod>'."\n";
            }
            $body .= '    <changefreq>'.$url['changefreq'].'</changefreq>'."\n"
                .'    <priority>'.$url['priority'].'</priority>'."\n"
                .'  </url>'."\n";
        }
        $body .= '</urlset>'."\n";

        return $body;
    }
}
