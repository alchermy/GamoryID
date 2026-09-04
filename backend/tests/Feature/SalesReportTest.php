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

class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Shop} */
    private function ownerOn(?string $planCode = null): array
    {
        $shop = Shop::create([
            'name' => 'ร้าน '.uniqid(),
            'slug' => 'sr-'.uniqid(),
            'status' => $planCode ? 'active' : 'trialing',
            'trial_ends_at' => $planCode ? null : now()->addMonth(),
        ]);
        $user = User::create([
            'name' => 'เจ้าของ',
            'email' => uniqid().'@example.test',
            'password' => 'password',
            'current_shop_id' => $shop->id,
            'email_verified_at' => now(),
        ]);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $user->id, 'role' => 'owner', 'permissions' => [], 'joined_at' => now()]);
        if ($planCode) {
            $plan = SubscriptionPlan::query()->where('code', $planCode)->firstOrFail();
            Subscription::create(['shop_id' => $shop->id, 'subscription_plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addDays(30)]);
        }

        return [$user, $shop];
    }

    private function sell(Shop $shop, User $user, string $tag, float $price, float $profit, \DateTimeInterface $soldAt): void
    {
        $item = InventoryItem::create(['shop_id' => $shop->id, 'tag' => $tag, 'title' => 'ID '.$tag, 'cost' => $price - $profit, 'list_price' => $price, 'status' => 'sold']);
        Sale::create([
            'shop_id' => $shop->id,
            'inventory_item_id' => $item->id,
            'created_by' => $user->id,
            'sold_price' => $price,
            'cost_snapshot' => $price - $profit,
            'profit' => $profit,
            'sold_at' => $soldAt,
        ]);
    }

    public function test_daily_series_is_zero_filled_and_totals_are_correct(): void
    {
        [$user, $shop] = $this->ownerOn();
        $this->sell($shop, $user, 'AAAAA', 5000, 2000, now());
        $this->sell($shop, $user, 'BBBBB', 3000, 1000, now());
        $this->sell($shop, $user, 'CCCCC', 1000, 500, now()->subDays(5));
        // outside the 30-day window — must be ignored by totals
        $this->sell($shop, $user, 'DDDDD', 9999, 9999, now()->subDays(45));

        $res = $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/reports/sales?granularity=day')
            ->assertOk()
            ->assertJsonPath('granularity', 'day')
            ->assertJsonCount(30, 'data')
            ->assertJsonPath('totals.revenue', 9000)
            ->assertJsonPath('totals.sales', 3);

        $today = collect($res->json('data'))->firstWhere('period', now()->toDateString());
        $this->assertSame(8000.0, (float) $today['revenue']);
        $this->assertSame(2, $today['sales']);
        $this->assertSame(3000.0, (float) $today['profit']);
    }

    public function test_month_and_year_granularities_return_the_right_bucket_counts(): void
    {
        [$user, $shop] = $this->ownerOn();

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/reports/sales?granularity=month')->assertOk()->assertJsonCount(12, 'data');
        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/reports/sales?granularity=year')->assertOk()->assertJsonCount(5, 'data');
        // unknown granularity falls back to day
        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/reports/sales?granularity=weird')->assertOk()
            ->assertJsonPath('granularity', 'day')->assertJsonCount(30, 'data');
    }

    public function test_previous_window_totals_are_reported_separately(): void
    {
        [$user, $shop] = $this->ownerOn();
        $this->sell($shop, $user, 'CURR1', 4000, 1000, now()->subDays(2));
        // 40 days ago sits in the previous 30-day window
        $this->sell($shop, $user, 'PREV1', 2500, 800, now()->subDays(40));

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/reports/sales?granularity=day')
            ->assertOk()
            ->assertJsonPath('totals.revenue', 4000)
            ->assertJsonPath('previous.revenue', 2500)
            ->assertJsonPath('previous.sales', 1);
    }

    public function test_profit_is_null_without_the_analytics_plan(): void
    {
        [$user, $shop] = $this->ownerOn('starter');
        $this->sell($shop, $user, 'STAR1', 4000, 1500, now());

        $res = $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/reports/sales?granularity=day')
            ->assertOk()
            ->assertJsonPath('totals.revenue', 4000)
            ->assertJsonPath('totals.profit', null);
        $this->assertNull($res->json('data.29.profit'));
    }

    public function test_profit_is_null_for_staff_without_the_permission(): void
    {
        [$owner, $shop] = $this->ownerOn();
        $this->sell($shop, $owner, 'OWN1', 4000, 1500, now());

        $staff = User::create(['name' => 'พนักงาน', 'email' => 'sr-staff@example.test', 'password' => 'password', 'current_shop_id' => $shop->id, 'email_verified_at' => now()]);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $staff->id, 'role' => 'staff', 'permissions' => ['inventory.sell'], 'joined_at' => now()]);

        $this->actingAs($staff)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/reports/sales?granularity=day')
            ->assertOk()
            ->assertJsonPath('totals.revenue', 4000)
            ->assertJsonPath('totals.profit', null);
    }
}
