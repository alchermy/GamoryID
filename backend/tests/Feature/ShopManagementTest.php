<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ShopManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_read_and_update_shop_settings(): void
    {
        [$user, $shop] = $this->owner('settings@example.test', 'ร้านตั้งค่า');

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/shop')->assertOk()->assertJsonPath('data.name', 'ร้านตั้งค่า');

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->putJson('/api/v1/shop', [
                'name' => 'ร้านใหม่', 'slug' => 'shop-new', 'description' => 'ร้านไอดี TH',
                'facebook_url' => 'https://facebook.com/gamoryid', 'line_url' => 'https://line.me/ti/p/@gamory', 'phone' => '0812345678',
                'inventory_copy_footer' => 'สอบถามเพิ่มเติมทาง LINE รับประกัน 7 วัน',
            ])->assertOk()->assertJsonPath('data.name', 'ร้านใหม่')
            ->assertJsonPath('data.line_url', 'https://line.me/ti/p/@gamory')
            ->assertJsonPath('data.inventory_copy_footer', 'สอบถามเพิ่มเติมทาง LINE รับประกัน 7 วัน');

        $this->assertDatabaseHas('shops', [
            'id' => $shop->id,
            'inventory_copy_footer' => 'สอบถามเพิ่มเติมทาง LINE รับประกัน 7 วัน',
        ]);
    }

    public function test_shop_settings_cannot_cross_tenant_boundary(): void
    {
        [$user, $shopA] = $this->owner('settings-a@example.test', 'ร้าน A');
        [, $shopB] = $this->owner('settings-b@example.test', 'ร้าน B');

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shopB->id)
            ->getJson('/api/v1/shop')->assertNotFound();
        $this->assertDatabaseHas('shops', ['id' => $shopA->id, 'name' => 'ร้าน A']);
    }

    public function test_owner_can_create_edit_reset_password_and_remove_staff(): void
    {
        [$user, $shop] = $this->owner('team@example.test', 'ร้านทีม');

        $create = $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson('/api/v1/team', [
                'name' => 'พนักงานหนึ่ง',
                'email' => 'staff@example.test',
                'password' => 'staff-password-1',
                'password_confirmation' => 'staff-password-1',
                'permissions' => ['inventory.manage', 'inventory.sell'],
            ]);
        $create->assertCreated()->assertJsonPath('data.user.email', 'staff@example.test');
        $this->assertDatabaseHas('users', ['email' => 'staff@example.test']);
        $staffUser = User::query()->where('email', 'staff@example.test')->firstOrFail();
        $this->assertNotNull($staffUser->email_verified_at);

        $memberId = ShopMember::query()->where('shop_id', $shop->id)->where('role', 'staff')->value('id');
        $this->assertNotNull($memberId);

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->putJson('/api/v1/team/'.$memberId, ['name' => 'พนักงานหนึ่ง (แก้ไข)', 'permissions' => ['inventory.sell']])
            ->assertOk()->assertJsonPath('data.permissions.0', 'inventory.sell');
        $this->assertDatabaseHas('users', ['id' => $staffUser->id, 'name' => 'พนักงานหนึ่ง (แก้ไข)']);

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->putJson('/api/v1/team/'.$memberId.'/password', ['password' => 'staff-password-2', 'password_confirmation' => 'staff-password-2'])
            ->assertOk();
        $this->assertTrue(Hash::check('staff-password-2', $staffUser->fresh()->password));
        $this->assertFalse(Hash::check('staff-password-1', $staffUser->fresh()->password));

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson('/api/v1/team', ['name' => 'ซ้ำ', 'email' => 'staff@example.test', 'password' => 'another-password', 'password_confirmation' => 'another-password'])
            ->assertUnprocessable()->assertJsonValidationErrors(['email']);

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->deleteJson('/api/v1/team/'.$memberId)->assertOk();

        $this->assertDatabaseMissing('shop_members', ['id' => $memberId]);
        $this->assertNotNull(ActivityLog::query()->where('shop_id', $shop->id)->where('event', 'team.member_created')->value('user_id'));
    }

    public function test_a_newly_created_staff_member_can_log_in_with_the_owner_set_password(): void
    {
        [$user, $shop] = $this->owner('login-team@example.test', 'ร้านทดสอบล็อกอิน');

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson('/api/v1/team', [
                'name' => 'พนักงานเข้าสู่ระบบ',
                'email' => 'login-staff@example.test',
                'password' => 'a-strong-password',
                'password_confirmation' => 'a-strong-password',
                'permissions' => ['inventory.sell'],
            ])
            ->assertCreated();

        $this->stateful()->postJson('/api/v1/auth/login', [
            'email' => 'login-staff@example.test',
            'password' => 'a-strong-password',
        ])->assertOk()->assertJsonPath('user.email', 'login-staff@example.test');
    }

    public function test_the_team_seat_limit_blocks_creating_more_staff_than_the_plan_allows(): void
    {
        [$user, $shop] = $this->owner('seats@example.test', 'ร้านที่นั่งเต็ม');
        // Pin the shop to an active plan capped at 3 total members so the trial
        // fallback (Growth-tier) does not apply.
        $plan = SubscriptionPlan::updateOrCreate(
            ['code' => 'starter'],
            ['name' => 'Starter', 'active_inventory_limit' => 1000, 'member_limit' => 3, 'price_monthly' => 299, 'monthly_days' => 30, 'yearly_days' => 365, 'is_active' => true],
        );
        $shop->update(['status' => 'active', 'trial_ends_at' => null]);
        $shop->subscriptions()->create(['subscription_plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addDays(30)]);

        foreach (['seat-a@example.test', 'seat-b@example.test'] as $email) {
            $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
                ->postJson('/api/v1/team', ['name' => 'พนักงาน', 'email' => $email, 'password' => 'a-strong-password', 'password_confirmation' => 'a-strong-password'])
                ->assertCreated();
        }

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson('/api/v1/team', ['name' => 'พนักงานเกินโควตา', 'email' => 'seat-c@example.test', 'password' => 'a-strong-password', 'password_confirmation' => 'a-strong-password'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'จำนวนสมาชิกเต็มตามแพ็กเกจ (3 คน)');
    }

    public function test_the_old_invitation_endpoints_no_longer_exist(): void
    {
        $this->getJson('/api/v1/team-invitations/some-token')->assertNotFound();
        $this->postJson('/api/v1/team-invitations/some-token/accept', [])->assertNotFound();
    }

    private function stateful(): self
    {
        return $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/login',
        ]);
    }

    private function owner(string $email, string $name): array
    {
        $shop = Shop::create(['name' => $name, 'slug' => 'shop-'.uniqid(), 'status' => 'trialing', 'trial_ends_at' => now()->addMonth()]);
        $user = User::create(['name' => 'เจ้าของร้าน', 'email' => $email, 'password' => 'password', 'current_shop_id' => $shop->id, 'email_verified_at' => now()]);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $user->id, 'role' => 'owner', 'permissions' => [], 'joined_at' => now()]);

        return [$user, $shop];
    }
}
