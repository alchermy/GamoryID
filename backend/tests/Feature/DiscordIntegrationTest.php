<?php

namespace Tests\Feature;

use App\Models\DiscordInstallation;
use App\Models\DiscordUserLink;
use App\Models\InventoryItem;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use App\Services\Discord\DiscordCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DiscordIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.discord.test_bypass', true);
    }

    public function test_discord_ping_is_acknowledged_in_test_mode(): void
    {
        $this->postJson('/api/v1/discord/interactions', [
            'id' => 'ping-1',
            'type' => 1,
        ])->assertOk()->assertJson(['type' => 1]);
    }

    public function test_unsigned_discord_interaction_is_rejected_outside_test_bypass(): void
    {
        config()->set('services.discord.test_bypass', false);

        $this->postJson('/api/v1/discord/interactions', [
            'id' => 'unsigned-1',
            'type' => 1,
        ])->assertUnauthorized();
    }

    public function test_owner_can_connect_a_guild_link_a_user_and_find_only_safe_tenant_data(): void
    {
        [$owner, $shop] = $this->owner('discord-owner@example.test', 'Discord Shop');
        [, $otherShop] = $this->owner('discord-other@example.test', 'Other Shop');
        InventoryItem::create([
            'shop_id' => $shop->id,
            'tag' => '23DX5',
            'title' => 'Gammy#TH01',
            'username' => 'private-login',
            'rank' => 'Ascendant 2',
            'level' => 121,
            'description' => 'มีสกินสำหรับส่งให้ลูกค้า',
            'notes' => 'โน้ตทีมที่ห้ามส่ง',
            'cost' => 4000,
            'list_price' => 8900,
            'status' => 'available',
        ]);
        InventoryItem::create([
            'shop_id' => $otherShop->id,
            'tag' => 'OTHER',
            'title' => 'Other#TH01',
            'cost' => 1,
            'list_price' => 1,
            'status' => 'available',
        ]);

        $setup = app(DiscordCodeService::class)->issueSetupCode($shop, $owner);
        $this->postJson('/api/v1/discord/interactions', $this->interaction('setup-1', 'ตั้งค่า', 'รหัส', $setup['code'], 'guild-shop', 'initial-room'))
            ->assertOk()
            ->assertJsonPath('data.flags', 64)
            ->assertJsonPath('data.content', fn ($content) => str_contains($content, 'เชื่อมเซิร์ฟเวอร์กับร้านสำเร็จ'));

        $this->actingAs($owner)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson('/api/v1/discord/channels/auto-create')
            ->assertOk()
            ->assertJsonPath('message', 'สร้างหรือปรับปรุงห้องภาษาไทยของ GamoryID แล้ว');
        $commandChannelId = "demo-{$shop->id}-commands";
        $this->assertDatabaseHas('discord_channel_bindings', [
            'discord_installation_id' => $shop->discordInstallation->id,
            'purpose' => 'commands',
            'channel_id' => $commandChannelId,
            'channel_name' => 'คำสั่งทั่วไป',
        ]);

        $link = app(DiscordCodeService::class)->issueLinkCode($shop, $owner);
        $this->postJson('/api/v1/discord/interactions', $this->interaction('link-wrong-room', 'เชื่อมบัญชี', 'รหัส', $link['code'], 'guild-shop', 'other-room'))
            ->assertOk()
            ->assertJsonPath('data.content', fn ($content) => str_contains($content, 'ในห้อง #คำสั่งทั่วไป เท่านั้น'));
        $this->postJson('/api/v1/discord/interactions', $this->interaction('link-1', 'เชื่อมบัญชี', 'รหัส', $link['code'], 'guild-shop', $commandChannelId))
            ->assertOk()
            ->assertJsonPath('data.content', fn ($content) => str_contains($content, 'เชื่อมบัญชีสำเร็จ'));

        $this->postJson('/api/v1/discord/interactions', $this->interaction('find-wrong-room', 'ค้นหา', 'แท็ก', '#23DX5', 'guild-shop', 'other-room'))
            ->assertOk()
            ->assertJsonPath('data.content', fn ($content) => str_contains($content, 'ในห้อง #คำสั่งทั่วไป เท่านั้น'));

        $find = $this->postJson('/api/v1/discord/interactions', $this->interaction('find-1', 'ค้นหา', 'แท็ก', '#23DX5', 'guild-shop', $commandChannelId))
            ->assertOk()
            ->assertJsonPath('data.flags', 64);
        $content = (string) $find->json('data.content');
        $this->assertStringContainsString('#23DX5', $content);
        $this->assertStringContainsString('Gammy\\#TH01', $content);
        $this->assertStringContainsString('Ascendant 2', $content);
        $this->assertStringContainsString('฿8,900', $content);
        $this->assertStringNotContainsString('private-login', $content);
        $this->assertStringNotContainsString('โน้ตทีมที่ห้ามส่ง', $content);
        $this->assertStringNotContainsString('4,000', $content);

        $this->assertDatabaseHas('discord_installations', ['shop_id' => $shop->id, 'guild_id' => 'guild-shop']);
        $this->assertDatabaseHas('discord_command_logs', ['command' => 'ร้าน.ค้นหา', 'status' => 'wrong_channel']);
        $this->assertDatabaseCount('discord_command_logs', 5);
    }

    public function test_staff_without_discord_management_permission_cannot_change_connection(): void
    {
        [, $shop] = $this->owner('owner-permission@example.test', 'Permission Shop');
        $staff = User::create([
            'name' => 'พนักงาน',
            'email' => 'discord-staff@example.test',
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
            ->postJson('/api/v1/discord/demo-connect')
            ->assertForbidden();
    }

    public function test_demo_mode_creates_channels_and_settings_remain_tenant_scoped(): void
    {
        [$owner, $shop] = $this->owner('demo-owner@example.test', 'Demo Shop');
        [$otherOwner, $otherShop] = $this->owner('demo-other@example.test', 'Demo Other');

        $this->actingAs($owner)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson('/api/v1/discord/demo-connect')
            ->assertOk();
        $this->actingAs($owner)->withHeader('X-Shop-Id', (string) $shop->id)
            ->getJson('/api/v1/discord/settings')
            ->assertOk()
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.installation.guild_name', 'Demo Shop Community')
            ->assertJsonCount(5, 'data.installation.channels')
            ->assertJsonPath('data.installation.channels.0.channel_name', 'คำสั่งทั่วไป');
        $this->actingAs($owner)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson('/api/v1/discord/test-notification', ['purpose' => 'system'])
            ->assertOk()
            ->assertJsonPath('message', 'ส่งข้อความทดสอบแล้ว');

        $this->actingAs($otherOwner)->withHeader('X-Shop-Id', (string) $otherShop->id)
            ->getJson('/api/v1/discord/settings')
            ->assertOk()
            ->assertJsonPath('data.connected', false)
            ->assertJsonPath('data.installation', null);
    }

    public function test_demo_installation_can_be_disconnected_after_test_bypass_is_disabled(): void
    {
        [$owner, $shop] = $this->owner('demo-cleanup@example.test', 'Demo Cleanup');

        $this->actingAs($owner)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson('/api/v1/discord/demo-connect')
            ->assertOk();

        config()->set('services.discord.test_bypass', false);

        $this->actingAs($owner)->withHeader('X-Shop-Id', (string) $shop->id)
            ->deleteJson('/api/v1/discord/disconnect')
            ->assertOk();
        $this->assertDatabaseMissing('discord_installations', ['shop_id' => $shop->id]);
    }

    public function test_setup_code_registers_thai_application_commands(): void
    {
        [$owner, $shop] = $this->owner('thai-command-owner@example.test', 'Thai Command Shop');
        config()->set('services.discord.application_id', 'application-1');
        config()->set('services.discord.public_key', str_repeat('a', 64));
        config()->set('services.discord.bot_token', 'bot-token');
        Http::fake([
            'https://discord.com/api/v10/applications/application-1/commands' => Http::response([], 200),
        ]);

        $this->actingAs($owner)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson('/api/v1/discord/setup-code')
            ->assertCreated();

        Http::assertSent(function ($request) {
            $commands = $request->data();
            $subcommands = collect(data_get($commands, '0.options', []))->pluck('name');

            $addId = collect(data_get($commands, '0.options', []))->firstWhere('name', 'เพิ่มไอดี');
            $addIdOptions = collect(data_get($addId, 'options', []));
            $rankOption = $addIdOptions->firstWhere('name', 'แรงก์');
            // Discord rejects a required option that appears after an optional one.
            $seenOptional = false;
            $orderingValid = $addIdOptions->every(function ($option) use (&$seenOptional) {
                $required = (bool) ($option['required'] ?? false);
                if (! $required) {
                    $seenOptional = true;
                }

                return ! ($required && $seenOptional);
            });

            return $request->method() === 'PUT'
                && $orderingValid
                && $addIdOptions->pluck('name')->contains('รหัส')
                && data_get($commands, '0.name') === 'ร้าน'
                && $subcommands->contains('ตั้งค่า')
                && $subcommands->contains('เชื่อมบัญชี')
                && $subcommands->contains('ค้นหา')
                && $subcommands->contains('สรุป')
                && $subcommands->contains('รายการ')
                && $subcommands->contains('จอง')
                && $subcommands->contains('ยกเลิกจอง')
                && $subcommands->contains('ปิดการขาย')
                && $subcommands->contains('โน้ต')
                && $subcommands->contains('เพิ่มไอดี')
                && $subcommands->contains('ช่วยเหลือ')
                && $addIdOptions->pluck('name')->contains('ชื่อ')
                && $addIdOptions->pluck('name')->contains('username')
                && collect(data_get($rankOption, 'choices', []))->pluck('value')->contains('Radiant')
                && collect(data_get($rankOption, 'choices', []))->count() === 25;
        });
    }

    public function test_staff_discord_commands_follow_current_shop_permissions(): void
    {
        Queue::fake();
        [, $shop] = $this->owner('command-owner@example.test', 'Command Shop');
        $staff = User::create([
            'name' => 'พนักงานขาย',
            'email' => 'command-staff@example.test',
            'password' => 'password',
            'current_shop_id' => $shop->id,
            'email_verified_at' => now(),
        ]);
        $member = ShopMember::create([
            'shop_id' => $shop->id,
            'user_id' => $staff->id,
            'role' => 'staff',
            'permissions' => ['inventory.sell'],
            'joined_at' => now(),
        ]);
        $installation = DiscordInstallation::create([
            'shop_id' => $shop->id,
            'installed_by' => $staff->id,
            'guild_id' => 'guild-commands',
            'guild_name' => 'Command Guild',
            'status' => 'connected',
            'installed_at' => now(),
        ]);
        $installation->channels()->create([
            'purpose' => 'commands',
            'channel_id' => 'commands-room',
            'channel_name' => 'คำสั่งทั่วไป',
            'enabled' => true,
        ]);
        DiscordUserLink::create([
            'shop_id' => $shop->id,
            'user_id' => $staff->id,
            'discord_user_id' => 'discord-staff',
            'discord_username' => 'staff',
            'linked_at' => now(),
        ]);
        foreach ([['BOOK1', 'Book#TH01'], ['SELL1', 'Sell#TH01']] as [$tag, $name]) {
            InventoryItem::create([
                'shop_id' => $shop->id,
                'created_by' => $staff->id,
                'tag' => $tag,
                'title' => $name,
                'rank' => 'Gold 2',
                'level' => 50,
                'description' => 'รายละเอียดสาธารณะ',
                'cost' => 1000,
                'list_price' => 2500,
                'status' => 'available',
            ]);
        }

        $this->postJson('/api/v1/discord/interactions', $this->commandInteraction('summary-wrong-room', 'สรุป', [], 'guild-commands', 'other-room', 'discord-staff'))
            ->assertOk()
            ->assertJsonPath('data.content', fn ($content) => str_contains($content, '#คำสั่งทั่วไป เท่านั้น'));
        $this->postJson('/api/v1/discord/interactions', $this->commandInteraction('help-seller', 'ช่วยเหลือ', [], 'guild-commands', 'commands-room', 'discord-staff'))
            ->assertOk()
            ->assertJsonPath('data.content', fn ($content) => str_contains($content, '/ร้าน จอง') && ! str_contains($content, '/ร้าน เพิ่มไอดี'));
        $this->postJson('/api/v1/discord/interactions', $this->commandInteraction('summary-seller', 'สรุป', [], 'guild-commands', 'commands-room', 'discord-staff'))
            ->assertOk()
            ->assertJsonPath('data.content', fn ($content) => str_contains($content, 'พร้อมขาย: 2 รายการ') && ! str_contains($content, 'กำไรเดือนนี้'));
        $this->postJson('/api/v1/discord/interactions', $this->commandInteraction('list-seller', 'รายการ', ['สถานะ' => 'available', 'จำนวน' => 2], 'guild-commands', 'commands-room', 'discord-staff'))
            ->assertOk()
            ->assertJsonPath('data.content', fn ($content) => str_contains($content, '#BOOK1') && str_contains($content, '#SELL1'));
        $this->postJson('/api/v1/discord/interactions', $this->commandInteraction('reserve-seller', 'จอง', ['แท็ก' => '#BOOK1', 'ลูกค้า' => 'ลูกค้าทดสอบ', 'ชั่วโมง' => 12, 'โน้ต' => 'รอยืนยัน'], 'guild-commands', 'commands-room', 'discord-staff'))
            ->assertOk()
            ->assertJsonPath('data.content', fn ($content) => str_contains($content, 'จอง **#BOOK1** สำเร็จ'));
        $this->assertDatabaseHas('inventory_items', ['shop_id' => $shop->id, 'tag' => 'BOOK1', 'status' => 'reserved']);

        $this->postJson('/api/v1/discord/interactions', $this->commandInteraction('note-seller', 'โน้ต', ['แท็ก' => '#BOOK1', 'ข้อความ' => 'ลูกค้าขอเวลาตัดสินใจ'], 'guild-commands', 'commands-room', 'discord-staff'))
            ->assertOk();
        $this->assertDatabaseHas('inventory_items', ['shop_id' => $shop->id, 'tag' => 'BOOK1', 'notes' => 'ลูกค้าขอเวลาตัดสินใจ']);
        $this->postJson('/api/v1/discord/interactions', $this->commandInteraction('release-seller', 'ยกเลิกจอง', ['แท็ก' => '#BOOK1'], 'guild-commands', 'commands-room', 'discord-staff'))
            ->assertOk();
        $this->assertDatabaseHas('inventory_items', ['shop_id' => $shop->id, 'tag' => 'BOOK1', 'status' => 'available']);

        $this->postJson('/api/v1/discord/interactions', $this->commandInteraction('sell-seller', 'ปิดการขาย', ['แท็ก' => '#SELL1', 'ลูกค้า' => 'คุณเอ', 'ราคา' => 2800, 'ไลน์' => 'customer-line', 'รายละเอียด' => 'ส่งมอบแล้ว'], 'guild-commands', 'commands-room', 'discord-staff'))
            ->assertOk()
            ->assertJsonPath('data.content', fn ($content) => str_contains($content, 'ปิดการขาย **#SELL1** สำเร็จ'));
        $this->assertDatabaseHas('inventory_items', ['shop_id' => $shop->id, 'tag' => 'SELL1', 'status' => 'sold']);
        $this->assertDatabaseHas('sales', ['shop_id' => $shop->id, 'sold_price' => 2800, 'created_by' => $staff->id]);
        $this->assertSame('คุณเอ', Sale::query()->firstOrFail()->customer->name);

        $this->postJson('/api/v1/discord/interactions', $this->commandInteraction('add-denied', 'เพิ่มไอดี', ['ชื่อ' => 'Denied#TH01', 'ต้นทุน' => 500, 'ราคา' => 1000], 'guild-commands', 'commands-room', 'discord-staff'))
            ->assertOk()
            ->assertJsonPath('data.content', fn ($content) => str_contains($content, 'ไม่มีสิทธิ์'));
        $this->assertDatabaseMissing('inventory_items', ['shop_id' => $shop->id, 'title' => 'Denied#TH01']);

        $member->update(['permissions' => ['inventory.manage']]);
        $this->postJson('/api/v1/discord/interactions', $this->commandInteraction('add-manager', 'เพิ่มไอดี', ['ชื่อ' => 'Added#TH01', 'ต้นทุน' => 700, 'ราคา' => 1500, 'username' => 'added-login', 'แรงก์' => 'Platinum 1', 'เลเวล' => 88], 'guild-commands', 'commands-room', 'discord-staff'))
            ->assertOk()
            ->assertJsonPath('data.content', fn ($content) => str_contains($content, 'เข้าคลังแล้ว'))
            ->assertJsonPath('data.components.0.components.0.type', 3)
            ->assertJsonPath('data.components.0.components.0.custom_id', fn ($id) => str_starts_with((string) $id, 'gid:menu:rank:'))
            ->assertJsonPath('data.components.1.components.0.custom_id', fn ($id) => str_starts_with((string) $id, 'gid:menu:addmore:'))
            ->assertJsonPath('data.components.1.components.1.style', 5)
            ->assertJsonPath('data.components.1.components.1.label', 'เปิดข้อมูลไอดีใน GamoryID');
        $this->assertDatabaseHas('inventory_items', ['shop_id' => $shop->id, 'title' => 'Added#TH01', 'username' => 'added-login', 'rank' => 'Platinum 1', 'level' => 88]);
        $this->postJson('/api/v1/discord/interactions', $this->commandInteraction('reserve-denied', 'จอง', ['แท็ก' => '#BOOK1'], 'guild-commands', 'commands-room', 'discord-staff'))
            ->assertOk()
            ->assertJsonPath('data.content', fn ($content) => str_contains($content, 'ไม่มีสิทธิ์'));
    }

    public function test_pinned_button_panel_runs_and_opens_modals(): void
    {
        Queue::fake();
        [$owner, $shop] = $this->owner('panel-owner@example.test', 'Panel Shop');
        $installation = DiscordInstallation::create([
            'shop_id' => $shop->id, 'installed_by' => $owner->id, 'guild_id' => 'guild-panel',
            'guild_name' => 'Panel Guild', 'status' => 'connected', 'installed_at' => now(),
        ]);
        $installation->channels()->create([
            'purpose' => 'commands', 'channel_id' => 'panel-room', 'channel_name' => 'คำสั่งทั่วไป', 'enabled' => true,
        ]);
        DiscordUserLink::create([
            'shop_id' => $shop->id, 'user_id' => $owner->id,
            'discord_user_id' => 'discord-panel', 'discord_username' => 'owner', 'linked_at' => now(),
        ]);
        InventoryItem::create([
            'shop_id' => $shop->id, 'created_by' => $owner->id, 'tag' => 'PANL1', 'title' => 'ไอดีในแผง',
            'rank' => 'Gold 1', 'cost' => 100, 'list_price' => 500, 'status' => 'available',
        ]);

        // /ร้าน เมนู — in test mode the panel is echoed back with its buttons
        $this->postJson('/api/v1/discord/interactions', $this->commandInteraction('panel-post', 'เมนู', [], 'guild-panel', 'panel-room', 'discord-panel'))
            ->assertOk()
            ->assertJsonPath('data.components.0.components.0.custom_id', 'gid:menu:add')
            ->assertJsonPath('data.components.2.components.1.custom_id', 'gid:menu:summary');

        // a "direct" button runs immediately
        $this->postJson('/api/v1/discord/interactions', $this->componentInteraction('btn-summary', 'gid:menu:summary', 'guild-panel', 'panel-room', 'discord-panel'))
            ->assertOk()
            ->assertJsonPath('data.content', fn ($c) => str_contains($c, 'สรุปร้าน'));

        // an "input" button replies with a modal (type 9) carrying a tag field
        $this->postJson('/api/v1/discord/interactions', $this->componentInteraction('btn-find', 'gid:menu:find', 'guild-panel', 'panel-room', 'discord-panel'))
            ->assertOk()
            ->assertJsonPath('type', 9)
            ->assertJsonPath('data.custom_id', 'gid:modal:find')
            ->assertJsonPath('data.components.0.components.0.custom_id', 'tag');

        // submitting the reserve modal actually reserves the item
        $this->postJson('/api/v1/discord/interactions', $this->modalInteraction('modal-reserve', 'gid:modal:reserve', [
            'tag' => '#PANL1', 'customer' => 'ลูกค้าแผง', 'hours' => '6',
        ], 'guild-panel', 'panel-room', 'discord-panel'))
            ->assertOk()
            ->assertJsonPath('data.content', fn ($c) => str_contains($c, 'จอง **#PANL1** สำเร็จ'));
        $this->assertDatabaseHas('inventory_items', ['tag' => 'PANL1', 'status' => 'reserved']);

        // the 5-field add modal creates the item; the reply carries a rank
        // dropdown (row 0) and the "add more" button (row 1)
        $this->postJson('/api/v1/discord/interactions', $this->modalInteraction('modal-add', 'gid:modal:add', [
            'title' => 'ไอดีจากปุ่ม', 'cost' => '120', 'price' => '640', 'username' => 'panel-login',
        ], 'guild-panel', 'panel-room', 'discord-panel'))
            ->assertOk()
            ->assertJsonPath('data.content', fn ($c) => str_contains($c, 'เข้าคลังแล้ว'))
            ->assertJsonPath('data.components.0.components.0.type', 3)
            ->assertJsonPath('data.components.0.components.0.custom_id', fn ($id) => str_starts_with((string) $id, 'gid:menu:rank:'))
            ->assertJsonPath('data.components.1.components.0.custom_id', fn ($id) => str_starts_with((string) $id, 'gid:menu:addmore:'));
        $added = InventoryItem::query()->where('shop_id', $shop->id)->where('title', 'ไอดีจากปุ่ม')->firstOrFail();

        // picking a rank from the dropdown sets it in one tap
        $this->postJson('/api/v1/discord/interactions', $this->componentInteraction('sel-rank', "gid:menu:rank:{$added->id}", 'guild-panel', 'panel-room', 'discord-panel', ['Immortal 3']))
            ->assertOk()
            ->assertJsonPath('data.content', fn ($c) => str_contains($c, 'ตั้งแรงก์'));
        $this->assertDatabaseHas('inventory_items', ['id' => $added->id, 'rank' => 'Immortal 3']);

        // the follow-up button opens a second modal for the optional fields (no rank field)
        $this->postJson('/api/v1/discord/interactions', $this->componentInteraction('btn-addmore', "gid:menu:addmore:{$added->id}", 'guild-panel', 'panel-room', 'discord-panel'))
            ->assertOk()
            ->assertJsonPath('type', 9)
            ->assertJsonPath('data.custom_id', "gid:modal:addmore:{$added->id}")
            ->assertJsonPath('data.components.0.components.0.custom_id', 'level');

        // submitting the second modal fills in the extras
        $this->postJson('/api/v1/discord/interactions', $this->modalInteraction('modal-addmore', "gid:modal:addmore:{$added->id}", [
            'level' => '145', 'email' => 'panel@example.test',
        ], 'guild-panel', 'panel-room', 'discord-panel'))
            ->assertOk()
            ->assertJsonPath('data.content', fn ($c) => str_contains($c, 'บันทึกข้อมูลเพิ่มเติม'));
        $this->assertDatabaseHas('inventory_items', ['id' => $added->id, 'level' => 145, 'email' => 'panel@example.test']);

        // a staff member without inventory.manage cannot use the add button
        $staff = User::create(['name' => 'สตาฟ', 'email' => 'panel-staff@example.test', 'password' => 'password', 'current_shop_id' => $shop->id, 'email_verified_at' => now()]);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $staff->id, 'role' => 'staff', 'permissions' => ['inventory.sell'], 'joined_at' => now()]);
        DiscordUserLink::create(['shop_id' => $shop->id, 'user_id' => $staff->id, 'discord_user_id' => 'discord-panel-staff', 'discord_username' => 'staff', 'linked_at' => now()]);
        $this->postJson('/api/v1/discord/interactions', $this->componentInteraction('btn-add', 'gid:menu:add', 'guild-panel', 'panel-room', 'discord-panel-staff'))
            ->assertOk()
            ->assertJsonPath('data.content', fn ($c) => str_contains($c, 'ไม่มีสิทธิ์'));
    }

    public function test_auto_create_reuses_existing_rooms_and_adds_only_the_thai_command_room(): void
    {
        [$owner, $shop] = $this->owner('room-migration-owner@example.test', 'Room Migration Shop');
        config()->set('services.discord.test_bypass', false);
        config()->set('services.discord.application_id', 'application-1');
        config()->set('services.discord.bot_token', 'bot-token');

        $installation = DiscordInstallation::create([
            'shop_id' => $shop->id,
            'installed_by' => $owner->id,
            'guild_id' => 'guild-live',
            'guild_name' => 'Live Guild',
            'status' => 'connected',
            'installed_at' => now(),
        ]);
        foreach (['system', 'sales', 'reservations', 'inventory'] as $purpose) {
            $installation->channels()->create([
                'purpose' => $purpose,
                'channel_id' => "old-{$purpose}",
                'channel_name' => "gamory-{$purpose}",
                'enabled' => true,
            ]);
        }

        Http::fake(function ($request) {
            $url = $request->url();
            if ($request->method() === 'GET') {
                return Http::response([
                    ['id' => 'category-1', 'name' => 'GamoryID', 'type' => 4, 'parent_id' => null],
                    ['id' => 'old-system', 'name' => 'gamory-system', 'type' => 0, 'parent_id' => null],
                    ['id' => 'old-sales', 'name' => 'gamory-sales', 'type' => 0, 'parent_id' => null],
                    ['id' => 'old-reservations', 'name' => 'gamory-reservations', 'type' => 0, 'parent_id' => null],
                    ['id' => 'old-inventory', 'name' => 'gamory-inventory', 'type' => 0, 'parent_id' => null],
                ]);
            }
            if ($request->method() === 'PATCH') {
                $id = basename(parse_url($url, PHP_URL_PATH));

                return Http::response([
                    'id' => $id,
                    'name' => $request->data()['name'],
                    'type' => $id === 'category-1' ? 4 : 0,
                    'parent_id' => $request->data()['parent_id'] ?? null,
                ]);
            }
            if ($request->method() === 'POST') {
                return Http::response([
                    'id' => 'new-commands',
                    'name' => $request->data()['name'],
                    'type' => 0,
                    'parent_id' => $request->data()['parent_id'],
                ]);
            }

            return Http::response([], 200);
        });

        $this->actingAs($owner)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson('/api/v1/discord/channels/auto-create')
            ->assertOk()
            ->assertJsonPath('message', 'สร้างหรือปรับปรุงห้องภาษาไทยของ GamoryID แล้ว');

        $this->assertDatabaseHas('discord_channel_bindings', [
            'discord_installation_id' => $installation->id,
            'purpose' => 'commands',
            'channel_id' => 'new-commands',
            'channel_name' => 'คำสั่งทั่วไป',
        ]);
        $this->assertDatabaseHas('discord_channel_bindings', [
            'discord_installation_id' => $installation->id,
            'purpose' => 'sales',
            'channel_id' => 'old-sales',
            'channel_name' => 'รายการขาย',
        ]);

        $createdRooms = collect(Http::recorded())
            ->filter(fn ($exchange) => $exchange[0]->method() === 'POST')
            ->map(fn ($exchange) => $exchange[0]->data());
        $this->assertCount(1, $createdRooms);
        $this->assertSame('คำสั่งทั่วไป', $createdRooms->first()['name']);
    }

    public function test_auto_create_posts_the_control_panel_into_the_commands_channel(): void
    {
        [$owner, $shop] = $this->owner('panel-provision-owner@example.test', 'Panel Provision Shop');
        config()->set('services.discord.test_bypass', false);
        config()->set('services.discord.application_id', 'application-1');
        config()->set('services.discord.public_key', 'public-key-1');
        config()->set('services.discord.bot_token', 'bot-token');

        DiscordInstallation::create([
            'shop_id' => $shop->id,
            'installed_by' => $owner->id,
            'guild_id' => 'guild-provision',
            'guild_name' => 'Provision Guild',
            'status' => 'connected',
            'installed_at' => now(),
        ]);

        Http::fake(function ($request) {
            if ($request->method() === 'GET') {
                return Http::response([]);
            }
            if ($request->method() === 'POST' && str_contains($request->url(), '/messages')) {
                return Http::response(['id' => 'panel-message-1']);
            }

            return Http::response(['id' => 'new-'.basename(parse_url($request->url(), PHP_URL_PATH)), 'name' => $request->data()['name'] ?? 'x', 'type' => 0], 200);
        });

        $this->actingAs($owner)->withHeader('X-Shop-Id', (string) $shop->id)
            ->postJson('/api/v1/discord/channels/auto-create')
            ->assertOk()
            ->assertJsonPath('message', 'สร้างห้องภาษาไทยและโพสต์แผงปุ่มควบคุมในห้องคำสั่งทั่วไปแล้ว');

        $panelPost = collect(Http::recorded())
            ->first(fn ($exchange) => $exchange[0]->method() === 'POST' && str_contains($exchange[0]->url(), '/messages'));
        $this->assertNotNull($panelPost, 'expected the control panel to be posted to a channel');
        $this->assertStringContainsString('แผงควบคุมร้าน', $panelPost[0]->data()['content']);
        $this->assertSame('gid:menu:add', $panelPost[0]->data()['components'][0]['components'][0]['custom_id']);

        $pinned = collect(Http::recorded())
            ->contains(fn ($exchange) => $exchange[0]->method() === 'PUT' && str_contains($exchange[0]->url(), '/pins/panel-message-1'));
        $this->assertTrue($pinned, 'expected the posted panel to be pinned');
    }

    private function interaction(string $id, string $subcommand, string $optionName, string $value, string $guildId, string $channelId): array
    {
        return $this->commandInteraction($id, $subcommand, [$optionName => $value], $guildId, $channelId);
    }

    private function componentInteraction(string $id, string $customId, string $guildId, string $channelId, string $discordUserId, array $values = []): array
    {
        return [
            'id' => $id,
            'type' => 3,
            'guild_id' => $guildId,
            'channel_id' => $channelId,
            'member' => ['permissions' => '32', 'user' => ['id' => $discordUserId, 'username' => 'merchant', 'global_name' => 'Merchant']],
            'data' => array_filter([
                'custom_id' => $customId,
                'component_type' => $values === [] ? 2 : 3,
                'values' => $values === [] ? null : $values,
            ], fn ($value) => $value !== null),
        ];
    }

    private function modalInteraction(string $id, string $customId, array $fields, string $guildId, string $channelId, string $discordUserId): array
    {
        return [
            'id' => $id,
            'type' => 5,
            'guild_id' => $guildId,
            'channel_id' => $channelId,
            'member' => ['permissions' => '32', 'user' => ['id' => $discordUserId, 'username' => 'merchant', 'global_name' => 'Merchant']],
            'data' => [
                'custom_id' => $customId,
                'components' => collect($fields)->map(fn ($value, $name) => [
                    'type' => 1,
                    'components' => [['type' => 4, 'custom_id' => $name, 'value' => $value]],
                ])->values()->all(),
            ],
        ];
    }

    private function commandInteraction(string $id, string $subcommand, array $options, string $guildId, string $channelId, string $discordUserId = 'discord-user-1'): array
    {
        return [
            'id' => $id,
            'type' => 2,
            'guild_id' => $guildId,
            'channel_id' => $channelId,
            'guild' => ['name' => 'Gamory Community'],
            'member' => [
                'permissions' => '32',
                'user' => ['id' => $discordUserId, 'username' => 'merchant', 'global_name' => 'Merchant'],
            ],
            'data' => [
                'name' => 'ร้าน',
                'options' => [[
                    'type' => 1,
                    'name' => $subcommand,
                    'options' => collect($options)->map(fn ($value, $name) => [
                        'type' => is_int($value) ? 4 : (is_float($value) ? 10 : 3),
                        'name' => $name,
                        'value' => $value,
                    ])->values()->all(),
                ]],
            ],
        ];
    }

    private function owner(string $email, string $name): array
    {
        $shop = Shop::create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.uniqid(),
            'status' => 'trialing',
            'trial_ends_at' => now()->addMonth(),
        ]);
        $user = User::create([
            'name' => 'เจ้าของร้าน',
            'email' => $email,
            'password' => 'password',
            'current_shop_id' => $shop->id,
            'email_verified_at' => now(),
        ]);
        ShopMember::create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'permissions' => [],
            'joined_at' => now(),
        ]);

        return [$user, $shop];
    }
}
