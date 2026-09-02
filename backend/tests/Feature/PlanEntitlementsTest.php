<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\PlanEntitlements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PlanEntitlementsTest extends TestCase
{
    use RefreshDatabase;

    private function shop(string $status = 'active', ?string $planCode = null, ?\DateTimeInterface $trialEndsAt = null): Shop
    {
        $shop = Shop::create([
            'name' => 'ร้าน '.uniqid(),
            'slug' => 'ent-'.uniqid(),
            'status' => $status,
            'trial_ends_at' => $trialEndsAt,
        ]);
        $user = User::create(['name' => 'เจ้าของ', 'email' => uniqid().'@example.test', 'password' => 'password', 'current_shop_id' => $shop->id]);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $user->id, 'role' => 'owner', 'permissions' => [], 'joined_at' => now()]);
        if ($planCode) {
            $plan = SubscriptionPlan::query()->where('code', $planCode)->firstOrFail();
            Subscription::create(['shop_id' => $shop->id, 'subscription_plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addDays(30)]);
        }

        return $shop;
    }

    public function test_a_shop_with_no_subscription_falls_back_to_free(): void
    {
        $ent = app(PlanEntitlements::class);
        $shop = $this->shop('active');

        $this->assertSame('free', $ent->effectivePlan($shop)->code);
        $this->assertSame(50, $ent->inventoryLimit($shop));
        $this->assertSame(1, $ent->memberLimit($shop));
        $this->assertFalse($ent->can($shop, 'discord'));
        $this->assertFalse($ent->can($shop, 'activity_log'));
    }

    public function test_a_trialing_shop_without_a_subscription_row_gets_the_trial_tier(): void
    {
        $ent = app(PlanEntitlements::class);
        $shop = $this->shop('trialing', null, now()->addDays(10));

        $this->assertSame('growth', $ent->effectivePlan($shop)->code);
        $this->assertTrue($ent->can($shop, 'discord'));
        $this->assertTrue($ent->can($shop, 'analytics'));
    }

    public function test_the_pro_plan_reports_unlimited_members(): void
    {
        $ent = app(PlanEntitlements::class);
        $shop = $this->shop('active', 'pro');

        $this->assertNull($ent->memberLimit($shop));
        // Unlimited: adding many members never aborts.
        for ($i = 0; $i < 20; $i++) {
            ShopMember::create(['shop_id' => $shop->id, 'user_id' => User::create(['name' => 'x', 'email' => uniqid().'@e.test', 'password' => 'password'])->id, 'role' => 'staff', 'permissions' => [], 'joined_at' => now()]);
        }
        $ent->ensureMemberCapacity($shop); // no exception
        $this->assertTrue(true);
    }

    public function test_ensure_feature_aborts_403_when_the_plan_lacks_it(): void
    {
        $ent = app(PlanEntitlements::class);
        $shop = $this->shop('active', 'starter');

        $this->assertTrue($ent->can($shop, 'activity_log'));

        try {
            $ent->ensureFeature($shop, 'discord');
            $this->fail('expected a 403');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_ensure_inventory_capacity_aborts_422_over_the_limit(): void
    {
        $ent = app(PlanEntitlements::class);
        $shop = $this->shop('active'); // free → limit 50

        for ($i = 0; $i < 50; $i++) {
            InventoryItem::create(['shop_id' => $shop->id, 'tag' => str_pad((string) $i, 5, 'A', STR_PAD_LEFT), 'title' => 't', 'cost' => 0, 'list_price' => 0, 'status' => 'available']);
        }

        try {
            $ent->ensureInventoryCapacity($shop);
            $this->fail('expected a 422');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        } catch (HttpResponseException $e) {
            $this->assertSame(422, $e->getResponse()->getStatusCode());
        }
    }

    public function test_summary_exposes_features_limits_and_usage(): void
    {
        $ent = app(PlanEntitlements::class);
        $shop = $this->shop('active', 'growth');
        InventoryItem::create(['shop_id' => $shop->id, 'tag' => 'SUM01', 'title' => 't', 'cost' => 0, 'list_price' => 0, 'status' => 'available']);

        $summary = $ent->summary($shop);

        $this->assertSame('growth', $summary['effective_plan']['code']);
        $this->assertTrue($summary['effective_plan']['features']['discord']);
        $this->assertFalse($summary['effective_plan']['features']['priority_support']);
        $this->assertSame(5000, $summary['effective_plan']['active_inventory_limit']);
        $this->assertSame(1, $summary['usage']['inventory_active']);
    }
}
