<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Reservation;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SaleConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private array $payload = [
        'customer' => ['name' => 'ลูกค้า'],
        'sold_price' => 5900,
        'has_warranty' => false,
    ];

    public function test_selling_the_same_item_twice_sequentially_only_succeeds_once(): void
    {
        Queue::fake();
        [$user, $shop] = $this->owner('sell-a@example.test', 'ร้านขาย A');
        $item = $this->item($shop, 'CON01');

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson("/api/v1/inventory/{$item->id}/sell", $this->payload)->assertCreated();
        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson("/api/v1/inventory/{$item->id}/sell", $this->payload)
            ->assertConflict()
            ->assertJsonPath('message', 'รายการนี้ถูกขายไปแล้ว');

        $this->assertDatabaseCount('sales', 1);
    }

    public function test_an_orphaned_sale_row_still_blocks_a_resell_even_if_item_status_drifted(): void
    {
        Queue::fake();
        [$user, $shop] = $this->owner('sell-b@example.test', 'ร้านขาย B');
        $item = $this->item($shop, 'CON02');
        // Simulate a sale row that exists without the item status having been flipped
        // (defense-in-depth: the guard must not rely on `status` alone).
        Sale::create([
            'shop_id' => $shop->id,
            'inventory_item_id' => $item->id,
            'sold_price' => 5900,
            'cost_snapshot' => 3000,
            'profit' => 2900,
            'has_warranty' => false,
            'sold_at' => now(),
        ]);

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson("/api/v1/inventory/{$item->id}/sell", $this->payload)
            ->assertConflict()
            ->assertJsonPath('message', 'รายการนี้ถูกขายไปแล้ว');

        $this->assertDatabaseCount('sales', 1);
    }

    public function test_a_staff_member_can_sell_an_item_reserved_by_another_staff_member(): void
    {
        Queue::fake();
        [$owner, $shop] = $this->owner('sell-c@example.test', 'ร้านขาย C');
        $item = $this->item($shop, 'CON03');
        $staffA = $this->staff($shop, 'staff-a@example.test');
        $staffB = $this->staff($shop, 'staff-b@example.test');

        $this->actingAs($staffA)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson("/api/v1/inventory/{$item->id}/reserve", [])->assertCreated();
        $this->assertSame('reserved', $item->fresh()->status->value);

        $this->actingAs($staffB)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson("/api/v1/inventory/{$item->id}/sell", $this->payload)
            ->assertCreated();

        $this->assertSame('sold', $item->fresh()->status->value);
        $this->assertDatabaseHas('reservations', ['inventory_item_id' => $item->id]);
        $this->assertNotNull(Reservation::where('inventory_item_id', $item->id)->first()->released_at);
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

    private function staff(Shop $shop, string $email): User
    {
        $user = User::create(['name' => 'พนักงานขาย', 'email' => $email, 'password' => 'password', 'current_shop_id' => $shop->id, 'email_verified_at' => now()]);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $user->id, 'role' => 'staff', 'permissions' => ['inventory.sell'], 'joined_at' => now()]);

        return $user;
    }

    private function item(Shop $shop, string $tag): InventoryItem
    {
        return InventoryItem::create(['shop_id' => $shop->id, 'tag' => $tag, 'title' => "Item {$tag}", 'riot_id' => 'player#'.$tag, 'cost' => 3000, 'list_price' => 5900, 'status' => 'available']);
    }
}
