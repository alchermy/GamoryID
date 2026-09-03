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

    private function shop(bool $enabled = true): Shop
    {
        return Shop::create([
            'name' => 'ร้านทดสอบหน้าร้าน',
            'slug' => 'test-storefront',
            'status' => 'active',
            'description' => 'ขายไอดี Valorant พร้อมส่ง',
            'line_url' => 'https://line.me/ti/p/@testshop',
            'facebook_url' => 'https://facebook.com/testshop',
            'phone' => '081-234-5678',
            'inventory_copy_footer' => 'ทักแชทได้ตลอด',
            'storefront_enabled' => $enabled,
        ]);
    }

    private function item(Shop $shop, string $tag, string $status = 'available'): InventoryItem
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
            'list_price' => 6900,
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
}
