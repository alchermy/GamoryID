<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanFeatureGateTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, Shop} */
    private function ownerOn(?string $planCode): array
    {
        $shop = Shop::create(['name' => 'ร้าน '.uniqid(), 'slug' => 'gate-'.uniqid(), 'status' => 'active']);
        $user = User::create(['name' => 'เจ้าของ', 'email' => uniqid().'@example.test', 'password' => 'password', 'current_shop_id' => $shop->id, 'email_verified_at' => now()]);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $user->id, 'role' => 'owner', 'permissions' => [], 'joined_at' => now()]);
        if ($planCode) {
            $plan = SubscriptionPlan::query()->where('code', $planCode)->firstOrFail();
            Subscription::create(['shop_id' => $shop->id, 'subscription_plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addDays(30)]);
        }

        return [$user, $shop];
    }

    private function req(User $user, Shop $shop, string $method, string $uri)
    {
        return $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)->json($method, $uri);
    }

    public function test_a_free_shop_is_blocked_from_paid_features(): void
    {
        [$user, $shop] = $this->ownerOn(null); // no subscription → Free

        $this->req($user, $shop, 'GET', '/api/v1/activity')->assertForbidden();
        $this->req($user, $shop, 'GET', '/api/v1/discord/settings')->assertForbidden();
        // inventory CSV is basic data portability — allowed on any plan with the permission;
        // the sales/profit report stays a paid (advanced_export) feature.
        $this->req($user, $shop, 'GET', '/api/v1/export/inventory.csv')->assertOk();
        $this->req($user, $shop, 'GET', '/api/v1/export/sales.csv')->assertForbidden();
        $this->req($user, $shop, 'POST', '/api/v1/imports/preview')->assertForbidden();

        $this->req($user, $shop, 'GET', '/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('summary.profit_this_month', null)
            ->assertJsonPath('subscription.effective_plan.code', 'free');

        // the public storefront is a Starter+ feature
        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->putJson('/api/v1/shop', [
                'name' => 'ร้านฟรี', 'slug' => 'free-'.substr(uniqid(), -6),
                'storefront_enabled' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['storefront_enabled']);
    }

    public function test_a_starter_shop_can_open_its_public_storefront(): void
    {
        [$user, $shop] = $this->ownerOn('starter');

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->putJson('/api/v1/shop', [
                'name' => 'ร้านสตาร์ท', 'slug' => 'starter-'.substr(uniqid(), -6),
                'storefront_enabled' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.storefront_enabled', true);
    }

    public function test_a_starter_shop_gets_log_import_and_discord_but_not_exports_or_analytics(): void
    {
        [$user, $shop] = $this->ownerOn('starter');

        $this->req($user, $shop, 'GET', '/api/v1/activity')->assertOk();
        $this->req($user, $shop, 'GET', '/api/v1/discord/settings')->assertOk();
        $this->req($user, $shop, 'GET', '/api/v1/export/inventory.csv')->assertOk();
        $this->req($user, $shop, 'GET', '/api/v1/export/sales.csv')->assertForbidden();

        $this->req($user, $shop, 'GET', '/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('summary.profit_this_month', null)
            ->assertJsonPath('summary.storefront_views', null);
    }

    public function test_a_growth_shop_unlocks_discord_export_and_analytics(): void
    {
        [$user, $shop] = $this->ownerOn('growth');

        $this->req($user, $shop, 'GET', '/api/v1/activity')->assertOk();
        $this->req($user, $shop, 'GET', '/api/v1/discord/settings')->assertOk();
        $this->req($user, $shop, 'GET', '/api/v1/export/inventory.csv')->assertOk();

        $dashboard = $this->req($user, $shop, 'GET', '/api/v1/dashboard')->assertOk();
        $dashboard->assertJsonPath('subscription.effective_plan.code', 'growth')
            ->assertJsonPath('subscription.effective_plan.features.discord', true);
        // analytics unlocked → profit + storefront views are real numbers, not null
        $this->assertNotNull($dashboard->json('summary.profit_this_month'));
        $this->assertSame(0, $dashboard->json('summary.storefront_views'));
        $this->assertIsArray($dashboard->json('subscription.usage'));
    }
}
