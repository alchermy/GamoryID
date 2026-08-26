<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_cannot_read_another_shops_inventory(): void
    {
        [$user, $shopA] = $this->owner('a@example.test', 'ร้าน A');
        [, $shopB] = $this->owner('b@example.test', 'ร้าน B');
        $foreign = $this->item($shopB, 'ZX234');

        $this->actingAs($user)
            ->withHeader('X-Shop-Id', (string) $shopA->id)
            ->getJson("/api/v1/inventory/{$foreign->id}")
            ->assertNotFound();

        $this->actingAs($user)
            ->withHeader('X-Shop-Id', (string) $shopB->id)
            ->getJson('/api/v1/inventory')
            ->assertNotFound();
    }

    public function test_create_generates_tag_and_never_exposes_credentials(): void
    {
        [$user, $shop] = $this->owner('owner@example.test', 'Nexus Store');
        $response = $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)->postJson('/api/v1/inventory', [
            'title' => 'Reaver Vandal', 'region' => 'AP', 'rank' => 'Diamond 3',
            'cost' => 4000, 'list_price' => 6500,
            'credentials' => ['username' => 'secret@example.test', 'password' => 'very-secret'],
        ]);

        $response->assertCreated()->assertJsonPath('data.has_credentials', true)->assertJsonMissing(['password' => 'very-secret']);
        $this->assertMatchesRegularExpression('/^#[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{5}$/', $response->json('data.tag'));
        $this->assertDatabaseCount('inventory_credentials', 1);
    }

    public function test_exact_tag_search_is_scoped_to_current_shop(): void
    {
        [$user, $shop] = $this->owner('find@example.test', 'ร้านค้นหา');
        $this->item($shop, '23DX5');
        $this->item($shop, 'Q7N2P');

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/inventory?q=%2323DX5')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.tag', '#23DX5');
    }

    public function test_an_item_can_only_be_sold_once(): void
    {
        [$user, $shop] = $this->owner('sell@example.test', 'ร้านขาย');
        $item = $this->item($shop, 'S4LE5');
        $payload = ['customer' => ['name' => 'ลูกค้า'], 'sold_price' => 5900];

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson("/api/v1/inventory/{$item->id}/sell", $payload)->assertCreated();
        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson("/api/v1/inventory/{$item->id}/sell", $payload)->assertConflict();
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_grace_shop_is_read_only_but_can_list_inventory(): void
    {
        [$user, $shop] = $this->owner('grace@example.test', 'ร้านหมดอายุ');
        $shop->update(['status' => 'grace_read_only']);
        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)->getJson('/api/v1/inventory')->assertOk();
        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)->postJson('/api/v1/inventory', [
            'title' => 'Blocked', 'cost' => 0, 'list_price' => 1,
        ])->assertStatus(423)->assertJsonPath('code', 'SHOP_READ_ONLY');
    }

    private function owner(string $email, string $name): array
    {
        $shop = Shop::create(['name' => $name, 'slug' => str($name)->slug().'-'.uniqid(), 'status' => 'trialing', 'trial_ends_at' => now()->addMonth()]);
        $user = User::create(['name' => 'เจ้าของร้าน', 'email' => $email, 'password' => 'password', 'current_shop_id' => $shop->id, 'email_verified_at' => now()]);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $user->id, 'role' => 'owner', 'permissions' => [], 'joined_at' => now()]);

        return [$user, $shop];
    }

    private function item(Shop $shop, string $tag): InventoryItem
    {
        return InventoryItem::create(['shop_id' => $shop->id, 'tag' => $tag, 'title' => "Item {$tag}", 'cost' => 3000, 'list_price' => 5900, 'status' => 'available']);
    }
}
