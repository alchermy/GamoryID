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

    public function send(Shop $shop, string $purpose, string $title, string $description): bool
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

        try {
            $this->client->sendMessage($binding->channel_id, [
                'embeds' => [[
                    'title' => $title,
                    'description' => $description,
                    'color' => 748543,
                    'footer' => ['text' => 'GamoryID · ไม่ส่งชื่อผู้ใช้ รหัสผ่าน ต้นทุน หรือโน้ตภายใน'],
                    'timestamp' => now()->toIso8601String(),
                ]],
            ]);
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
