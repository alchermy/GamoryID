<?php

namespace App\Services\Discord;

use App\Models\DiscordChannelBinding;
use App\Models\Shop;
use RuntimeException;

class DiscordNotificationService
{
    public function __construct(private readonly DiscordApiClient $client) {}

    public function send(Shop $shop, string $purpose, string $title, string $description): bool
    {
        $installation = $shop->discordInstallation()->where('status', 'connected')->first();
        if (! $installation) {
            return false;
        }

        $binding = DiscordChannelBinding::query()
            ->where('discord_installation_id', $installation->id)
            ->where('purpose', $purpose)
            ->where('enabled', true)
            ->first();
        if (! $binding) {
            return false;
        }

        if ($this->client->isTestMode()) {
            return true;
        }

        $this->client->sendMessage($binding->channel_id, [
            'embeds' => [[
                'title' => $title,
                'description' => $description,
                'color' => 748543,
                'footer' => ['text' => 'GamoryID · ไม่ส่งชื่อผู้ใช้ รหัสผ่าน ต้นทุน หรือโน้ตภายใน'],
                'timestamp' => now()->toIso8601String(),
            ]],
        ]);

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
