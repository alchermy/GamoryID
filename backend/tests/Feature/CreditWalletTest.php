<?php

namespace Tests\Feature;

use App\Jobs\VerifyPaymentSlip;
use App\Models\PaymentSubmission;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\CreditWallet;
use App\Services\SlipVerifier;
use App\Services\SubscriptionLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreditWalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_purchase_debits_credits_once_with_an_idempotency_key(): void
    {
        [$user, $shop] = $this->owner();
        $shop->update(['credit_balance' => 500]);
        $plan = $this->plan(299);
        $key = (string) Str::uuid();

        $response = $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/subscriptions/purchase', ['plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'auto_renew' => true]);

        $response->assertOk()
            ->assertJsonPath('data.credit_balance', 201)
            ->assertJsonPath('data.subscription.auto_renew', true)
            ->assertJsonPath('data.subscription.plan.code', 'starter');
        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/subscriptions/purchase', ['plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'auto_renew' => true])
            ->assertOk()->assertJsonPath('data.credit_balance', 201);

        $this->assertDatabaseHas('shops', ['id' => $shop->id, 'credit_balance' => 201, 'status' => 'active']);
        $this->assertDatabaseCount('credit_transactions', 1);
        $this->assertDatabaseHas('credit_transactions', ['shop_id' => $shop->id, 'credits' => -299, 'balance_after' => 201]);
    }

    public function test_package_purchase_cannot_spend_more_credits_than_available(): void
    {
        [$user, $shop] = $this->owner();
        $shop->update(['credit_balance' => 100]);
        $plan = $this->plan(299);

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/subscriptions/purchase', ['plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'auto_renew' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('credits');

        $this->assertDatabaseHas('shops', ['id' => $shop->id, 'credit_balance' => 100]);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_verified_top_up_creates_a_positive_ledger_entry_only_once(): void
    {
        [, $shop] = $this->owner();
        $payment = PaymentSubmission::create([
            'shop_id' => $shop->id,
            'status' => 'pending',
            'expected_amount' => 750,
            'credit_amount' => 750,
            'slip_path' => 'slips/test.png',
        ]);
        $wallet = app(CreditWallet::class);

        $wallet->approveTopUp($payment);
        $wallet->approveTopUp($payment->fresh());

        $this->assertDatabaseHas('shops', ['id' => $shop->id, 'credit_balance' => 750]);
        $this->assertDatabaseHas('payment_submissions', ['id' => $payment->id, 'status' => 'verified']);
        $this->assertDatabaseCount('credit_transactions', 1);
        $this->assertDatabaseHas('credit_transactions', ['payment_submission_id' => $payment->id, 'credits' => 750, 'balance_after' => 750]);
    }

    public function test_local_slip_bypass_sends_a_top_up_to_admin_review_without_crediting_the_shop(): void
    {
        [, $shop] = $this->owner();
        Storage::fake('private');
        Storage::disk('private')->put('slips/credit.png', 'test-slip');
        config()->set('services.slipok.test_bypass', true);
        $payment = PaymentSubmission::create([
            'shop_id' => $shop->id,
            'status' => 'pending',
            'expected_amount' => 120,
            'credit_amount' => 120,
            'slip_disk' => 'private',
            'slip_path' => 'slips/credit.png',
        ]);

        (new VerifyPaymentSlip($payment->id))->handle(app(SlipVerifier::class));

        $this->assertDatabaseHas('payment_submissions', ['id' => $payment->id, 'status' => 'pending_review']);
        $this->assertDatabaseHas('shops', ['id' => $shop->id, 'credit_balance' => 0]);
        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_auto_renew_preference_is_limited_to_the_current_shops_active_subscription(): void
    {
        [$user, $shop] = $this->owner();
        $plan = $this->plan(299);
        $subscription = $shop->subscriptions()->create(['subscription_plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addDays(30)]);

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->putJson('/api/v1/subscriptions/auto-renew', ['auto_renew' => true])
            ->assertOk()->assertJsonPath('data.id', $subscription->id)->assertJsonPath('data.auto_renew', true);

        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id, 'auto_renew' => true]);
    }

    public function test_merchant_transaction_history_is_scoped_to_the_current_shop(): void
    {
        [$user, $shop] = $this->owner();
        [, $otherShop] = $this->owner();
        $plan = $this->plan(299);

        $shop->subscriptions()->create([
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'auto_renew' => true,
        ]);
        $otherShop->subscriptions()->create([
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);
        PaymentSubmission::create([
            'shop_id' => $shop->id,
            'submitted_by' => $user->id,
            'status' => 'verified',
            'expected_amount' => 500,
            'credit_amount' => 500,
            'slip_path' => 'slips/shop.png',
            'verified_at' => now(),
        ]);
        PaymentSubmission::create([
            'shop_id' => $otherShop->id,
            'status' => 'pending_review',
            'expected_amount' => 900,
            'credit_amount' => 900,
            'slip_path' => 'slips/other-shop.png',
        ]);

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/billing/history')
            ->assertOk()
            ->assertJsonPath('data.subscriptions.total', 1)
            ->assertJsonPath('data.subscriptions.items.0.plan.name', 'Starter')
            ->assertJsonPath('data.subscriptions.items.0.auto_renew', true)
            ->assertJsonPath('data.top_ups.total', 1)
            ->assertJsonPath('data.top_ups.items.0.credits', 500)
            ->assertJsonPath('data.top_ups.items.0.submitted_by.name', 'เจ้าของร้าน')
            ->assertJsonMissing(['credits' => 900]);
    }

    public function test_due_auto_renewal_debits_credit_and_creates_the_next_subscription(): void
    {
        [, $shop] = $this->owner();
        $shop->update(['status' => 'active', 'credit_balance' => 299]);
        $plan = $this->plan(299);
        $expired = $shop->subscriptions()->create([
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subMinute(),
            'auto_renew' => true,
        ]);

        app(SubscriptionLifecycle::class)->run();

        $this->assertDatabaseHas('shops', ['id' => $shop->id, 'credit_balance' => 0, 'status' => 'active']);
        $this->assertDatabaseHas('subscriptions', ['id' => $expired->id, 'status' => 'expired']);
        $this->assertDatabaseHas('credit_transactions', ['shop_id' => $shop->id, 'type' => 'subscription_renewal', 'credits' => -299]);
        $this->assertDatabaseCount('subscriptions', 2);
    }

    public function test_yearly_purchase_charges_the_yearly_price_and_sets_a_365_day_period(): void
    {
        [$user, $shop] = $this->owner();
        $shop->update(['credit_balance' => 5000]);
        $plan = $this->plan(299); // yearly = 2990

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/subscriptions/purchase', ['plan_id' => $plan->id, 'billing_cycle' => 'yearly', 'auto_renew' => false])
            ->assertOk()
            ->assertJsonPath('data.credit_balance', 2010)
            ->assertJsonPath('data.subscription.billing_cycle', 'yearly')
            ->assertJsonPath('data.subscription.price_paid', 2990);

        $sub = $shop->subscriptions()->latest('id')->first();
        $this->assertSame(365, (int) $sub->starts_at->diffInDays($sub->ends_at));
        $this->assertDatabaseHas('credit_transactions', ['shop_id' => $shop->id, 'credits' => -2990]);
    }

    public function test_a_running_sale_price_is_charged_instead_of_the_list_price(): void
    {
        [$user, $shop] = $this->owner();
        $shop->update(['credit_balance' => 500]);
        $plan = $this->plan(299);
        $plan->update(['sale_price_monthly' => 149, 'sale_label' => 'โปรเปิดตัว', 'sale_ends_at' => now()->addDays(7)]);

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/subscriptions/purchase', ['plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'auto_renew' => false])
            ->assertOk()
            ->assertJsonPath('data.credit_balance', 351)
            ->assertJsonPath('data.subscription.price_paid', 149);
    }

    public function test_an_expired_sale_falls_back_to_the_list_price(): void
    {
        [$user, $shop] = $this->owner();
        $shop->update(['credit_balance' => 500]);
        $plan = $this->plan(299);
        $plan->update(['sale_price_monthly' => 149, 'sale_ends_at' => now()->subDay()]);

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/subscriptions/purchase', ['plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'auto_renew' => false])
            ->assertOk()
            ->assertJsonPath('data.subscription.price_paid', 299);
    }

    public function test_the_free_plan_cannot_be_purchased(): void
    {
        [$user, $shop] = $this->owner();
        $shop->update(['credit_balance' => 500]);
        $free = SubscriptionPlan::query()->where('code', 'free')->firstOrFail();

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/subscriptions/purchase', ['plan_id' => $free->id, 'billing_cycle' => 'monthly', 'auto_renew' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('plan_id');
    }

    private function owner(): array
    {
        $shop = Shop::create(['name' => 'ร้านเครดิต', 'slug' => 'credit-'.uniqid(), 'status' => 'trialing', 'trial_ends_at' => now()->addMonth()]);
        $user = User::create(['name' => 'เจ้าของร้าน', 'email' => 'credit-'.uniqid().'@example.test', 'password' => 'password', 'current_shop_id' => $shop->id, 'email_verified_at' => now()]);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $user->id, 'role' => 'owner', 'permissions' => [], 'joined_at' => now()]);

        return [$user, $shop];
    }

    private function plan(int $price): SubscriptionPlan
    {
        return SubscriptionPlan::updateOrCreate(
            ['code' => 'starter'],
            [
                'name' => 'Starter', 'active_inventory_limit' => 100, 'member_limit' => 2,
                'price_monthly' => $price, 'price_yearly' => $price * 10,
                'sale_price_monthly' => null, 'sale_price_yearly' => null,
                'sale_label' => null, 'sale_ends_at' => null,
                'monthly_days' => 30, 'yearly_days' => 365, 'is_active' => true,
            ],
        );
    }
}
