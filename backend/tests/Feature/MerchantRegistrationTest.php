<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\ShopMember;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MerchantRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_registration_creates_an_owner_shop_and_growth_trial(): void
    {
        Notification::fake();
        // Plans are provisioned by the default-plans data migration; the trial
        // mirrors the Growth tier so new owners get the full feature set.
        $trialPlan = SubscriptionPlan::query()->where('code', 'growth')->firstOrFail();

        $response = $this
            ->withHeaders([
                'Origin' => 'http://localhost:5173',
                'Referer' => 'http://localhost:5173/register',
            ])
            ->postJson('/api/v1/auth/register', [
                'name' => 'พีท เจ้าของร้าน',
                'shop_name' => 'ร้านเริ่มใหม่',
                'email' => 'new-owner@example.test',
                'password' => 'strong-pass-123',
                'password_confirmation' => 'strong-pass-123',
                'accept_terms' => true,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.email', 'new-owner@example.test')
            ->assertJsonPath('user.email_verified_at', null)
            ->assertJsonPath('user.terms_current', true)
            ->assertJsonPath('shop.name', 'ร้านเริ่มใหม่');

        $user = User::query()->where('email', 'new-owner@example.test')->firstOrFail();
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertSame(config('legal.terms_version'), $user->terms_version);
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('shops', [
            'id' => $user->current_shop_id,
            'status' => SubscriptionStatus::Trialing->value,
            'onboarding_dismissed_at' => null,
        ]);
        $this->assertDatabaseHas('shop_members', [
            'shop_id' => $user->current_shop_id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'shop_id' => $user->current_shop_id,
            'subscription_plan_id' => $trialPlan->id,
            'status' => SubscriptionStatus::Trialing->value,
        ]);
        $this->assertSame([], ShopMember::query()->where('user_id', $user->id)->firstOrFail()->permissions);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_unverified_merchant_cannot_reach_the_app(): void
    {
        $user = User::create([
            'name' => 'ผู้ใช้รอยืนยัน',
            'email' => 'unverified@example.test',
            'password' => 'strong-pass-123',
        ]);

        $this->actingAs($user)->getJson('/api/v1/dashboard')->assertForbidden();
    }

    public function test_unverified_merchant_can_request_another_verification_email(): void
    {
        Notification::fake();
        $user = User::create([
            'name' => 'ผู้ใช้รอยืนยัน',
            'email' => 'resend@example.test',
            'password' => 'strong-pass-123',
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/email/verification-notification')
            ->assertOk();

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_registration_explains_the_ten_character_password_rule_in_thai(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'พีท',
            'shop_name' => 'ร้านทดสอบ',
            'email' => 'short-password@example.test',
            'password' => '123456789',
            'password_confirmation' => '123456789',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password'])
            ->assertJsonPath('errors.password.0', 'รหัสผ่านต้องมีอย่างน้อย 10 ตัวอักษร');
    }

    public function test_registration_requires_accepting_the_terms(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'พีท',
            'shop_name' => 'ร้านทดสอบ',
            'email' => 'no-consent@example.test',
            'password' => 'strong-pass-123',
            'password_confirmation' => 'strong-pass-123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['accept_terms']);

        $this->assertDatabaseMissing('users', ['email' => 'no-consent@example.test']);
    }

    public function test_verified_email_link_works_without_an_authenticated_session(): void
    {
        $user = User::create([
            'name' => 'ผู้ใช้รอยืนยัน',
            'email' => 'pending-verification@example.test',
            'password' => 'strong-pass-123',
        ]);
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        // No actingAs(): the link is opened straight from an email, in a browser
        // that has no session for this user.
        $this->get($verificationUrl)
            ->assertRedirect('http://localhost:5173/verify-email?verified=1');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_link_with_a_wrong_hash_is_rejected(): void
    {
        $user = User::create([
            'name' => 'ผู้ใช้รอยืนยัน',
            'email' => 'wrong-hash@example.test',
            'password' => 'strong-pass-123',
        ]);
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('someone-else@example.test')],
        );

        $this->get($verificationUrl)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
