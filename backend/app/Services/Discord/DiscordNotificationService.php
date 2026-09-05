<?php

namespace App\Services\Discord;

use App\Models\DiscordChannelBinding;
use App\Models\Shop;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class DiscordNotificationService
{
    public function __construct(private readonly DiscordApiClient $client) {}

    /**
     * @param  array{label: string, url: string}|null  $link  rendered as a Discord link button under the embed
     */
    public function send(Shop $shop, string $purpose, string $title, string $description, ?array $link = null): bool
    {
        $log = Log::channel('discord')->withContext(['shop_id' => $shop->id, 'purpose' => $purpose]);

        $installation = $shop->discordInstallation()->where('status', 'connected')->first();
        if (! $installation) {
            $log->debug('ข้ามการแจ้งเตือน Discord: ร้านยังไม่ได้เชื่อมต่อ', ['title' => $title]);

            return false;
        }

        $binding = DiscordChannelBinding::query()
            ->where('discord_installation_id', $installation->id)
            ->where('purpose', $purpose)
            ->where('enabled', true)
            ->first();
        if (! $binding) {
            $log->warning('ข้ามการแจ้งเตือน Discord: ยังไม่ได้ตั้งค่าห้องสำหรับหมวดนี้', ['title' => $title]);

            return false;
        }

        if ($this->client->isTestMode()) {
            $log->debug('โหมดทดสอบ: จำลองการส่งแจ้งเตือน Discord', ['channel_id' => $binding->channel_id, 'title' => $title]);

            return true;
        }

        $payload = [
            'embeds' => [[
                'title' => $title,
                'description' => $description,
                'color' => 748543,
                'footer' => ['text' => 'GamoryID · ไม่ส่งชื่อผู้ใช้ รหัสผ่าน ต้นทุน หรือโน้ตภายใน'],
                'timestamp' => now()->toIso8601String(),
            ]],
        ];
        if ($link && filter_var($link['url'] ?? null, FILTER_VALIDATE_URL) && preg_match('#^https?://#', (string) $link['url'])) {
            $payload['components'] = [[
                'type' => 1,
                'components' => [[
                    'type' => 2,
                    'style' => 5,
                    'label' => mb_substr($link['label'], 0, 80),
                    'url' => $link['url'],
                ]],
            ]];
        }

        try {
            $this->client->sendMessage($binding->channel_id, $payload);
        } catch (Throwable $exception) {
            $log->error('ส่งแจ้งเตือน Discord ไม่สำเร็จ', [
                'channel_id' => $binding->channel_id,
                'title' => $title,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $log->info('ส่งแจ้งเตือน Discord แล้ว', ['channel_id' => $binding->channel_id, 'title' => $title]);

        return true;
    }

    public function sendTest(Shop $shop, string $purpose = 'system'): void
    {
        if (! $shop->discordInstallation()->where('status', 'connected')->exists()) {
            throw new RuntimeException('ร้านนี้ยังไม่ได้เชื่อมต่อ Discord');
        }

        if (! $this->send(
            $shop,
            $purpose,
            'GamoryID เชื่อมต่อสำเร็จ',
            "ข้อความทดสอบจากร้าน **{$shop->name}**\nห้องนี้พร้อมรับการแจ้งเตือนแล้ว",
        )) {
            throw new RuntimeException('กรุณาเลือกห้องแจ้งเตือนก่อนทดสอบ');
        }
    }
}
