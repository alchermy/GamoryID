<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_erase_a_customers_contact_details_while_keeping_sale_history(): void
    {
        [$owner, $shop] = $this->owner('priv-owner@example.test', 'ร้านความเป็นส่วนตัว');
        $customer = Customer::create([
            'shop_id' => $shop->id, 'name' => 'คุณเอ', 'phone' => '0891112222',
            'line_id' => 'khun-a', 'facebook_url' => 'https://facebook.com/a', 'notes' => 'ลูกค้าประจำ',
        ]);
        $item = InventoryItem::create(['shop_id' => $shop->id, 'tag' => 'PRV01', 'title' => 'Item', 'cost' => 100, 'list_price' => 300, 'status' => 'sold']);
        $sale = Sale::create([
            'shop_id' => $shop->id, 'inventory_item_id' => $item->id, 'customer_id' => $customer->id,
            'sold_price' => 300, 'cost_snapshot' => 100, 'profit' => 200, 'has_warranty' => false, 'sold_at' => now(),
        ]);

        $this->actingAs($owner)->withHeader('X-Shop-Id', (string) $shop->id)
            ->deleteJson("/api/v1/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('message', 'ลบข้อมูลติดต่อของลูกค้าแล้ว ประวัติการขายยังคงอยู่');

        $customer->refresh();
        $this->assertNotNull($customer->anonymized_at);
        $this->assertNull($customer->phone);
        $this->assertNull($customer->line_id);
        $this->assertNull($customer->facebook_url);
        $this->assertStringContainsString('ลบข้อมูลแล้ว', $customer->name);

        // sale row keeps its link
        $this->assertSame($customer->id, $sale->fresh()->customer_id);
        $this->assertDatabaseHas('activity_logs', [
            'shop_id' => $shop->id, 'user_id' => $owner->id, 'event' => 'customer.anonymized',
        ]);

        // second call is a no-op, not an error
        $this->actingAs($owner)->withHeader('X-Shop-Id', (string) $shop->id)
            ->deleteJson("/api/v1/customers/{$customer->id}")->assertOk();
        $this->assertDatabaseCount('activity_logs', 1);
    }

    public function test_a_staff_member_without_team_manage_cannot_erase_a_customer(): void
    {
        [, $shop] = $this->owner('priv-owner2@example.test', 'ร้าน 2');
        $staff = User::create(['name' => 'พนักงาน', 'email' => 'priv-staff@example.test', 'password' => 'password', 'current_shop_id' => $shop->id, 'email_verified_at' => now()]);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $staff->id, 'role' => 'staff', 'permissions' => ['inventory.sell'], 'joined_at' => now()]);
        $customer = Customer::create(['shop_id' => $shop->id, 'name' => 'คุณบี', 'phone' => '0800000000']);

        $this->actingAs($staff)->withHeader('X-Shop-Id', (string) $shop->id)
            ->deleteJson("/api/v1/customers/{$customer->id}")->assertForbidden();
        $this->assertNull($customer->fresh()->anonymized_at);
    }

    public function test_a_shop_cannot_erase_another_shops_customer(): void
    {
        [$ownerA, $shopA] = $this->owner('priv-a@example.test', 'ร้าน A');
        [, $shopB] = $this->owner('priv-b@example.test', 'ร้าน B');
        $foreign = Customer::create(['shop_id' => $shopB->id, 'name' => 'คุณซี']);

        $this->actingAs($ownerA)->withHeader('X-Shop-Id', (string) $shopA->id)
            ->deleteJson("/api/v1/customers/{$foreign->id}")->assertNotFound();
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
}
