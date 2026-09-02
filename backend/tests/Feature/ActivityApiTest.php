<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\InventoryItem;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_browse_the_shop_activity_log_with_filters(): void
    {
        [$owner, $shop] = $this->owner('act-owner@example.test', 'ร้านบันทึกกิจกรรม');
        $staff = $this->staff($shop, 'act-staff@example.test', ['inventory.manage']);
        $item = InventoryItem::create(['shop_id' => $shop->id, 'tag' => 'ACT01', 'title' => 'Item', 'cost' => 100, 'list_price' => 200, 'status' => 'available']);

        $this->log($shop, $owner, 'inventory.created', $item);
        $this->log($shop, $staff, 'inventory.updated', $item);
        $this->log($shop, null, 'import.completed', null, ['imported_rows' => 12]);

        $response = $this->actingAs($owner)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/activity')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(3, 'data');
        $this->assertContains('inventory.created', $response->json('filters.events'));
        $this->assertContains($staff->id, collect($response->json('filters.actors'))->pluck('id')->all());

        // filter by event
        $this->actingAs($owner)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/activity?event=inventory.updated')
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.actor.id', $staff->id);

        // filter by actor = the staff member
        $this->actingAs($owner)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/activity?actor='.$staff->id)
            ->assertOk()->assertJsonPath('meta.total', 1);

        // filter by actor = system (unattributed)
        $this->actingAs($owner)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/activity?actor=system')
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.event', 'import.completed');
    }

    public function test_a_shop_cannot_see_another_shops_activity(): void
    {
        [$ownerA, $shopA] = $this->owner('act-a@example.test', 'ร้าน A');
        [, $shopB] = $this->owner('act-b@example.test', 'ร้าน B');
        $this->log($shopB, null, 'inventory.created');

        $this->actingAs($ownerA)->withHeader('X-Shop-Id', (string) $shopB->id)
            ->getJson('/api/v1/activity')->assertNotFound();

        $this->actingAs($ownerA)->withHeader('X-Shop-Id', (string) $shopA->id)
            ->getJson('/api/v1/activity')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_a_staff_member_without_team_manage_cannot_view_the_activity_log(): void
    {
        [, $shop] = $this->owner('act-owner2@example.test', 'ร้าน 2');
        $staff = $this->staff($shop, 'act-staff2@example.test', ['inventory.sell']);

        $this->actingAs($staff)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/activity')->assertForbidden();
    }

    public function test_logging_out_is_recorded_in_the_activity_log(): void
    {
        [$owner, $shop] = $this->owner('logout-owner@example.test', 'ร้านออกจากระบบ');

        $this->actingAs($owner)
            ->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'])
            ->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'shop_id' => $shop->id, 'user_id' => $owner->id, 'event' => 'auth.logged_out',
        ]);
    }

    /**
     * @return array{0: User, 1: Shop}
     */
    private function owner(string $email, string $name): array
    {
        $shop = Shop::create(['name' => $name, 'slug' => str($name)->slug().'-'.uniqid(), 'status' => 'trialing', 'trial_ends_at' => now()->addMonth()]);
        $user = User::create(['name' => 'เจ้าของร้าน', 'email' => $email, 'password' => 'password', 'current_shop_id' => $shop->id, 'email_verified_at' => now()]);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $user->id, 'role' => 'owner', 'permissions' => [], 'joined_at' => now()]);

        return [$user, $shop];
    }

    private function staff(Shop $shop, string $email, array $permissions): User
    {
        $user = User::create(['name' => 'พนักงาน', 'email' => $email, 'password' => 'password', 'current_shop_id' => $shop->id, 'email_verified_at' => now()]);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $user->id, 'role' => 'staff', 'permissions' => $permissions, 'joined_at' => now()]);

        return $user;
    }

    private function log(Shop $shop, ?User $user, string $event, ?InventoryItem $subject = null, array $metadata = []): void
    {
        ActivityLog::create([
            'shop_id' => $shop->id,
            'user_id' => $user?->id,
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
