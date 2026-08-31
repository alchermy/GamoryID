<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Shop;
use App\Models\ShopInvitation;
use App\Models\ShopMember;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\ShopInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
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

    public function test_owner_can_invite_accept_and_manage_staff_permissions(): void
    {
        [$user, $shop] = $this->owner('team@example.test', 'ร้านทีม');
        Notification::fake();

        $create = $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson('/api/v1/team', ['name' => 'พนักงานหนึ่ง', 'email' => 'staff@example.test', 'permissions' => ['inventory.manage', 'inventory.sell']]);
        $create->assertCreated()->assertJsonPath('data.email', 'staff@example.test');
        Notification::assertSentOnDemand(ShopInvitationNotification::class);
        $token = Str::afterLast($create->json('invite_url'), '/');

        $this->getJson('/api/v1/team-invitations/'.$token)
            ->assertOk()->assertJsonPath('data.shop_name', 'ร้านทีม')->assertJsonPath('data.email', 'staff@example.test');

        $this->postJson('/api/v1/team-invitations/'.$token.'/accept', [
            'name' => 'พนักงานหนึ่ง',
            'password' => 'staff-password',
            'password_confirmation' => 'staff-password',
        ])->assertOk();

        $this->getJson('/api/v1/team-invitations/'.$token)->assertNotFound();

        $memberId = ShopMember::query()->where('shop_id', $shop->id)->where('role', 'staff')->value('id');
        $this->assertNotNull($memberId);

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->putJson('/api/v1/team/'.$memberId, ['permissions' => ['inventory.sell']])
            ->assertOk()->assertJsonPath('data.permissions.0', 'inventory.sell');

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->deleteJson('/api/v1/team/'.$memberId)->assertOk();

        $this->assertDatabaseMissing('shop_members', ['id' => $memberId]);
        $this->assertDatabaseHas('shop_invitations', ['shop_id' => $shop->id, 'email' => 'staff@example.test']);
        $this->assertNotNull(ShopInvitation::query()->where('shop_id', $shop->id)->first()?->accepted_at);
        $this->assertNotNull(ActivityLog::query()->where('shop_id', $shop->id)->where('event', 'team.invitation_accepted')->value('user_id'));
    }

    private function owner(string $email, string $name): array
    {
        $shop = Shop::create(['name' => $name, 'slug' => 'shop-'.uniqid(), 'status' => 'trialing', 'trial_ends_at' => now()->addMonth()]);
        $user = User::create(['name' => 'เจ้าของร้าน', 'email' => $email, 'password' => 'password', 'current_shop_id' => $shop->id, 'email_verified_at' => now()]);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $user->id, 'role' => 'owner', 'permissions' => [], 'joined_at' => now()]);
        SubscriptionPlan::create(['name' => 'Starter', 'code' => 'starter-'.uniqid(), 'active_inventory_limit' => 1000, 'member_limit' => 3, 'price_thb' => 299, 'duration_days' => 30, 'is_active' => true]);

        return [$user, $shop];
    }
}
