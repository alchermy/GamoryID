<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    private function shop(string $slug, bool $enabled = true): Shop
    {
        return Shop::create([
            'name' => 'ร้าน '.$slug,
            'slug' => $slug,
            'status' => 'trialing',
            'trial_ends_at' => now()->addMonth(),
            'storefront_enabled' => $enabled,
        ]);
    }

    public function test_sitemap_lists_opted_in_shops_and_their_available_items(): void
    {
        config(['app.storefront_url' => 'https://shop.example']);

        $open = $this->shop('open-shop');
        InventoryItem::create(['shop_id' => $open->id, 'tag' => 'LIVE1', 'title' => 'x', 'cost' => 1, 'list_price' => 100, 'status' => 'available']);
        $soldItem = InventoryItem::create(['shop_id' => $open->id, 'tag' => 'SOLD1', 'title' => 'x', 'cost' => 1, 'list_price' => 100, 'status' => 'sold']);
        $hiddenItem = InventoryItem::create(['shop_id' => $open->id, 'tag' => 'HIDE1', 'title' => 'x', 'cost' => 1, 'list_price' => 100, 'status' => 'available']);
        $hiddenItem->update(['hidden_from_directory' => true]);

        $closed = $this->shop('closed-shop', enabled: false);
        InventoryItem::create(['shop_id' => $closed->id, 'tag' => 'NOPE1', 'title' => 'x', 'cost' => 1, 'list_price' => 100, 'status' => 'available']);

        $hidden = $this->shop('hidden-shop');
        $hidden->update(['hidden_from_directory' => true]);

        $body = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->getContent();

        $this->assertStringContainsString('<loc>https://shop.example/</loc>', $body);
        $this->assertStringContainsString('<loc>https://shop.example/browse</loc>', $body);
        $this->assertStringContainsString('<loc>https://shop.example/s/open-shop</loc>', $body);
        $this->assertStringContainsString('<loc>https://shop.example/s/open-shop/LIVE1</loc>', $body);

        $this->assertStringNotContainsString('/s/closed-shop', $body);
        $this->assertStringNotContainsString('/s/hidden-shop', $body);
        $this->assertStringNotContainsString('SOLD1', $body);
        $this->assertStringNotContainsString('HIDE1', $body);

        unset($soldItem);
    }

    public function test_robots_points_at_the_dynamic_sitemap(): void
    {
        $body = $this->get('/robots.txt')->assertOk()->getContent();
        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $body);
    }
}
