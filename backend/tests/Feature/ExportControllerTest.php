<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Shop} */
    private function owner(?string $planCode, array $permissions = [], string $role = 'owner'): array
    {
        $shop = Shop::create(['name' => 'ร้าน '.uniqid(), 'slug' => 'exp-'.uniqid(), 'status' => 'active']);
        $user = User::create(['name' => 'ผู้ใช้', 'email' => uniqid().'@example.test', 'password' => 'password', 'current_shop_id' => $shop->id, 'email_verified_at' => now()]);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $user->id, 'role' => $role, 'permissions' => $permissions, 'joined_at' => now()]);
        if ($planCode) {
            $plan = SubscriptionPlan::query()->where('code', $planCode)->firstOrFail();
            Subscription::create(['shop_id' => $shop->id, 'subscription_plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addDays(30)]);
        }

        return [$user, $shop];
    }

    private function req(User $user, Shop $shop, string $uri)
    {
        return $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)->get($uri);
    }

    public function test_inventory_csv_is_available_on_any_plan_and_has_a_utf8_bom(): void
    {
        [$user, $shop] = $this->owner(null); // free
        InventoryItem::create(['shop_id' => $shop->id, 'tag' => 'AAAAA', 'title' => 'ไอดีทดสอบ', 'cost' => 3000, 'list_price' => 6900, 'status' => 'available']);

        $response = $this->req($user, $shop, '/api/v1/export/inventory.csv')->assertOk();
        $body = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('tag,riot_id,username,rank,status,list_price,cost', $body);
        $this->assertStringContainsString('#AAAAA', $body);
    }

    public function test_inventory_csv_hides_cost_without_the_profit_permission(): void
    {
        [$user, $shop] = $this->owner(null, ['data.export'], 'staff');
        InventoryItem::create(['shop_id' => $shop->id, 'tag' => 'BBBBB', 'title' => 'x', 'cost' => 3000, 'list_price' => 6900, 'status' => 'available']);

        $body = $this->req($user, $shop, '/api/v1/export/inventory.csv')->assertOk()->streamedContent();
        $header = strtok($body, "\n");
        $this->assertStringContainsString('status,list_price', $header);
        $this->assertStringNotContainsString('cost', $header);
    }

    public function test_inventory_csv_needs_the_data_export_permission(): void
    {
        [$user, $shop] = $this->owner(null, [], 'staff');

        $this->req($user, $shop, '/api/v1/export/inventory.csv')->assertForbidden();
    }

    public function test_sales_csv_is_gated_by_the_advanced_export_feature(): void
    {
        [$starterUser, $starterShop] = $this->owner('starter');
        $this->req($starterUser, $starterShop, '/api/v1/export/sales.csv')->assertForbidden();

        [$growthUser, $growthShop] = $this->owner('growth');
        $this->req($growthUser, $growthShop, '/api/v1/export/sales.csv')->assertOk();
    }

    public function test_sales_csv_filters_by_date_range_and_includes_profit_for_owners(): void
    {
        [$user, $shop] = $this->owner('growth');
        $inRange = InventoryItem::create(['shop_id' => $shop->id, 'tag' => 'INRNG', 'title' => 'ขายในช่วง', 'cost' => 1000, 'list_price' => 2000, 'status' => 'sold']);
        $outRange = InventoryItem::create(['shop_id' => $shop->id, 'tag' => 'OUTRG', 'title' => 'ขายนอกช่วง', 'cost' => 1000, 'list_price' => 3000, 'status' => 'sold']);
        Sale::create(['shop_id' => $shop->id, 'inventory_item_id' => $inRange->id, 'sold_price' => 2000, 'cost_snapshot' => 1000, 'profit' => 1000, 'sold_at' => '2026-06-15 10:00:00']);
        Sale::create(['shop_id' => $shop->id, 'inventory_item_id' => $outRange->id, 'sold_price' => 3000, 'cost_snapshot' => 1000, 'profit' => 2000, 'sold_at' => '2026-05-01 10:00:00']);

        $body = $this->req($user, $shop, '/api/v1/export/sales.csv?from=2026-06-01&to=2026-06-30')
            ->assertOk()->streamedContent();

        $this->assertStringContainsString('sold_at,tag,title,sold_price,cost,profit', $body);
        $this->assertStringContainsString('#INRNG', $body);
        $this->assertStringNotContainsString('#OUTRG', $body);
    }

    public function test_sales_csv_rejects_an_inverted_date_range(): void
    {
        [$user, $shop] = $this->owner('growth');

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/export/sales.csv?from=2026-06-30&to=2026-06-01')
            ->assertStatus(422);
    }
}
