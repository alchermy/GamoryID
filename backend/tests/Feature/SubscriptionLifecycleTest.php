<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Jobs\SendDiscordShopNotification;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\SubscriptionExpiringNotification;
use App\Services\SubscriptionLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_trial_five_days_from_expiry_sends_the_first_reminder(): void
    {
        Notification::fake();
        Queue::fake();
        [$shop, $subscription, $owner] = $this->trialShop(now()->addDays(5));

        app(SubscriptionLifecycle::class)->run();

        $this->assertSame(1, $subscription->fresh()->expiry_reminder_stage);
        Notification::assertSentTo($owner, SubscriptionExpiringNotification::class);
        Queue::assertPushed(SendDiscordShopNotification::class, fn (SendDiscordShopNotification $job) => $job->shopId === $shop->id && $job->purpose === 'system');
    }

    public function test_running_again_at_the_same_stage_does_not_resend(): void
    {
        Notification::fake();
        [$shop, $subscription, $owner] = $this->trialShop(now()->addDays(5));

        app(SubscriptionLifecycle::class)->run();
        app(SubscriptionLifecycle::class)->run();

        Notification::assertSentToTimes($owner, SubscriptionExpiringNotification::class, 1);
    }

    public function test_moving_closer_to_expiry_sends_the_next_stage(): void
    {
        Notification::fake();
        [$shop, $subscription, $owner] = $this->trialShop(now()->addDays(5));
        app(SubscriptionLifecycle::class)->run();

        $shop->update(['trial_ends_at' => now()->addDays(2)]);
        app(SubscriptionLifecycle::class)->run();

        $this->assertSame(2, $subscription->fresh()->expiry_reminder_stage);
        Notification::assertSentToTimes($owner, SubscriptionExpiringNotification::class, 2);
    }

    public function test_an_active_subscription_close_to_ends_at_is_reminded(): void
    {
        Notification::fake();
        [$shop, $owner] = $this->owner('active-owner@example.test', 'ร้านชำระแล้ว');
        $plan = $this->plan();
        $subscription = Subscription::create([
            'shop_id' => $shop->id,
            'subscription_plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subDays(24),
            'ends_at' => now()->addDays(6),
            'grace_ends_at' => now()->addDays(20),
        ]);
        $shop->update(['status' => SubscriptionStatus::Active]);

        app(SubscriptionLifecycle::class)->run();

        $this->assertSame(1, $subscription->fresh()->expiry_reminder_stage);
        Notification::assertSentTo($owner, SubscriptionExpiringNotification::class);
    }

    public function test_a_member_with_billing_permission_is_also_reminded(): void
    {
        Notification::fake();
        [$shop, $subscription, $owner] = $this->trialShop(now()->addDays(5));
        $billingStaff = User::create(['name' => 'พนักงานบัญชี', 'email' => 'billing-staff@example.test', 'password' => 'password', 'email_verified_at' => now()]);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $billingStaff->id, 'role' => 'staff', 'permissions' => ['billing.manage'], 'joined_at' => now()]);
        $otherStaff = User::create(['name' => 'พนักงานขาย', 'email' => 'sales-staff@example.test', 'password' => 'password', 'email_verified_at' => now()]);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $otherStaff->id, 'role' => 'staff', 'permissions' => ['inventory.sell'], 'joined_at' => now()]);

        app(SubscriptionLifecycle::class)->run();

        Notification::assertSentTo($billingStaff, SubscriptionExpiringNotification::class);
        Notification::assertNotSentTo($otherStaff, SubscriptionExpiringNotification::class);
    }

    public function test_a_shop_without_discord_still_receives_the_email_reminder(): void
    {
        Notification::fake();
        [$shop, $subscription, $owner] = $this->trialShop(now()->addDays(1));

        app(SubscriptionLifecycle::class)->run();

        Notification::assertSentTo($owner, SubscriptionExpiringNotification::class);
        $this->assertSame(3, $subscription->fresh()->expiry_reminder_stage);
    }

    /**
     * @return array{0: Shop, 1: Subscription, 2: User}
     */
    private function trialShop(\DateTimeInterface $trialEndsAt): array
    {
        [$shop, $owner] = $this->owner('trial-owner@example.test', 'ร้านทดลองใช้');
        $shop->update(['trial_ends_at' => $trialEndsAt]);
        $plan = $this->plan();
        $subscription = Subscription::create([
            'shop_id' => $shop->id,
            'subscription_plan_id' => $plan->id,
            'status' => SubscriptionStatus::Trialing,
            'starts_at' => now()->subDays(25),
            'ends_at' => $trialEndsAt,
            'grace_ends_at' => now()->addDays(30),
        ]);

        return [$shop, $subscription, $owner];
    }

    /**
     * @return array{0: Shop, 1: User}
     */
    private function owner(string $email, string $name): array
    {
        $shop = Shop::create(['name' => $name, 'slug' => str($name)->slug().'-'.uniqid(), 'status' => 'trialing', 'trial_ends_at' => now()->addMonth()]);
        $user = User::create(['name' => 'เจ้าของร้าน', 'email' => $email, 'password' => 'password', 'current_shop_id' => $shop->id, 'email_verified_at' => now()]);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $user->id, 'role' => 'owner', 'permissions' => [], 'joined_at' => now()]);

        return [$shop, $user];
    }

    private function plan(): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Starter',
            'code' => 'starter-'.uniqid(),
            'active_inventory_limit' => 1000,
            'member_limit' => 3,
            'price_thb' => 299,
            'duration_days' => 30,
            'is_active' => true,
        ]);
    }
}
