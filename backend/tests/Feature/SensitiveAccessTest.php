<?php

namespace Tests\Feature;

use App\Jobs\SendDiscordShopNotification;
use App\Models\InventoryCredential;
use App\Models\InventoryItem;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use App\Services\CredentialCipher;
use App\Services\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SensitiveAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_revealing_credentials_without_a_fresh_reauth_is_blocked(): void
    {
        [$user, $shop, $item] = $this->shopWithSecretItem();

        $this->acting($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson("/api/v1/inventory/{$item->id}/credentials")
            ->assertStatus(428)
            ->assertJsonPath('code', 'SENSITIVE_REAUTH_REQUIRED');
    }

    public function test_password_only_reauth_unlocks_the_reveal_when_2fa_is_off(): void
    {
        Queue::fake();
        [$user, $shop, $item] = $this->shopWithSecretItem();

        $this->acting($user)
            ->postJson('/api/v1/security/reauth', ['password' => 'password'])
            ->assertOk()
            ->assertJsonPath('valid_for_seconds', config('credentials.reauth_minutes') * 60);

        $this->acting($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson("/api/v1/inventory/{$item->id}/credentials")
            ->assertOk()
            ->assertJsonPath('data.username', 'acc.login')
            ->assertJsonPath('data.password', 'the-secret-pw')
            ->assertJsonPath('data.recovery_email', 'rescue@example.test');

        // Discord is told that a password was viewed — but never the password itself.
        Queue::assertPushed(
            SendDiscordShopNotification::class,
            fn (SendDiscordShopNotification $job) => $job->shopId === $shop->id
                && $job->purpose === 'system'
                && $job->title === 'มีการเปิดดูรหัสผ่านไอดี'
                && str_contains($job->description, '#SEC01')
                && str_contains($job->description, 'เจ้าของร้าน')
                && $job->actor === 'เจ้าของร้าน'
                && ! str_contains($job->description, 'the-secret-pw'),
        );
    }

    public function test_a_wrong_password_at_reauth_is_rejected(): void
    {
        [$user] = $this->shopWithSecretItem();

        $this->acting($user)
            ->postJson('/api/v1/security/reauth', ['password' => 'not-it'])
            ->assertStatus(422);
    }

    public function test_stale_reauth_no_longer_counts(): void
    {
        [$user, $shop, $item] = $this->shopWithSecretItem();

        $this->acting($user)
            ->withSession(['auth.password_confirmed_at' => now()->subMinutes(45)->timestamp])
            ->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson("/api/v1/inventory/{$item->id}/credentials")
            ->assertStatus(428);
    }

    public function test_enabling_2fa_requires_the_account_password_then_a_valid_code(): void
    {
        [$user] = $this->shopWithSecretItem();

        $this->acting($user)
            ->postJson('/api/v1/security/2fa/begin', ['password' => 'wrong'])
            ->assertStatus(422);

        $begin = $this->acting($user)
            ->postJson('/api/v1/security/2fa/begin', ['password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['secret', 'otpauth_uri']);
        $secret = $begin->json('secret');
        $this->assertNull($user->fresh()->two_factor_confirmed_at);

        $this->acting($user)
            ->postJson('/api/v1/security/2fa/confirm', ['code' => '000000'])
            ->assertStatus(422);

        $confirm = $this->acting($user)
            ->postJson('/api/v1/security/2fa/confirm', ['code' => app(Totp::class)->currentCode($secret)])
            ->assertOk()
            ->assertJsonCount(8, 'recovery_codes');
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->assertSame($confirm->json('recovery_codes'), $user->fresh()->two_factor_recovery_codes);
    }

    public function test_a_recovery_code_can_stand_in_for_the_authenticator_and_is_consumed(): void
    {
        [$user, $shop, $item] = $this->shopWithSecretItem();
        $this->enableTwoFactor($user);

        // a wrong account password must not burn a recovery code
        $this->acting($user)
            ->postJson('/api/v1/security/reauth', ['password' => 'nope', 'recovery_code' => 'aaaaa-11111'])
            ->assertStatus(422);
        $this->assertCount(3, $user->fresh()->two_factor_recovery_codes);

        // right password + a recovery code → unlocks, and that code is spent
        $this->acting($user)
            ->postJson('/api/v1/security/reauth', ['password' => 'password', 'recovery_code' => 'AAAAA-11111'])
            ->assertOk();
        $this->assertSame(['bbbbb-22222', 'ccccc-33333'], array_values($user->fresh()->two_factor_recovery_codes));

        $this->acting($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson("/api/v1/inventory/{$item->id}/credentials")->assertOk();

        // the spent code no longer works
        $this->acting($user)
            ->postJson('/api/v1/security/reauth', ['password' => 'password', 'recovery_code' => 'aaaaa-11111'])
            ->assertStatus(422);
    }

    public function test_recovery_codes_can_be_regenerated_and_old_ones_stop_working(): void
    {
        [$user] = $this->shopWithSecretItem();
        $secret = $this->enableTwoFactor($user);

        $this->acting($user)
            ->postJson('/api/v1/security/2fa/recovery-codes', ['password' => 'password'])
            ->assertStatus(422); // needs a second factor

        $fresh = $this->acting($user)
            ->postJson('/api/v1/security/2fa/recovery-codes', [
                'password' => 'password',
                'code' => app(Totp::class)->currentCode($secret),
            ])
            ->assertOk()
            ->assertJsonCount(8, 'recovery_codes')
            ->json('recovery_codes');

        $this->assertSame($fresh, $user->fresh()->two_factor_recovery_codes);
        $this->assertNotContains('aaaaa-11111', $fresh);

        $this->acting($user)
            ->postJson('/api/v1/security/reauth', ['password' => 'password', 'recovery_code' => 'aaaaa-11111'])
            ->assertStatus(422);
        $this->acting($user)
            ->postJson('/api/v1/security/reauth', ['password' => 'password', 'recovery_code' => $fresh[0]])
            ->assertOk();
    }

    public function test_with_2fa_on_the_reveal_needs_password_and_code(): void
    {
        [$user, $shop, $item] = $this->shopWithSecretItem();
        $secret = $this->enableTwoFactor($user);

        // password alone is not enough anymore
        $this->acting($user)
            ->postJson('/api/v1/security/reauth', ['password' => 'password'])
            ->assertStatus(422);

        $this->acting($user)
            ->postJson('/api/v1/security/reauth', ['password' => 'password', 'code' => 'zzzzzz'])
            ->assertStatus(422);

        $this->acting($user)
            ->postJson('/api/v1/security/reauth', [
                'password' => 'password',
                'code' => app(Totp::class)->currentCode($secret),
            ])
            ->assertOk();

        $this->acting($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson("/api/v1/inventory/{$item->id}/credentials")
            ->assertOk()
            ->assertJsonPath('data.password', 'the-secret-pw');
    }

    public function test_disabling_2fa_needs_a_second_factor_and_clears_everything(): void
    {
        [$user] = $this->shopWithSecretItem();
        $this->enableTwoFactor($user);

        $this->acting($user)
            ->postJson('/api/v1/security/2fa/disable', ['password' => 'password', 'code' => '111111'])
            ->assertStatus(422);

        // a recovery code works here too
        $this->acting($user)
            ->postJson('/api/v1/security/2fa/disable', ['password' => 'password', 'recovery_code' => 'ccccc-33333'])
            ->assertOk();

        $fresh = $user->fresh();
        $this->assertNull($fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_confirmed_at);
        $this->assertNull($fresh->two_factor_recovery_codes);
    }

    public function test_staff_without_the_reveal_permission_gets_403(): void
    {
        [, $shop, $item] = $this->shopWithSecretItem();
        $staff = User::create([
            'name' => 'พนักงาน', 'email' => 'staff-'.uniqid().'@example.test',
            'password' => 'password', 'current_shop_id' => $shop->id, 'email_verified_at' => now(),
        ]);
        ShopMember::create([
            'shop_id' => $shop->id, 'user_id' => $staff->id, 'role' => 'staff',
            'permissions' => ['inventory.sell'], 'joined_at' => now(),
        ]);

        $this->acting($staff)
            ->postJson('/api/v1/security/reauth', ['password' => 'password'])
            ->assertOk();

        $this->acting($staff)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson("/api/v1/inventory/{$item->id}/credentials")
            ->assertForbidden();
    }

    /** @return array{User, Shop, InventoryItem} */
    private function shopWithSecretItem(): array
    {
        $shop = Shop::create([
            'name' => 'ร้านลับ', 'slug' => 'secret-'.uniqid(),
            'status' => 'trialing', 'trial_ends_at' => now()->addMonth(),
        ]);
        $user = User::create([
            'name' => 'เจ้าของร้าน', 'email' => 'owner-'.uniqid().'@example.test',
            'password' => 'password', 'current_shop_id' => $shop->id, 'email_verified_at' => now(),
        ]);
        ShopMember::create([
            'shop_id' => $shop->id, 'user_id' => $user->id, 'role' => 'owner',
            'permissions' => [], 'joined_at' => now(),
        ]);
        $item = InventoryItem::create([
            'shop_id' => $shop->id, 'tag' => 'SEC01', 'title' => 'ไอดีลับ',
            'username' => 'acc.login', 'cost' => 1000, 'list_price' => 2000, 'status' => 'available',
        ]);
        $encrypted = app(CredentialCipher::class)->encrypt([
            'username' => 'acc.login',
            'password' => 'the-secret-pw',
            'recovery_email' => 'rescue@example.test',
        ]);
        InventoryCredential::create([
            'inventory_item_id' => $item->id,
            'encrypted_payload' => $encrypted['payload'],
            'key_version' => $encrypted['key_version'],
        ]);

        return [$user, $shop, $item];
    }

    /**
     * Act as $user over a "stateful" request so the session middleware runs
     * (the /security/* endpoints read and write the session).
     */
    private function acting(User $user): self
    {
        return $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/account',
        ])->actingAs($user);
    }

    /** Fixed recovery codes seeded by enableTwoFactor(), for assertions. */
    private const RECOVERY_CODES = ['aaaaa-11111', 'bbbbb-22222', 'ccccc-33333'];

    private function enableTwoFactor(User $user): string
    {
        $secret = app(Totp::class)->generateSecret();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => self::RECOVERY_CODES,
        ])->save();

        return $secret;
    }
}
