<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorefrontViewAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Shop} */
    private function ownerOn(?string $planCode): array
    {
        $shop = Shop::create(['name' => 'ร้าน '.uniqid(), 'slug' => 'sv-'.uniqid(), 'status' => 'active', 'storefront_enabled' => true]);
        $user = User::create(['name' => 'เจ้าของ', 'email' => uniqid().'@example.test', 'password' => 'password', 'current_shop_id' => $shop->id, 'email_verified_at' => now()]);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $user->id, 'role' => 'owner', 'permissions' => [], 'joined_at' => now()]);
        if ($planCode) {
            $plan = SubscriptionPlan::query()->where('code', $planCode)->firstOrFail();
            Subscription::create(['shop_id' => $shop->id, 'subscription_plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addDays(30)]);
        }

        return [$user, $shop];
    }

    public function test_a_storefront_visit_writes_a_per_day_rollup_row(): void
    {
        $shop = Shop::create(['name' => 'ร้านสถิติ', 'slug' => 'stats-shop', 'status' => 'trialing', 'trial_ends_at' => now()->addMonth(), 'storefront_enabled' => true]);
        InventoryItem::create(['shop_id' => $shop->id, 'tag' => 'AAAAA', 'title' => 'x', 'cost' => 1, 'list_price' => 100, 'status' => 'available']);

        $this->withHeaders(['User-Agent' => 'A/1'])->getJson('/api/v1/public/shops/stats-shop')->assertOk();
        $this->withHeaders(['User-Agent' => 'B/2'])->getJson('/api/v1/public/shops/stats-shop')->assertOk();
        // same visitor again — deduped, no extra count
        $this->withHeaders(['User-Agent' => 'B/2'])->getJson('/api/v1/public/shops/stats-shop')->assertOk();

        $this->assertDatabaseHas('shop_view_daily', [
            'shop_id' => $shop->id,
            'date' => now()->toDateString(),
            'views' => 2,
        ]);
        $this->assertSame(2, $shop->fresh()->storefront_view_count);
    }

    public function test_views_endpoint_returns_a_zero_filled_series_for_analytics_plans(): void
    {
        [$user, $shop] = $this->ownerOn('growth');
        DB::table('shop_view_daily')->insert([
            ['shop_id' => $shop->id, 'date' => now()->toDateString(), 'views' => 9, 'created_at' => now(), 'updated_at' => now()],
            ['shop_id' => $shop->id, 'date' => now()->subDays(3)->toDateString(), 'views' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('shops')->where('id', $shop->id)->update(['storefront_view_count' => 13]);

        $res = $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/storefront/views?granularity=day')
            ->assertOk()
            ->assertJsonPath('granularity', 'day')
            ->assertJsonPath('total', 13)
            ->assertJsonCount(30, 'data');

        $today = collect($res->json('data'))->firstWhere('period', now()->toDateString());
        $this->assertSame(9, $today['views']);

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/storefront/views?granularity=month')->assertOk()->assertJsonCount(12, 'data');
        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/storefront/views?granularity=year')->assertOk()->assertJsonCount(5, 'data');
    }

    public function test_views_endpoint_is_gated_by_the_analytics_feature(): void
    {
        [$starterUser, $starterShop] = $this->ownerOn('starter');
        $this->actingAs($starterUser)->withHeader('X-Shop-Id', (string) $starterShop->id)
            ->getJson('/api/v1/storefront/views')->assertForbidden();
    }
}
