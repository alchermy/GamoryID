<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\InventoryMedia;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_cannot_read_another_shops_inventory(): void
    {
        [$user, $shopA] = $this->owner('a@example.test', 'ร้าน A');
        [, $shopB] = $this->owner('b@example.test', 'ร้าน B');
        $foreign = $this->item($shopB, 'ZX234');

        $this->actingAs($user)
            ->withHeader('X-Shop-Id', (string) $shopA->id)
            ->getJson("/api/v1/inventory/{$foreign->id}")
            ->assertNotFound();

        $this->actingAs($user)
            ->withHeader('X-Shop-Id', (string) $shopB->id)
            ->getJson('/api/v1/inventory')
            ->assertNotFound();
    }

    public function test_create_generates_tag_and_never_exposes_credentials(): void
    {
        [$user, $shop] = $this->owner('owner@example.test', 'Nexus Store');
        $response = $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)->postJson('/api/v1/inventory', [
            'riot_id' => 'Gammy#TH01', 'username' => 'gammy.ops01', 'rank' => 'Diamond 3',
            'cost' => 4000, 'list_price' => 6500,
            'credentials' => ['username' => 'secret@example.test', 'password' => 'very-secret'],
        ]);

        $response->assertCreated()->assertJsonPath('data.has_credentials', true)->assertJsonPath('data.riot_id', 'Gammy#TH01')->assertJsonPath('data.username', 'gammy.ops01')->assertJsonMissing(['password' => 'very-secret']);
        $this->assertMatchesRegularExpression('/^#[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{5}$/', $response->json('data.tag'));
        $this->assertDatabaseCount('inventory_credentials', 1);
        $this->assertDatabaseHas('inventory_items', ['riot_id' => 'Gammy#TH01', 'username' => 'gammy.ops01', 'region' => 'TH']);
    }

    public function test_exact_tag_search_is_scoped_to_current_shop(): void
    {
        [$user, $shop] = $this->owner('find@example.test', 'ร้านค้นหา');
        $this->item($shop, '23DX5');
        $this->item($shop, 'Q7N2P');

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/inventory?q=%2323DX5')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.tag', '#23DX5');
    }

    public function test_owner_can_view_and_update_inventory_details_without_exposing_credentials(): void
    {
        [$user, $shop] = $this->owner('crud@example.test', 'ร้าน CRUD');
        $item = $this->item($shop, 'EDIT5');

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson("/api/v1/inventory/{$item->id}")
            ->assertOk()
            ->assertJsonPath('data.tag', '#EDIT5');

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->putJson("/api/v1/inventory/{$item->id}", [
                'title' => 'Gammy#TH55',
                'riot_id' => 'Gammy#TH55',
                'username' => 'gammy.updated',
                'description' => 'รายละเอียดใหม่สำหรับลูกค้า',
                'rank' => 'Immortal 2',
                'level' => 255,
                'cost' => 5100,
                'list_price' => 7900,
                'credentials' => ['password' => 'rotated-secret'],
            ])
            ->assertOk()
            ->assertJsonPath('data.riot_id', 'Gammy#TH55')
            ->assertJsonPath('data.description', 'รายละเอียดใหม่สำหรับลูกค้า')
            ->assertJsonPath('data.rank', 'Immortal 2')
            ->assertJsonPath('data.level', 255)
            ->assertJsonMissing(['password' => 'rotated-secret']);

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'shop_id' => $shop->id,
            'riot_id' => 'Gammy#TH55',
            'username' => 'gammy.updated',
            'list_price' => 7900,
        ]);
    }

    public function test_inventory_note_is_tenant_scoped_permission_checked_and_audited_without_its_content(): void
    {
        [$owner, $shop] = $this->owner('note-owner@example.test', 'ร้านโน้ต');
        $item = $this->item($shop, 'NOTE5');
        $note = 'คุณเอกจองถึง 18:00 น. รอตัดสินใจ';

        $this->actingAs($owner)->withHeader('X-Shop-Id', (string) $shop->id)
            ->patchJson("/api/v1/inventory/{$item->id}/note", ['notes' => $note])
            ->assertOk()
            ->assertJsonPath('data.notes', $note);

        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'notes' => $note]);
        $log = ActivityLog::where('event', 'inventory.note_updated')->latest('id')->firstOrFail();
        $this->assertSame(['tag' => '#NOTE5', 'has_note' => true], $log->metadata);
        $this->assertStringNotContainsString($note, (string) $log->toJson());

        $staff = User::create([
            'name' => 'พนักงานขาย',
            'email' => 'note-staff@example.test',
            'password' => 'password',
            'current_shop_id' => $shop->id,
            'email_verified_at' => now(),
        ]);
        ShopMember::create([
            'shop_id' => $shop->id,
            'user_id' => $staff->id,
            'role' => 'staff',
            'permissions' => ['inventory.sell'],
            'joined_at' => now(),
        ]);

        $this->actingAs($staff)->withHeader('X-Shop-Id', (string) $shop->id)
            ->patchJson("/api/v1/inventory/{$item->id}/note", ['notes' => 'รอปิดการขาย'])
            ->assertOk()
            ->assertJsonPath('data.notes', 'รอปิดการขาย');

        $staff->shops()->updateExistingPivot($shop->id, ['permissions' => json_encode([])]);
        $this->actingAs($staff)->withHeader('X-Shop-Id', (string) $shop->id)
            ->patchJson("/api/v1/inventory/{$item->id}/note", ['notes' => 'ไม่มีสิทธิ์'])
            ->assertForbidden();

        [, $otherShop] = $this->owner('note-other@example.test', 'ร้านอื่น');
        $foreign = $this->item($otherShop, 'OTHER');
        $this->actingAs($owner)->withHeader('X-Shop-Id', (string) $shop->id)
            ->patchJson("/api/v1/inventory/{$foreign->id}/note", ['notes' => 'ห้ามข้ามร้าน'])
            ->assertNotFound();
    }

    public function test_inventory_supports_one_display_image_and_four_detail_images(): void
    {
        Storage::fake('private');
        [$user, $shop] = $this->owner('media@example.test', 'ร้านรูปภาพ');
        $item = $this->item($shop, 'MEDIA');

        $displayResponse = $this->actingAs($user)
            ->withHeader('X-Shop-Id', (string) $shop->id)
            ->post("/api/v1/inventory/{$item->id}/media", [
                'role' => 'display',
                'image' => $this->image('display.png'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'display');

        $firstDisplay = InventoryMedia::where('role', 'display')->firstOrFail();
        Storage::disk('private')->assertExists($firstDisplay->path);

        $this->actingAs($user)
            ->withHeader('X-Shop-Id', (string) $shop->id)
            ->withHeader('Accept', 'application/json')
            ->post("/api/v1/inventory/{$item->id}/media", [
                'role' => 'display',
                'image' => $this->image('replacement.png'),
            ])
            ->assertCreated();

        $this->assertDatabaseCount('inventory_media', 1);
        Storage::disk('private')->assertMissing($firstDisplay->path);

        foreach (range(1, 4) as $index) {
            $this->actingAs($user)
                ->withHeader('X-Shop-Id', (string) $shop->id)
                ->post("/api/v1/inventory/{$item->id}/media", [
                    'role' => 'detail',
                    'image' => $this->image("detail-{$index}.png"),
                ])
                ->assertCreated();
        }

        $this->actingAs($user)
            ->withHeader('X-Shop-Id', (string) $shop->id)
            ->withHeader('Accept', 'application/json')
            ->post("/api/v1/inventory/{$item->id}/media", [
                'role' => 'detail',
                'image' => $this->image('too-many.png'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');

        $detailResponse = $this->actingAs($user)
            ->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson("/api/v1/inventory/{$item->id}")
            ->assertOk()
            ->assertJsonCount(5, 'data.media')
            ->assertJsonPath('data.media.0.role', 'display');

        $signedImageUrl = $detailResponse->json('data.media.0.url');
        $this->actingAs($user)->get($signedImageUrl)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('signature=', $displayResponse->json('data.url'));
    }

    public function test_inventory_media_is_private_and_tenant_scoped(): void
    {
        Storage::fake('private');
        [$owner, $shop] = $this->owner('media-owner@example.test', 'ร้านเจ้าของรูป');
        [$outsider] = $this->owner('media-outsider@example.test', 'ร้านอื่น');
        $item = $this->item($shop, 'PRIV8');

        $upload = $this->actingAs($owner)
            ->withHeader('X-Shop-Id', (string) $shop->id)
            ->post("/api/v1/inventory/{$item->id}/media", [
                'role' => 'display',
                'image' => $this->image('private.png'),
            ])
            ->assertCreated();

        $mediaId = $upload->json('data.id');
        $signedImageUrl = $upload->json('data.url');

        $this->actingAs($outsider)->get($signedImageUrl)->assertNotFound();
        $this->actingAs($outsider)
            ->withHeader('X-Shop-Id', (string) $outsider->current_shop_id)
            ->deleteJson("/api/v1/inventory/{$item->id}/media/{$mediaId}")
            ->assertNotFound();

        $this->assertDatabaseHas('inventory_media', ['id' => $mediaId]);
    }

    public function test_an_item_can_only_be_sold_once(): void
    {
        [$user, $shop] = $this->owner('sell@example.test', 'ร้านขาย');
        $item = $this->item($shop, 'S4LE5');
        $payload = [
            'customer' => ['name' => 'ลูกค้า', 'facebook_url' => 'https://facebook.com/customer', 'line_id' => 'customer-line', 'phone' => '0812345678'],
            'sold_price' => 5900,
            'has_warranty' => true,
            'warranty_ends_at' => now()->addDays(7)->toDateString(),
            'notes' => 'รับประกันการเข้าใช้งาน 7 วัน',
        ];

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson("/api/v1/inventory/{$item->id}/sell", $payload)->assertCreated();
        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson("/api/v1/inventory/{$item->id}/sell", $payload)->assertConflict();
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseHas('sales', [
            'inventory_item_id' => $item->id,
            'has_warranty' => true,
        ]);
        $this->assertSame(now()->addDays(7)->toDateString(), Sale::firstOrFail()->warranty_ends_at->toDateString());
        $this->assertDatabaseHas('customers', [
            'shop_id' => $shop->id,
            'name' => 'ลูกค้า',
            'line_id' => 'customer-line',
            'phone' => '0812345678',
        ]);
    }

    public function test_grace_shop_is_read_only_but_can_list_inventory(): void
    {
        [$user, $shop] = $this->owner('grace@example.test', 'ร้านหมดอายุ');
        $shop->update(['status' => 'grace_read_only']);
        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)->getJson('/api/v1/inventory')->assertOk();
        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)->postJson('/api/v1/inventory', [
            'title' => 'Blocked', 'cost' => 0, 'list_price' => 1,
        ])->assertStatus(423)->assertJsonPath('code', 'SHOP_READ_ONLY');
    }

    public function test_archived_inventory_is_hidden_until_explicitly_filtered(): void
    {
        [$user, $shop] = $this->owner('archive@example.test', 'ร้านเก็บถาวร');
        $item = $this->item($shop, 'ARCH9');

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->deleteJson("/api/v1/inventory/{$item->id}")->assertOk();

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/inventory')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/inventory?status=archived')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'archived');
    }

    public function test_sales_and_customers_are_scoped_to_the_current_shop(): void
    {
        [$user, $shopA] = $this->owner('history-a@example.test', 'ร้านประวัติ A');
        [$foreignUser, $shopB] = $this->owner('history-b@example.test', 'ร้านประวัติ B');
        $customerA = Customer::create(['shop_id' => $shopA->id, 'name' => 'ลูกค้า A', 'line_id' => 'line-a']);
        $customerB = Customer::create(['shop_id' => $shopB->id, 'name' => 'ลูกค้า B', 'line_id' => 'line-b']);
        $itemA = $this->item($shopA, 'SALEA');
        $itemB = $this->item($shopB, 'SALEB');
        Sale::create(['shop_id' => $shopA->id, 'inventory_item_id' => $itemA->id, 'customer_id' => $customerA->id, 'created_by' => $user->id, 'sold_price' => 5000, 'cost_snapshot' => 3000, 'profit' => 2000, 'sold_at' => now()]);
        Sale::create(['shop_id' => $shopB->id, 'inventory_item_id' => $itemB->id, 'customer_id' => $customerB->id, 'created_by' => $foreignUser->id, 'sold_price' => 6000, 'cost_snapshot' => 3000, 'profit' => 3000, 'sold_at' => now()]);

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shopA->id)
            ->getJson('/api/v1/sales')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.inventory_item.tag', 'SALEA');
        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shopA->id)
            ->getJson('/api/v1/customers')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'ลูกค้า A')->assertJsonPath('data.0.sales_count', 1);
    }

    public function test_dashboard_returns_a_tenant_scoped_seven_day_sales_trend(): void
    {
        [$user, $shop] = $this->owner('dashboard@example.test', 'ร้าน Dashboard');
        [, $otherShop] = $this->owner('other-dashboard@example.test', 'ร้านอื่น');
        $available = $this->item($shop, 'DASH1');
        $reserved = $this->item($shop, 'DASH2');
        $reserved->update(['status' => 'reserved']);
        $sold = $this->item($shop, 'DASH3');
        $sold->update(['status' => 'sold']);
        $foreign = $this->item($otherShop, 'DASH4');
        $foreign->update(['status' => 'sold']);

        Sale::create(['shop_id' => $shop->id, 'inventory_item_id' => $sold->id, 'created_by' => $user->id, 'sold_price' => 5900, 'cost_snapshot' => 3000, 'profit' => 2900, 'sold_at' => now()]);
        Sale::create(['shop_id' => $otherShop->id, 'inventory_item_id' => $foreign->id, 'created_by' => $user->id, 'sold_price' => 9000, 'cost_snapshot' => 3000, 'profit' => 6000, 'sold_at' => now()]);

        $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('summary.available', 1)
            ->assertJsonPath('summary.reserved', 1)
            ->assertJsonPath('summary.sold_total', 1)
            ->assertJsonPath('summary.revenue_this_month', 5900)
            ->assertJsonPath('summary.profit_this_month', 2900)
            ->assertJsonCount(7, 'sales_last_7_days')
            ->assertJsonPath('sales_last_7_days.6.revenue', 5900)
            ->assertJsonPath('sales_last_7_days.6.sales', 1);
    }

    private function owner(string $email, string $name): array
    {
        $shop = Shop::create(['name' => $name, 'slug' => str($name)->slug().'-'.uniqid(), 'status' => 'trialing', 'trial_ends_at' => now()->addMonth()]);
        $user = User::create(['name' => 'เจ้าของร้าน', 'email' => $email, 'password' => 'password', 'current_shop_id' => $shop->id, 'email_verified_at' => now()]);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $user->id, 'role' => 'owner', 'permissions' => [], 'joined_at' => now()]);

        return [$user, $shop];
    }

    private function item(Shop $shop, string $tag): InventoryItem
    {
        return InventoryItem::create(['shop_id' => $shop->id, 'tag' => $tag, 'title' => "Item {$tag}", 'cost' => 3000, 'list_price' => 5900, 'status' => 'available']);
    }

    private function image(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1EAAAAASUVORK5CYII='),
        );
    }
}
