<?php

namespace Tests\Feature;

use App\Jobs\SendDiscordShopNotification;
use App\Models\InventoryItem;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReservationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_available_item_can_be_reserved(): void
    {
        Queue::fake();
        [$user, $shop] = $this->owner('reserve-owner@example.test', 'ร้านจอง');
        $item = $this->item($shop, 'RSV01');

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson("/api/v1/inventory/{$item->id}/reserve", ['customer_name' => 'ลูกค้าทดสอบ'])
            ->assertCreated()
            ->assertJsonPath('data.inventory_item_id', $item->id);

        $this->assertSame('reserved', $item->fresh()->status->value);
        $this->assertNotNull($item->fresh());
        Queue::assertPushed(SendDiscordShopNotification::class, fn (SendDiscordShopNotification $job) => $job->purpose === 'reservations');
    }

    public function test_an_already_reserved_item_cannot_be_reserved_again(): void
    {
        Queue::fake();
        [$user, $shop] = $this->owner('reserve-owner2@example.test', 'ร้านจอง 2');
        $item = $this->item($shop, 'RSV02');
        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson("/api/v1/inventory/{$item->id}/reserve", [])->assertCreated();

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson("/api/v1/inventory/{$item->id}/reserve", [])
            ->assertConflict()
            ->assertJsonPath('message', 'รายการนี้ไม่พร้อมให้จอง');
    }

    public function test_releasing_a_reservation_returns_the_item_to_available_and_updates_the_timeline(): void
    {
        Queue::fake();
        [$user, $shop] = $this->owner('reserve-owner3@example.test', 'ร้านจอง 3');
        $item = $this->item($shop, 'RSV03');
        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson("/api/v1/inventory/{$item->id}/reserve", [])->assertCreated();

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->deleteJson("/api/v1/inventory/{$item->id}/reserve")
            ->assertOk()
            ->assertJsonPath('message', 'ยกเลิกการจองแล้ว');

        $this->assertSame('available', $item->fresh()->status->value);
        $this->assertDatabaseHas('reservations', ['inventory_item_id' => $item->id]);

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson("/api/v1/inventory/{$item->id}/timeline")
            ->assertOk()
            ->assertJsonFragment(['event' => 'inventory.reservation_released']);
    }

    public function test_releasing_an_item_that_is_not_reserved_conflicts(): void
    {
        [$user, $shop] = $this->owner('reserve-owner4@example.test', 'ร้านจอง 4');
        $item = $this->item($shop, 'RSV04');

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->deleteJson("/api/v1/inventory/{$item->id}/reserve")
            ->assertConflict()
            ->assertJsonPath('message', 'รายการนี้ไม่ได้ถูกจอง');
    }

    public function test_a_staff_member_without_the_sell_permission_cannot_reserve(): void
    {
        [$owner, $shop] = $this->owner('reserve-owner5@example.test', 'ร้านจอง 5');
        $item = $this->item($shop, 'RSV05');
        $staff = User::create(['name' => 'พนักงานคลัง', 'email' => 'stock-staff@example.test', 'password' => 'password', 'current_shop_id' => $shop->id, 'email_verified_at' => now()]);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $staff->id, 'role' => 'staff', 'permissions' => ['inventory.manage'], 'joined_at' => now()]);

        $this->actingAs($staff)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson("/api/v1/inventory/{$item->id}/reserve", [])
            ->assertForbidden();
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

    private function item(Shop $shop, string $tag): InventoryItem
    {
        return InventoryItem::create(['shop_id' => $shop->id, 'tag' => $tag, 'title' => "Item {$tag}", 'riot_id' => 'player#'.$tag, 'cost' => 3000, 'list_price' => 5900, 'status' => 'available']);
    }
}
