<?php

namespace App\Services\Discord;

use App\Models\ActivityLog;
use App\Models\DiscordCommandLog;
use App\Models\DiscordInstallation;
use App\Models\DiscordLinkCode;
use App\Models\DiscordSetupCode;
use App\Models\DiscordUserLink;
use App\Models\ShopMember;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class DiscordCommandDispatcher
{
    public function __construct(private readonly DiscordShopCommandHandler $shopCommands) {}

    public function handle(array $interaction): array
    {
        $startedAt = hrtime(true);
        $command = $this->commandName($interaction);
        $context = ['shop_id' => null, 'user_id' => null, 'status' => 'denied'];

        try {
            [$response, $context] = match ($command) {
                'ร้าน.ตั้งค่า', 'gid.setup' => $this->setup($interaction),
                'ร้าน.เชื่อมบัญชี', 'gid.link' => $this->link($interaction),
                'gid.find' => $this->shopCommand($interaction, 'ร้าน.ค้นหา'),
                default => $this->shopCommands->supports($command)
                    ? $this->shopCommand($interaction, $command)
                    : [$this->ephemeral('ไม่พบคำสั่งนี้ กรุณาเลือกคำสั่งจากรายการของ Discord'), $context],
            };
        } catch (Throwable $error) {
            report($error);
            $response = $this->ephemeral('คำสั่งยังทำงานไม่สำเร็จ กรุณาลองใหม่อีกครั้ง');
            $context['status'] = 'error';
        }

        $this->log($interaction, $command, $context, (int) ((hrtime(true) - $startedAt) / 1_000_000));

        return $response;
    }

    private function setup(array $interaction): array
    {
        if (! $this->memberCanManageGuild($interaction)) {
            return [$this->ephemeral('ต้องมีสิทธิ์จัดการเซิร์ฟเวอร์ก่อนเชื่อมร้าน'), ['shop_id' => null, 'user_id' => null, 'status' => 'denied']];
        }

        $guildId = (string) ($interaction['guild_id'] ?? '');
        $guildName = trim((string) ($interaction['guild']['name'] ?? '')) ?: 'เซิร์ฟเวอร์ Discord';
        $code = $this->optionValue($interaction, 'รหัส', 'code');
        if ($guildId === '' || $code === '') {
            return [$this->ephemeral('ข้อมูลเซิร์ฟเวอร์หรือรหัสเชื่อมร้านไม่ครบ'), ['shop_id' => null, 'user_id' => null, 'status' => 'denied']];
        }

        $context = DB::transaction(function () use ($code, $guildId, $guildName) {
            $setupCode = DiscordSetupCode::query()
                ->where('token_hash', hash('sha256', mb_strtoupper(trim($code))))
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();
            if (! $setupCode) {
                return null;
            }

            $otherShop = DiscordInstallation::query()
                ->where('guild_id', $guildId)
                ->where('shop_id', '!=', $setupCode->shop_id)
                ->exists();
            if ($otherShop) {
                return ['conflict' => true, 'shop_id' => $setupCode->shop_id, 'user_id' => $setupCode->created_by];
            }

            DiscordInstallation::query()->updateOrCreate(
                ['shop_id' => $setupCode->shop_id],
                [
                    'installed_by' => $setupCode->created_by,
                    'guild_id' => $guildId,
                    'guild_name' => $guildName,
                    'status' => 'connected',
                    'bot_permissions' => ['manage_channels', 'view_channels', 'send_messages', 'embed_links'],
                    'installed_at' => now(),
                    'last_verified_at' => now(),
                ],
            );
            $setupCode->update(['used_at' => now()]);
            ActivityLog::create([
                'shop_id' => $setupCode->shop_id,
                'user_id' => $setupCode->created_by,
                'event' => 'discord.connected',
                'metadata' => ['guild_id' => $guildId, 'guild_name' => $guildName],
                'created_at' => now(),
            ]);

            return ['shop_id' => $setupCode->shop_id, 'user_id' => $setupCode->created_by, 'status' => 'success'];
        });

        if (! $context) {
            return [$this->ephemeral('รหัสเชื่อมร้านไม่ถูกต้องหรือหมดอายุแล้ว กรุณาสร้างรหัสใหม่'), ['shop_id' => null, 'user_id' => null, 'status' => 'denied']];
        }
        if ($context['conflict'] ?? false) {
            return [$this->ephemeral('เซิร์ฟเวอร์นี้เชื่อมกับร้านอื่นอยู่แล้ว กรุณายกเลิกการเชื่อมต่อเดิมก่อน'), [...$context, 'status' => 'conflict']];
        }

        return [$this->ephemeral('เชื่อมเซิร์ฟเวอร์กับร้านสำเร็จ กลับไปหน้า GamoryID แล้วกด “ตรวจสอบสถานะ” ได้เลย'), $context];
    }

    private function link(array $interaction): array
    {
        $guildId = (string) ($interaction['guild_id'] ?? '');
        $discordUserId = $this->discordUserId($interaction);
        $username = $this->discordUsername($interaction);
        $code = $this->optionValue($interaction, 'รหัส', 'code');
        $installation = DiscordInstallation::query()->where('guild_id', $guildId)->where('status', 'connected')->first();
        if (! $installation) {
            return [$this->ephemeral('เซิร์ฟเวอร์นี้ยังไม่ได้เชื่อมกับร้าน GamoryID'), ['shop_id' => null, 'user_id' => null, 'status' => 'denied']];
        }
        if ($message = $this->commandChannelError($interaction, $installation)) {
            return [$this->ephemeral($message), ['shop_id' => $installation->shop_id, 'user_id' => null, 'status' => 'wrong_channel']];
        }

        $link = DB::transaction(function () use ($code, $installation, $discordUserId, $username) {
            $linkCode = DiscordLinkCode::query()
                ->where('shop_id', $installation->shop_id)
                ->where('token_hash', hash('sha256', mb_strtoupper(trim($code))))
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();
            if (! $linkCode) {
                return null;
            }

            DiscordUserLink::query()->updateOrCreate(
                ['shop_id' => $installation->shop_id, 'user_id' => $linkCode->user_id],
                ['discord_user_id' => $discordUserId, 'discord_username' => $username, 'linked_at' => now()],
            );
            $linkCode->update(['used_at' => now()]);

            return $linkCode;
        });

        if (! $link) {
            return [$this->ephemeral('รหัสเชื่อมบัญชีไม่ถูกต้องหรือหมดอายุแล้ว กรุณาสร้างรหัสใหม่'), ['shop_id' => $installation->shop_id, 'user_id' => null, 'status' => 'denied']];
        }

        ActivityLog::create([
            'shop_id' => $installation->shop_id,
            'user_id' => $link->user_id,
            'event' => 'discord.user_linked',
            'metadata' => ['discord_user_id' => $discordUserId],
            'created_at' => now(),
        ]);

        return [$this->ephemeral('เชื่อมบัญชีสำเร็จ ใช้ `/ร้าน ช่วยเหลือ` เพื่อดูคำสั่งที่บัญชีของคุณมีสิทธิ์ใช้งานได้เลย'), ['shop_id' => $installation->shop_id, 'user_id' => $link->user_id, 'status' => 'success']];
    }

    private function shopCommand(array $interaction, string $command): array
    {
        $guildId = (string) ($interaction['guild_id'] ?? '');
        $discordUserId = $this->discordUserId($interaction);
        $installation = DiscordInstallation::query()
            ->where('guild_id', $guildId)
            ->where('status', 'connected')
            ->with('shop')
            ->first();
        if (! $installation) {
            return [$this->ephemeral('เซิร์ฟเวอร์นี้ยังไม่ได้เชื่อมกับร้าน GamoryID'), ['shop_id' => null, 'user_id' => null, 'status' => 'denied']];
        }
        if ($message = $this->commandChannelError($interaction, $installation)) {
            return [$this->ephemeral($message), ['shop_id' => $installation->shop_id, 'user_id' => null, 'status' => 'wrong_channel']];
        }

        $link = DiscordUserLink::query()
            ->where('shop_id', $installation->shop_id)
            ->where('discord_user_id', $discordUserId)
            ->with('user')
            ->first();
        if (! $link || ! $link->user) {
            return [$this->ephemeral('บัญชี Discord นี้ยังไม่ได้เชื่อมกับสมาชิกในร้าน กรุณาสร้างรหัสจากหน้า Discord ใน GamoryID แล้วใช้ `/ร้าน เชื่อมบัญชี` ในห้องคำสั่งทั่วไป'), ['shop_id' => $installation->shop_id, 'user_id' => null, 'status' => 'denied']];
        }

        $member = ShopMember::query()
            ->where('shop_id', $installation->shop_id)
            ->where('user_id', $link->user_id)
            ->first();
        if (! $member) {
            return [$this->ephemeral('บัญชีนี้ไม่ได้เป็นสมาชิกของร้านแล้ว กรุณาติดต่อเจ้าของร้าน'), ['shop_id' => $installation->shop_id, 'user_id' => $link->user_id, 'status' => 'denied']];
        }
        if (! $this->shopCommands->canRun($command, $member)) {
            return [$this->ephemeral($this->shopCommands->permissionDeniedMessage($command)), ['shop_id' => $installation->shop_id, 'user_id' => $link->user_id, 'status' => 'denied']];
        }

        try {
            $result = $this->shopCommands->execute($command, $interaction, $installation, $link, $member);
        } catch (HttpExceptionInterface $error) {
            $message = $error->getStatusCode() < 500 && $error->getMessage() !== ''
                ? $error->getMessage()
                : 'คำสั่งยังทำงานไม่สำเร็จ กรุณาลองใหม่อีกครั้ง';

            return [$this->ephemeral($message), ['shop_id' => $installation->shop_id, 'user_id' => $link->user_id, 'status' => 'denied']];
        }

        return [
            $this->ephemeral($result['content'], $result['link'] ?? null),
            ['shop_id' => $installation->shop_id, 'user_id' => $link->user_id, 'status' => $result['status']],
        ];
    }

    private function commandName(array $interaction): string
    {
        $root = (string) ($interaction['data']['name'] ?? 'unknown');
        $subcommand = (string) ($interaction['data']['options'][0]['name'] ?? 'unknown');

        return "{$root}.{$subcommand}";
    }

    private function optionValue(array $interaction, string ...$names): string
    {
        $options = $interaction['data']['options'][0]['options'] ?? [];
        foreach ($options as $option) {
            if (in_array(($option['name'] ?? null), $names, true)) {
                return (string) ($option['value'] ?? '');
            }
        }

        return '';
    }

    private function memberCanManageGuild(array $interaction): bool
    {
        return (((int) ($interaction['member']['permissions'] ?? 0)) & 32) === 32;
    }

    private function discordUserId(array $interaction): string
    {
        return (string) ($interaction['member']['user']['id'] ?? $interaction['user']['id'] ?? '');
    }

    private function discordUsername(array $interaction): string
    {
        return (string) ($interaction['member']['user']['global_name'] ?? $interaction['member']['user']['username'] ?? 'ผู้ใช้ Discord');
    }

    private function commandChannelError(array $interaction, DiscordInstallation $installation): ?string
    {
        $channel = $installation->channels()
            ->where('purpose', 'commands')
            ->where('enabled', true)
            ->first();
        if (! $channel) {
            return 'ร้านนี้ยังไม่มีห้องคำสั่งทั่วไป กรุณาให้ผู้ดูแลกด “ซิงก์ห้องและคำสั่ง” ในหน้า Discord ของ GamoryID ก่อน';
        }

        if ((string) ($interaction['channel_id'] ?? '') !== $channel->channel_id) {
            return "กรุณาใช้คำสั่งนี้ในห้อง #{$channel->channel_name} เท่านั้น";
        }

        return null;
    }

    /**
     * @param  array{label: string, url: string}|null  $link
     */
    private function ephemeral(string $content, ?array $link = null): array
    {
        $data = [
            'content' => $content,
            'flags' => 64,
            'allowed_mentions' => ['parse' => []],
        ];
        if ($link && filter_var($link['url'] ?? null, FILTER_VALIDATE_URL) && preg_match('#^https?://#', (string) $link['url'])) {
            $data['components'] = [[
                'type' => 1,
                'components' => [[
                    'type' => 2,
                    'style' => 5,
                    'label' => mb_substr($link['label'], 0, 80),
                    'url' => $link['url'],
                ]],
            ]];
        }

        return ['type' => 4, 'data' => $data];
    }

    private function log(array $interaction, string $command, array $context, int $latencyMs): void
    {
        try {
            DiscordCommandLog::create([
                'interaction_id' => (string) ($interaction['id'] ?? uniqid('local-', true)),
                'shop_id' => $context['shop_id'] ?? null,
                'user_id' => $context['user_id'] ?? null,
                'discord_user_id' => $this->discordUserId($interaction) ?: null,
                'command' => $command,
                'status' => $context['status'] ?? 'unknown',
                'latency_ms' => $latencyMs,
                'created_at' => now(),
            ]);
        } catch (QueryException) {
            // Discord can retry the same signed interaction. The unique ID keeps the audit idempotent.
        }
    }
}
