<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiscordInstallation;
use App\Models\DiscordUserLink;
use App\Services\AuditLogger;
use App\Services\CurrentShop;
use App\Services\Discord\DiscordApiClient;
use App\Services\Discord\DiscordCodeService;
use App\Services\Discord\DiscordNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class DiscordSettingsController extends Controller
{
    private const CHANNELS = [
        'commands' => ['name' => 'คำสั่งทั่วไป', 'label' => 'คำสั่งทั่วไป'],
        'system' => ['name' => 'ระบบและข้อผิดพลาด', 'label' => 'ระบบและข้อผิดพลาด'],
        'sales' => ['name' => 'รายการขาย', 'label' => 'รายการขาย'],
        'reservations' => ['name' => 'รายการจอง', 'label' => 'รายการจอง'],
        'inventory' => ['name' => 'คลังไอดี', 'label' => 'คลังไอดี'],
    ];

    public function show(Request $request, CurrentShop $currentShop, DiscordApiClient $discord)
    {
        $shop = $currentShop->from($request);
        $installation = DiscordInstallation::query()
            ->where('shop_id', $shop->id)
            ->with('channels')
            ->first();
        $availableChannels = [];
        $syncError = null;

        if ($installation) {
            if ($discord->isTestMode()) {
                $availableChannels = $installation->channels->map(fn ($channel) => [
                    'id' => $channel->channel_id,
                    'name' => $channel->channel_name,
                ])->values()->all();
            } elseif ($discord->isConfigured()) {
                try {
                    $availableChannels = $discord->listTextChannels($installation->guild_id);
                    $installation->update(['last_verified_at' => now(), 'status' => 'connected']);
                } catch (Throwable) {
                    $syncError = 'ยังโหลดรายชื่อห้องจาก Discord ไม่สำเร็จ คุณยังใช้ค่าที่บันทึกไว้ได้';
                }
            }
        }

        $userLink = DiscordUserLink::query()
            ->where('shop_id', $shop->id)
            ->where('user_id', $request->user()->id)
            ->first();

        return response()->json(['data' => [
            'configured' => $discord->isConfigured(),
            'test_mode' => $discord->isTestMode(),
            'connected' => (bool) $installation,
            'installation' => $installation ? [
                'guild_id' => $installation->guild_id,
                'guild_name' => $installation->guild_name,
                'status' => $installation->status,
                'installed_at' => $installation->installed_at,
                'last_verified_at' => $installation->last_verified_at,
                'channels' => $installation->channels->map(fn ($channel) => [
                    'purpose' => $channel->purpose,
                    'channel_id' => $channel->channel_id,
                    'channel_name' => $channel->channel_name,
                    'enabled' => $channel->enabled,
                ])->values(),
            ] : null,
            'available_channels' => $availableChannels,
            'channel_sync_error' => $syncError,
            'user_link' => $userLink ? [
                'linked' => true,
                'discord_username' => $userLink->discord_username,
                'linked_at' => $userLink->linked_at,
            ] : ['linked' => false, 'discord_username' => null, 'linked_at' => null],
            'purposes' => collect(self::CHANNELS)->map(fn ($definition, $purpose) => [
                'value' => $purpose,
                'label' => $definition['label'],
            ])->values(),
        ]]);
    }

    public function setupCode(Request $request, CurrentShop $currentShop, DiscordApiClient $discord, DiscordCodeService $codes)
    {
        $shop = $currentShop->from($request);
        if (! $discord->isConfigured()) {
            return response()->json(['message' => 'ยังไม่ได้ตั้งค่า Discord App กรุณาใส่คีย์ในเซิร์ฟเวอร์ก่อนเชื่อมต่อจริง'], 503);
        }

        try {
            $discord->registerCommands();
        } catch (Throwable) {
            return response()->json(['message' => 'ยังเชื่อมต่อ Discord ไม่สำเร็จ กรุณาตรวจสอบ Application ID และ Bot Token'], 502);
        }
        $issued = $codes->issueSetupCode($shop, $request->user());

        return response()->json(['data' => [
            'code' => $issued['code'],
            'expires_at' => $issued['record']->expires_at,
            'install_url' => $discord->installUrl(),
        ]], 201);
    }

    public function linkCode(Request $request, CurrentShop $currentShop, DiscordCodeService $codes)
    {
        $shop = $currentShop->from($request);
        if (! $shop->discordInstallation()->where('status', 'connected')->exists()) {
            return response()->json(['message' => 'ร้านนี้ยังไม่ได้เชื่อมต่อ Discord'], 409);
        }

        $issued = $codes->issueLinkCode($shop, $request->user());

        return response()->json(['data' => [
            'code' => $issued['code'],
            'expires_at' => $issued['record']->expires_at,
        ]], 201);
    }

    public function demoConnect(Request $request, CurrentShop $currentShop, DiscordApiClient $discord, AuditLogger $audit)
    {
        if (! $discord->isTestMode()) {
            abort(404);
        }

        $shop = $currentShop->from($request);
        $installation = DB::transaction(function () use ($shop, $request) {
            $installation = DiscordInstallation::query()->updateOrCreate(
                ['shop_id' => $shop->id],
                [
                    'installed_by' => $request->user()->id,
                    'guild_id' => "demo-{$shop->id}",
                    'guild_name' => "{$shop->name} Community",
                    'status' => 'connected',
                    'bot_permissions' => ['demo'],
                    'installed_at' => now(),
                    'last_verified_at' => now(),
                ],
            );
            foreach (self::CHANNELS as $purpose => $definition) {
                $installation->channels()->updateOrCreate(
                    ['purpose' => $purpose],
                    [
                        'channel_id' => "demo-{$shop->id}-{$purpose}",
                        'channel_name' => $definition['name'],
                        'enabled' => true,
                    ],
                );
            }
            DiscordUserLink::query()->updateOrCreate(
                ['shop_id' => $shop->id, 'user_id' => $request->user()->id],
                [
                    'discord_user_id' => "demo-user-{$request->user()->id}",
                    'discord_username' => $request->user()->name.' (Demo)',
                    'linked_at' => now(),
                ],
            );

            return $installation;
        });
        $audit->record($request, $shop, 'discord.demo_connected', $installation);

        return response()->json(['message' => 'เปิดโหมดจำลอง Discord แล้ว']);
    }

    public function autoCreateChannels(Request $request, CurrentShop $currentShop, DiscordApiClient $discord, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $installation = $shop->discordInstallation()->where('status', 'connected')->firstOrFail();

        if ($discord->isTestMode()) {
            $created = collect(self::CHANNELS)->map(fn ($definition, $purpose) => [
                'purpose' => $purpose,
                'id' => "demo-{$installation->shop_id}-{$purpose}",
                'name' => $definition['name'],
            ])->values();
        } else {
            $discord->registerCommands();
            $guildChannels = collect($discord->listGuildChannels($installation->guild_id));
            $category = $guildChannels->first(fn (array $channel) => $channel['type'] === 4 && in_array($channel['name'], ['จัดการร้าน GamoryID', 'GamoryID'], true));
            if ($category) {
                if ($category['name'] !== 'จัดการร้าน GamoryID') {
                    $category = $discord->updateChannel($category['id'], ['name' => 'จัดการร้าน GamoryID']);
                }
            } else {
                $category = $discord->createCategory($installation->guild_id, 'จัดการร้าน GamoryID');
            }

            $created = collect();
            foreach (self::CHANNELS as $purpose => $definition) {
                $binding = $installation->channels()->where('purpose', $purpose)->first();
                $channel = $binding
                    ? $guildChannels->firstWhere('id', $binding->channel_id)
                    : $guildChannels->first(fn (array $candidate) => $candidate['type'] === 0 && $candidate['name'] === $definition['name']);
                if ($channel) {
                    $channel = $discord->updateChannel($channel['id'], [
                        'name' => $definition['name'],
                        'parent_id' => (string) $category['id'],
                    ]);
                } else {
                    $channel = $discord->createTextChannel($installation->guild_id, $definition['name'], (string) $category['id']);
                }
                $created->push(['purpose' => $purpose, 'id' => (string) $channel['id'], 'name' => (string) $channel['name']]);
            }
        }

        DB::transaction(function () use ($installation, $created) {
            foreach ($created as $channel) {
                $installation->channels()->updateOrCreate(
                    ['purpose' => $channel['purpose']],
                    [
                        'channel_id' => $channel['id'],
                        'channel_name' => $channel['name'],
                        'enabled' => true,
                    ],
                );
            }
        });
        $audit->record($request, $shop, 'discord.channels_created', $installation, ['purposes' => array_keys(self::CHANNELS)]);

        return response()->json(['message' => 'สร้างหรือปรับปรุงห้องภาษาไทยของ GamoryID แล้ว']);
    }

    public function updateChannels(Request $request, CurrentShop $currentShop, DiscordApiClient $discord, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $installation = $shop->discordInstallation()->where('status', 'connected')->firstOrFail();
        $data = $request->validate([
            'channels' => ['required', 'array', 'size:5'],
            'channels.*.purpose' => ['required', Rule::in(array_keys(self::CHANNELS)), 'distinct'],
            'channels.*.channel_id' => ['required', 'string', 'max:32'],
        ]);

        $available = $discord->isTestMode()
            ? $installation->channels()->get()->mapWithKeys(fn ($channel) => [$channel->channel_id => $channel->channel_name])
            : collect($discord->listTextChannels($installation->guild_id))->keyBy('id')->map(fn ($channel) => $channel['name']);

        DB::transaction(function () use ($data, $available, $installation) {
            foreach ($data['channels'] as $channel) {
                if (! $available->has($channel['channel_id'])) {
                    throw new RuntimeException('พบห้อง Discord ที่ไม่อยู่ในเซิร์ฟเวอร์นี้');
                }
                $installation->channels()->updateOrCreate(
                    ['purpose' => $channel['purpose']],
                    [
                        'channel_id' => $channel['channel_id'],
                        'channel_name' => (string) $available->get($channel['channel_id']),
                        'enabled' => true,
                    ],
                );
            }
        });
        $audit->record($request, $shop, 'discord.channels_updated', $installation, ['purposes' => array_column($data['channels'], 'purpose')]);

        return response()->json(['message' => 'บันทึกห้องคำสั่งและห้องแจ้งเตือนแล้ว']);
    }

    public function testNotification(Request $request, CurrentShop $currentShop, DiscordNotificationService $notifications, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $data = $request->validate(['purpose' => ['nullable', Rule::in(array_keys(self::CHANNELS))]]);
        $purpose = $data['purpose'] ?? 'system';
        $notifications->sendTest($shop, $purpose);
        $audit->record($request, $shop, 'discord.test_notification_sent', $shop->discordInstallation, ['purpose' => $purpose]);

        return response()->json(['message' => 'ส่งข้อความทดสอบแล้ว']);
    }

    public function disconnect(Request $request, CurrentShop $currentShop, DiscordApiClient $discord, AuditLogger $audit)
    {
        $shop = $currentShop->from($request);
        $installation = $shop->discordInstallation()->firstOrFail();
        $isDemoInstallation = str_starts_with($installation->guild_id, 'demo-');
        if (! $discord->isTestMode() && ! $isDemoInstallation) {
            $discord->leaveGuild($installation->guild_id);
        }
        $audit->record($request, $shop, 'discord.disconnected', $installation, ['guild_id' => $installation->guild_id]);
        $installation->delete();

        return response()->json(['message' => 'ยกเลิกการเชื่อมต่อ Discord แล้ว']);
    }
}
