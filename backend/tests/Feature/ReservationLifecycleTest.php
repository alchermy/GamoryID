<?php

namespace Tests\Feature;

use App\Jobs\SendDiscordShopNotification;
use App\Models\InventoryItem;
use App\Models\Reservation;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use App\Services\ReservationLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReservationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_expired_reservation_is_released_and_notified(): void
    {
        Queue::fake();
        [$shop, $user] = $this->owner();
        $item = $this->reservedItem($shop, 'EXP01');
        $reservation = Reservation::create([
            'shop_id' => $shop->id,
            'inventory_item_id' => $item->id,
            'created_by' => $user->id,
            'expires_at' => now()->subHour(),
        ]);

        app(ReservationLifecycle::class)->run();

        $item->refresh();
        $this->assertSame('available', $item->status->value);
        $this->assertNotNull($reservation->fresh()->released_at);
        $this->assertDatabaseHas('activity_logs', [
            'shop_id' => $shop->id,
            'event' => 'inventory.reservation_expired',
            'subject_type' => InventoryItem::class,
            'subject_id' => $item->id,
        ]);
        Queue::assertPushed(SendDiscordShopNotification::class, fn (SendDiscordShopNotification $job) => $job->shopId === $shop->id && $job->purpose === 'reservations');
    }

    public function test_a_reservation_not_yet_expired_is_left_untouched(): void
    {
        Queue::fake();
        [$shop, $user] = $this->owner();
        $item = $this->reservedItem($shop, 'EXP02');
        $reservation = Reservation::create([
            'shop_id' => $shop->id,
            'inventory_item_id' => $item->id,
            'created_by' => $user->id,
            'expires_at' => now()->addHour(),
        ]);

        app(ReservationLifecycle::class)->run();

        $this->assertSame('reserved', $item->fresh()->status->value);
        $this->assertNull($reservation->fresh()->released_at);
        Queue::assertNotPushed(SendDiscordShopNotification::class);
    }

    public function test_an_expired_reservation_on_an_already_sold_item_only_closes_the_reservation(): void
    {
        Queue::fake();
        [$shop, $user] = $this->owner();
        $item = $this->reservedItem($shop, 'EXP03');
        $item->update(['status' => 'sold']);
        $reservation = Reservation::create([
            'shop_id' => $shop->id,
            'inventory_item_id' => $item->id,
            'created_by' => $user->id,
            'expires_at' => now()->subHour(),
        ]);

        app(ReservationLifecycle::class)->run();

        $this->assertSame('sold', $item->fresh()->status->value);
        $this->assertNotNull($reservation->fresh()->released_at);
        Queue::assertNotPushed(SendDiscordShopNotification::class);
    }

    public function test_the_expiry_event_appears_on_the_item_timeline(): void
    {
        Queue::fake();
        [$shop, $user] = $this->owner();
        $item = $this->reservedItem($shop, 'EXP04');
        Reservation::create([
            'shop_id' => $shop->id,
            'inventory_item_id' => $item->id,
            'created_by' => $user->id,
            'expires_at' => now()->subMinute(),
        ]);

        app(ReservationLifecycle::class)->run();

        $this->actingAs($user)
            ->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson("/api/v1/inventory/{$item->id}/timeline")
            ->assertOk()
            ->assertJsonFragment(['event' => 'inventory.reservation_expired']);
    }

    /**
     * @return array{0: Shop, 1: User}
     */
    private function owner(): array
    {
        $shop = Shop::create(['name' => 'ร้านทดสอบจอง', 'slug' => 'reservation-lifecycle-'.uniqid(), 'status' => 'trialing', 'trial_ends_at' => now()->addMonth()]);
        $user = User::create(['name' => 'เจ้าของร้าน', 'email' => 'reservation-owner-'.uniqid().'@example.test', 'password' => 'password', 'current_shop_id' => $shop->id, 'email_verified_at' => now()]);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $user->id, 'role' => 'owner', 'permissions' => [], 'joined_at' => now()]);

        return [$shop, $user];
    }

    private function reservedItem(Shop $shop, string $tag): InventoryItem
    {
        return InventoryItem::create([
            'shop_id' => $shop->id,
            'tag' => $tag,
            'title' => "Item {$tag}",
            'riot_id' => 'player#'.$tag,
            'cost' => 3000,
            'list_price' => 5900,
            'status' => 'reserved',
        ]);
    }
}
