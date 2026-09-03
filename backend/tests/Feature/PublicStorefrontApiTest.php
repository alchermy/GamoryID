<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryMedia;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicStorefrontApiTest extends TestCase
{
    use RefreshDatabase;

    private function shop(bool $enabled = true, string $slug = 'test-storefront'): Shop
    {
        // trialing → trial-tier plan, which grants the `storefront` feature.
        return Shop::create([
            'name' => 'ร้านทดสอบหน้าร้าน',
            'slug' => $slug,
            'status' => 'trialing',
            'trial_ends_at' => now()->addMonth(),
            'description' => 'ขายไอดี Valorant พร้อมส่ง',
            'line_url' => 'https://line.me/ti/p/@testshop',
            'facebook_url' => 'https://facebook.com/testshop',
            'phone' => '081-234-5678',
            'inventory_copy_footer' => 'ทักแชทได้ตลอด',
            'storefront_enabled' => $enabled,
        ]);
    }

    private function item(Shop $shop, string $tag, string $status = 'available', int $price = 6900): InventoryItem
    {
        return InventoryItem::create([
            'shop_id' => $shop->id,
            'tag' => $tag,
            'title' => "ไอดี {$tag}",
            'riot_id' => 'Secret#TH01',
            'username' => 'secret.login',
            'region' => 'TH',
            'rank' => 'Immortal 1',
            'level' => 240,
            'skin_count' => 55,
            'cost' => 3000,
            'list_price' => $price,
            'status' => $status,
        ]);
    }

    public function test_storefront_profile_and_inventory_are_public_and_filtered(): void
    {
        $shop = $this->shop();
        $this->item($shop, 'AAAAA', 'available');
        $this->item($shop, 'BBBBB', 'available');
        $this->item($shop, 'CCCCC', 'reserved');
        $this->item($shop, 'DDDDD', 'sold');
        $this->item($shop, 'EEEEE', 'available')->delete();

        $this->getJson('/api/v1/public/shops/test-storefront')
            ->assertOk()
            ->assertJsonPath('data.name', 'ร้านทดสอบหน้าร้าน')
            ->assertJsonPath('data.line_url', 'https://line.me/ti/p/@testshop')
            ->assertJsonPath('data.inventory_copy_footer', 'ทักแชทได้ตลอด');

        $list = $this->getJson('/api/v1/public/shops/test-storefront/inventory')->assertOk();

        $tags = collect($list->json('data'))->pluck('tag')->sort()->values()->all();
        $this->assertSame(['#AAAAA', '#BBBBB'], $tags);

        $row = collect($list->json('data'))->firstWhere('tag', '#AAAAA');
        $this->assertArrayNotHasKey('cost', $row);
        $this->assertArrayNotHasKey('username', $row);
        $this->assertArrayNotHasKey('riot_id', $row);
        $this->assertArrayNotHasKey('notes', $row);
        $this->assertSame('6900.00', $row['list_price']);
        $this->assertSame('Immortal 1', $row['rank']);
    }

    public function test_a_disabled_storefront_is_not_found(): void
    {
        $shop = $this->shop(enabled: false);
        $this->item($shop, 'AAAAA');

        $this->getJson('/api/v1/public/shops/test-storefront')->assertNotFound();
        $this->getJson('/api/v1/public/shops/test-storefront/inventory')->assertNotFound();
        $this->getJson('/api/v1/public/shops/test-storefront/items/AAAAA')->assertNotFound();
        $this->getJson('/api/v1/public/shops/no-such-shop')->assertNotFound();
    }

    public function test_a_shop_without_the_storefront_plan_feature_is_not_public(): void
    {
        // active + no subscription → Free plan → no `storefront` feature,
        // even though the flag is on.
        $shop = Shop::create([
            'name' => 'ร้านฟรี',
            'slug' => 'free-shop',
            'status' => 'active',
            'storefront_enabled' => true,
        ]);
        $this->item($shop, 'AAAAA', 'available');

        $this->getJson('/api/v1/public/shops/free-shop')->assertNotFound();
        $this->getJson('/api/v1/public/shops/free-shop/inventory')->assertNotFound();
        $this->assertSame([], $this->getJson('/api/v1/public/listings')->assertOk()->json('data'));
    }

    public function test_a_single_item_returns_its_media_and_hides_sold_items(): void
    {
        $shop = $this->shop();
        $available = $this->item($shop, 'AAAAA', 'available');
        InventoryMedia::create([
            'inventory_item_id' => $available->id,
            'role' => 'display',
            'disk' => 'private',
            'path' => 'inventory/1/1/display.png',
            'mime_type' => 'image/png',
            'size_bytes' => 1234,
            'sort_order' => 0,
        ]);
        $this->item($shop, 'ZZZZZ', 'sold');

        $detail = $this->getJson('/api/v1/public/shops/test-storefront/items/AAAAA')
            ->assertOk()
            ->assertJsonPath('data.tag', '#AAAAA');
        $this->assertStringContainsString('/api/v1/public/media/', $detail->json('data.media.0.image_url'));

        // accepts a leading '#' too
        $this->getJson('/api/v1/public/shops/test-storefront/items/%23AAAAA')->assertOk();
        $this->getJson('/api/v1/public/shops/test-storefront/items/ZZZZZ')->assertNotFound();
    }

    public function test_public_media_streams_only_for_available_items_of_open_storefronts(): void
    {
        Storage::fake('private');
        $shop = $this->shop();
        $item = $this->item($shop, 'AAAAA', 'available');
        $path = UploadedFile::fake()->create('display.png', 10, 'image/png')->store("inventory/{$shop->id}/{$item->id}", 'private');
        $media = InventoryMedia::create([
            'inventory_item_id' => $item->id,
            'role' => 'display',
            'disk' => 'private',
            'path' => $path,
            'mime_type' => 'image/png',
            'size_bytes' => 2048,
            'sort_order' => 0,
        ]);

        $this->get("/api/v1/public/media/{$media->id}")->assertOk();

        $item->update(['status' => 'sold']);
        $this->get("/api/v1/public/media/{$media->id}")->assertNotFound();

        $item->update(['status' => 'available']);
        $shop->update(['storefront_enabled' => false]);
        $this->get("/api/v1/public/media/{$media->id}")->assertNotFound();
    }

    public function test_aggregated_listings_span_open_shops_and_sort(): void
    {
        $a = $this->shop(slug: 'shop-a');
        $b = $this->shop(slug: 'shop-b');
        $closed = $this->shop(enabled: false, slug: 'shop-closed');

        $cheap = $this->item($a, 'AAAAA', 'available', 1000);
        $mid = $this->item($b, 'BBBBB', 'available', 5000);
        $pricey = $this->item($a, 'CCCCC', 'available', 9000);
        $this->item($b, 'DDDDD', 'sold', 2000);
        $this->item($closed, 'EEEEE', 'available', 1);
        InventoryItem::whereKey($mid->id)->update(['view_count' => 99]);

        $newest = $this->getJson('/api/v1/public/listings')->assertOk();
        $this->assertSame(
            ['#AAAAA', '#BBBBB', '#CCCCC'],
            collect($newest->json('data'))->pluck('tag')->sort()->values()->all(),
        );
        $row = collect($newest->json('data'))->firstWhere('tag', '#AAAAA');
        $this->assertSame('shop-a', $row['shop']['slug']);
        $this->assertArrayNotHasKey('cost', $row);
        $this->assertArrayNotHasKey('view_count', $row);

        $asc = $this->getJson('/api/v1/public/listings?sort=price_asc')->assertOk();
        $this->assertSame(['#AAAAA', '#BBBBB', '#CCCCC'], collect($asc->json('data'))->pluck('tag')->all());

        $desc = $this->getJson('/api/v1/public/listings?sort=price_desc')->assertOk();
        $this->assertSame(['#CCCCC', '#BBBBB', '#AAAAA'], collect($desc->json('data'))->pluck('tag')->all());

        $popular = $this->getJson('/api/v1/public/listings?sort=popular')->assertOk();
        $this->assertSame('#BBBBB', $popular->json('data.0.tag'));

        unset($cheap, $pricey);
    }

    public function test_view_counts_increment_once_per_visitor_then_dedup(): void
    {
        $shop = $this->shop();
        $item = $this->item($shop, 'AAAAA', 'available');
        $ua = ['User-Agent' => 'ViewTest/1.0'];

        $this->withHeaders($ua)->getJson('/api/v1/public/shops/test-storefront')->assertOk();
        $this->assertSame(1, $shop->fresh()->storefront_view_count);

        // same visitor within the dedup window — no change
        $this->withHeaders($ua)->getJson('/api/v1/public/shops/test-storefront')->assertOk();
        $this->assertSame(1, $shop->fresh()->storefront_view_count);

        // opening an item counts the item and the shop (different visitor)
        $this->withHeaders(['User-Agent' => 'Other/2.0'])
            ->getJson('/api/v1/public/shops/test-storefront/items/AAAAA')->assertOk();
        $this->assertSame(1, $item->fresh()->view_count);
        $this->assertSame(2, $shop->fresh()->storefront_view_count);
    }
}
