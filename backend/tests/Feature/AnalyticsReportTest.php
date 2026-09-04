<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsReportTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Shop} */
    private function ownerOn(?string $planCode = null): array
    {
        $shop = Shop::create([
            'name' => 'ร้าน '.uniqid(),
            'slug' => 'ar-'.uniqid(),
            'status' => $planCode ? 'active' : 'trialing',
            'trial_ends_at' => $planCode ? null : now()->addMonth(),
        ]);
        $user = User::create(['name' => 'เจ้าของ', 'email' => uniqid().'@example.test', 'password' => 'password', 'current_shop_id' => $shop->id, 'email_verified_at' => now()]);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $user->id, 'role' => 'owner', 'permissions' => [], 'joined_at' => now()]);
        if ($planCode) {
            $plan = SubscriptionPlan::query()->where('code', $planCode)->firstOrFail();
            Subscription::create(['shop_id' => $shop->id, 'subscription_plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addDays(30)]);
        }

        return [$user, $shop];
    }

    private function staff(Shop $shop, array $permissions): User
    {
        $user = User::create(['name' => 'พนักงาน '.uniqid(), 'email' => uniqid().'@example.test', 'password' => 'password', 'current_shop_id' => $shop->id, 'email_verified_at' => now()]);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $user->id, 'role' => 'staff', 'permissions' => $permissions, 'joined_at' => now()]);

        return $user;
    }

    private function sell(Shop $shop, User $seller, array $attrs): void
    {
        $price = $attrs['price'];
        $item = InventoryItem::create([
            'shop_id' => $shop->id,
            'tag' => $attrs['tag'],
            'title' => 'ID '.$attrs['tag'],
            'rank' => $attrs['rank'] ?? null,
            'cost' => $price - ($attrs['profit'] ?? 0),
            'list_price' => $price,
            'status' => 'sold',
        ]);
        if (isset($attrs['listed_at'])) {
            $item->created_at = $attrs['listed_at'];
            $item->save();
        }
        Sale::create([
            'shop_id' => $shop->id,
            'inventory_item_id' => $item->id,
            'created_by' => $seller->id,
            'customer_id' => $attrs['customer_id'] ?? null,
            'sold_price' => $price,
            'cost_snapshot' => $price - ($attrs['profit'] ?? 0),
            'profit' => $attrs['profit'] ?? 0,
            'sold_at' => $attrs['sold_at'] ?? now(),
        ]);
    }

    public function test_the_endpoint_is_gated_by_the_analytics_plan_feature(): void
    {
        [$starterUser, $starterShop] = $this->ownerOn('starter');
        $this->actingAs($starterUser)->withHeader('X-Shop-Id', (string) $starterShop->id)
            ->getJson('/api/v1/reports/analytics')->assertForbidden();

        [$growthUser, $growthShop] = $this->ownerOn('growth');
        $this->actingAs($growthUser)->withHeader('X-Shop-Id', (string) $growthShop->id)
            ->getJson('/api/v1/reports/analytics')->assertOk();
    }

    public function test_permission_and_profit_visibility(): void
    {
        [$owner, $shop] = $this->ownerOn('growth');
        $this->sell($shop, $owner, ['tag' => 'AAAAA', 'price' => 5000, 'profit' => 2000, 'rank' => 'Immortal']);

        // no profit.view and no team.manage → forbidden
        $plain = $this->staff($shop, ['inventory.sell']);
        $this->actingAs($plain)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/reports/analytics')->assertForbidden();

        // profit.view → sees profit numbers
        $profitStaff = $this->staff($shop, ['inventory.sell', 'profit.view']);
        $this->actingAs($profitStaff)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/reports/analytics')->assertOk()
            ->assertJsonPath('summary.profit', 2000)
            ->assertJsonPath('summary.margin_pct', 40);

        // team.manage only → page loads but profit is hidden
        $manager = $this->staff($shop, ['team.manage']);
        $res = $this->actingAs($manager)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/reports/analytics')->assertOk()
            ->assertJsonPath('summary.profit', null)
            ->assertJsonPath('summary.margin_pct', null);
        $this->assertNull($res->json('by_rank.0.profit'));
        $this->assertNull($res->json('by_staff.0.profit'));
    }

    public function test_breakdowns_are_grouped_sorted_and_bucketed(): void
    {
        [$owner, $shop] = $this->ownerOn('growth');
        $staffB = $this->staff($shop, ['inventory.sell']);
        $vip = Customer::create(['shop_id' => $shop->id, 'name' => 'ลูกค้า VIP']);
        $walkin = Customer::create(['shop_id' => $shop->id, 'name' => 'ขาจร']);

        // Immortal ×2 (big), Diamond ×1 (small)
        $this->sell($shop, $owner, ['tag' => 'IMM01', 'price' => 12000, 'profit' => 4000, 'rank' => 'Immortal', 'customer_id' => $vip->id]);
        $this->sell($shop, $owner, ['tag' => 'IMM02', 'price' => 8000, 'profit' => 3000, 'rank' => 'Immortal', 'customer_id' => $vip->id]);
        $this->sell($shop, $staffB, ['tag' => 'DIA01', 'price' => 900, 'profit' => 300, 'rank' => 'Diamond', 'customer_id' => $walkin->id]);

        $res = $this->actingAs($owner)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/reports/analytics')->assertOk()
            ->assertJsonPath('summary.sales', 3)
            ->assertJsonPath('summary.revenue', 20900)
            ->assertJsonPath('summary.avg_price', round(20900 / 3, 2));

        // by_rank sorted by revenue desc
        $this->assertSame('Immortal', $res->json('by_rank.0.label'));
        $this->assertSame(20000.0, (float) $res->json('by_rank.0.revenue'));
        $this->assertSame('Diamond', $res->json('by_rank.1.label'));

        // price bands: always 5, "ต่ำกว่า 1,000" holds the 900 sale, "10,000 ขึ้นไป" holds the 12000
        $bands = collect($res->json('by_price_band'));
        $this->assertCount(5, $bands);
        $this->assertSame(1, $bands->firstWhere('label', 'ต่ำกว่า 1,000')['sales']);
        $this->assertSame(1, $bands->firstWhere('label', '10,000 ขึ้นไป')['sales']);
        $this->assertSame(1, $bands->firstWhere('label', '5,000–9,999')['sales']);

        // by_staff: owner leads
        $this->assertSame(20000.0, (float) $res->json('by_staff.0.revenue'));
        $this->assertSame(2, $res->json('by_staff.0.sales'));

        // top_customers: VIP first
        $this->assertSame('ลูกค้า VIP', $res->json('top_customers.0.name'));
        $this->assertSame(2, $res->json('top_customers.0.sales'));
        $this->assertNotNull($res->json('top_customers.0.last_bought_at'));
    }

    public function test_avg_days_to_sell_and_range_handling(): void
    {
        [$owner, $shop] = $this->ownerOn('growth');
        $this->sell($shop, $owner, ['tag' => 'SLOW1', 'price' => 3000, 'profit' => 500, 'listed_at' => now()->subDays(10), 'sold_at' => now()]);

        $res = $this->actingAs($owner)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/reports/analytics')->assertOk()
            ->assertJsonPath('range.from', now()->startOfMonth()->toDateString());
        $this->assertEqualsWithDelta(10, (float) $res->json('summary.avg_days_to_sell'), 1);

        // a sale before this month is excluded from the default (month-to-date) window
        $this->sell($shop, $owner, ['tag' => 'OLD01', 'price' => 9999, 'profit' => 9999, 'sold_at' => now()->startOfMonth()->subDays(3)]);
        $this->actingAs($owner)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/reports/analytics')->assertOk()
            ->assertJsonPath('summary.sales', 1);

        // validation
        $this->actingAs($owner)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/reports/analytics?from=2026-05-01&to=2026-04-01')->assertStatus(422);
        $this->actingAs($owner)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/reports/analytics?from=2024-01-01&to=2026-01-01')->assertStatus(422);
    }
}
