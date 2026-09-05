<?php

namespace App\Services\Discord;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DiscordApiClient
{
    private const API_BASE = 'https://discord.com/api/v10';

    public function isConfigured(): bool
    {
        return filled(config('services.discord.application_id'))
            && filled(config('services.discord.public_key'))
            && filled(config('services.discord.bot_token'));
    }

    public function isTestMode(): bool
    {
        return app()->environment(['local', 'testing'])
            && (bool) config('services.discord.test_bypass');
    }

    public function installUrl(): string
    {
        $applicationId = (string) config('services.discord.application_id');
        if ($applicationId === '') {
            throw new RuntimeException('ยังไม่ได้ตั้งค่า Discord Application ID');
        }

        return 'https://discord.com/oauth2/authorize?'.http_build_query([
            'client_id' => $applicationId,
            'scope' => 'bot applications.commands',
            // Manage Channels, View Channels, Send Messages and Embed Links.
            'permissions' => '19472',
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function listTextChannels(string $guildId): array
    {
        return collect($this->listGuildChannels($guildId))
            ->filter(fn (array $channel) => (int) ($channel['type'] ?? -1) === 0)
            ->map(fn (array $channel) => [
                'id' => (string) $channel['id'],
                'name' => (string) $channel['name'],
            ])->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function listGuildChannels(string $guildId): array
    {
        return collect($this->request()->get("/guilds/{$guildId}/channels")->throw()->json())
            ->map(fn (array $channel) => [
                'id' => (string) $channel['id'],
                'name' => (string) $channel['name'],
                'type' => (int) $channel['type'],
                'parent_id' => isset($channel['parent_id']) ? (string) $channel['parent_id'] : null,
            ])->values()->all();
    }

    public function createCategory(string $guildId, string $name): array
    {
        return $this->request()->post("/guilds/{$guildId}/channels", [
            'name' => $name,
            'type' => 4,
        ])->throw()->json();
    }

    public function createTextChannel(string $guildId, string $name, ?string $parentId = null): array
    {
        return $this->request()->post("/guilds/{$guildId}/channels", array_filter([
            'name' => $name,
            'type' => 0,
            'parent_id' => $parentId,
        ], fn ($value) => $value !== null))->throw()->json();
    }

    public function updateChannel(string $channelId, array $changes): array
    {
        return $this->request()->patch("/channels/{$channelId}", $changes)->throw()->json();
    }

    public function sendMessage(string $channelId, array $payload): void
    {
        $this->request()->post("/channels/{$channelId}/messages", [
            ...$payload,
            'allowed_mentions' => ['parse' => []],
        ])->throw();
    }

    public function leaveGuild(string $guildId): void
    {
        $this->request()->delete("/users/@me/guilds/{$guildId}")->throw();
    }

    public function registerCommands(): void
    {
        $applicationId = (string) config('services.discord.application_id');
        if ($applicationId === '') {
            throw new RuntimeException('ยังไม่ได้ตั้งค่า Discord Application ID');
        }

        $this->request()->put("/applications/{$applicationId}/commands", $this->commandDefinitions())->throw();
    }

    /** @return array<int, array<string, mixed>> */
    private function commandDefinitions(): array
    {
        return [[
            'name' => 'ร้าน',
            'description' => 'จัดการร้าน GamoryID',
            'dm_permission' => false,
            'options' => [
                [
                    'type' => 1,
                    'name' => 'ตั้งค่า',
                    'description' => 'เชื่อมเซิร์ฟเวอร์นี้กับร้าน GamoryID',
                    'options' => [[
                        'type' => 3,
                        'name' => 'รหัส',
                        'description' => 'รหัสเชื่อมร้านจากหน้า GamoryID',
                        'required' => true,
                    ]],
                ],
                [
                    'type' => 1,
                    'name' => 'เชื่อมบัญชี',
                    'description' => 'เชื่อมบัญชี Discord กับสมาชิกในร้าน',
                    'options' => [[
                        'type' => 3,
                        'name' => 'รหัส',
                        'description' => 'รหัสเชื่อมบัญชีจากหน้า GamoryID',
                        'required' => true,
                    ]],
                ],
                [
                    'type' => 1,
                    'name' => 'ค้นหา',
                    'description' => 'ค้นหาไอดีด้วยแท็ก',
                    'options' => [[
                        'type' => 3,
                        'name' => 'แท็ก',
                        'description' => 'เช่น #23DX5',
                        'required' => true,
                    ]],
                ],
                [
                    'type' => 1,
                    'name' => 'สรุป',
                    'description' => 'ดูภาพรวมสต็อกและยอดขายของร้าน',
                ],
                [
                    'type' => 1,
                    'name' => 'รายการ',
                    'description' => 'ดูรายการไอดีล่าสุดตามสถานะ',
                    'options' => [
                        [
                            'type' => 3,
                            'name' => 'สถานะ',
                            'description' => 'กรองสถานะของไอดี',
                            'required' => false,
                            'choices' => [
                                ['name' => 'ทั้งหมด', 'value' => 'all'],
                                ['name' => 'พร้อมขาย', 'value' => 'available'],
                                ['name' => 'ถูกจอง', 'value' => 'reserved'],
                                ['name' => 'ขายแล้ว', 'value' => 'sold'],
                            ],
                        ],
                        [
                            'type' => 4,
                            'name' => 'จำนวน',
                            'description' => 'จำนวนรายการที่ต้องการแสดง 1–10 รายการ',
                            'required' => false,
                            'min_value' => 1,
                            'max_value' => 10,
                        ],
                    ],
                ],
                [
                    'type' => 1,
                    'name' => 'จอง',
                    'description' => 'จองไอดีให้ลูกค้า',
                    'options' => [
                        $this->stringOption('แท็ก', 'แท็กไอดี เช่น #23DX5', true),
                        $this->stringOption('ลูกค้า', 'ชื่อลูกค้าที่จอง'),
                        [
                            'type' => 4,
                            'name' => 'ชั่วโมง',
                            'description' => 'ระยะเวลาจอง 1–720 ชั่วโมง ค่าเริ่มต้น 24',
                            'required' => false,
                            'min_value' => 1,
                            'max_value' => 720,
                        ],
                        $this->stringOption('โน้ต', 'ข้อความเตือนความจำภายในทีม'),
                    ],
                ],
                [
                    'type' => 1,
                    'name' => 'ยกเลิกจอง',
                    'description' => 'ยกเลิกการจองและคืนสถานะพร้อมขาย',
                    'options' => [
                        $this->stringOption('แท็ก', 'แท็กไอดี เช่น #23DX5', true),
                    ],
                ],
                [
                    'type' => 1,
                    'name' => 'ปิดการขาย',
                    'description' => 'บันทึกข้อมูลลูกค้าและปิดการขายไอดี',
                    'options' => [
                        $this->stringOption('แท็ก', 'แท็กไอดี เช่น #23DX5', true),
                        $this->stringOption('ลูกค้า', 'ชื่อหรือนามแฝงของลูกค้า', true),
                        [
                            'type' => 10,
                            'name' => 'ราคา',
                            'description' => 'ราคาที่ขายจริง',
                            'required' => true,
                            'min_value' => 0,
                        ],
                        $this->stringOption('เฟซบุ๊ก', 'ลิงก์ Facebook ของลูกค้า'),
                        $this->stringOption('ไลน์', 'LINE ID ของลูกค้า'),
                        $this->stringOption('เบอร์โทร', 'เบอร์โทรของลูกค้า'),
                        $this->stringOption('หมดประกัน', 'วันที่หมดประกัน รูปแบบ YYYY-MM-DD'),
                        $this->stringOption('รายละเอียด', 'รายละเอียดเพิ่มเติมของการขาย'),
                    ],
                ],
                [
                    'type' => 1,
                    'name' => 'โน้ต',
                    'description' => 'บันทึกโน้ตเตือนความจำไว้กับไอดี',
                    'options' => [
                        $this->stringOption('แท็ก', 'แท็กไอดี เช่น #23DX5', true),
                        $this->stringOption('ข้อความ', 'โน้ตภายในทีม', true, 2000),
                    ],
                ],
                [
                    'type' => 1,
                    'name' => 'เพิ่มไอดี',
                    'description' => 'เพิ่มไอดีใหม่ — ใส่ได้ถึงชื่อผู้ใช้ ไม่รับรหัสผ่าน',
                    'options' => [
                        $this->stringOption('riot-id', 'Riot ID เช่น Player#TH1', true),
                        [
                            'type' => 10,
                            'name' => 'ต้นทุน',
                            'description' => 'ต้นทุนของไอดี',
                            'required' => true,
                            'min_value' => 0,
                        ],
                        [
                            'type' => 10,
                            'name' => 'ราคา',
                            'description' => 'ราคาตั้งขาย',
                            'required' => true,
                            'min_value' => 0,
                        ],
                        $this->stringOption('username', 'ชื่อผู้ใช้สำหรับล็อกอิน (ห้ามใส่รหัสผ่าน)'),
                        $this->stringOption('email', 'อีเมลของบัญชีเกม (ห้ามใส่รหัสผ่าน)'),
                        [
                            'type' => 3,
                            'name' => 'แรงก์',
                            'description' => 'แรงก์ปัจจุบันของไอดี',
                            'required' => false,
                            'choices' => $this->valorantRankChoices(),
                        ],
                        [
                            'type' => 4,
                            'name' => 'เลเวล',
                            'description' => 'เลเวลของไอดี',
                            'required' => false,
                            'min_value' => 0,
                        ],
                        $this->stringOption('รายละเอียด', 'รายละเอียดไอดี'),
                        $this->stringOption('โน้ต', 'ข้อความเตือนความจำภายในทีม'),
                    ],
                ],
                [
                    'type' => 1,
                    'name' => 'ช่วยเหลือ',
                    'description' => 'ดูคำสั่งที่บัญชีของคุณมีสิทธิ์ใช้งาน',
                ],
            ],
        ]];
    }

    private function stringOption(string $name, string $description, bool $required = false, ?int $maxLength = null): array
    {
        return array_filter([
            'type' => 3,
            'name' => $name,
            'description' => $description,
            'required' => $required,
            'max_length' => $maxLength,
        ], fn ($value) => $value !== null);
    }

    /**
     * Valorant's fixed competitive ladder — 8 tiers × 3 divisions + Radiant = 25,
     * exactly Discord's per-option choice limit. Name and value are the same
     * string so it stores straight into inventory_items.rank.
     *
     * @return array<int, array{name: string, value: string}>
     */
    private function valorantRankChoices(): array
    {
        $tiers = ['Iron', 'Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Ascendant', 'Immortal'];
        $choices = [];
        foreach ($tiers as $tier) {
            foreach ([1, 2, 3] as $division) {
                $label = "{$tier} {$division}";
                $choices[] = ['name' => $label, 'value' => $label];
            }
        }
        $choices[] = ['name' => 'Radiant', 'value' => 'Radiant'];

        return $choices;
    }

    private function request(): PendingRequest
    {
        $token = (string) config('services.discord.bot_token');
        if ($token === '') {
            throw new RuntimeException('ยังไม่ได้ตั้งค่า Discord Bot Token');
        }

        return Http::baseUrl(self::API_BASE)
            ->withToken($token, 'Bot')
            ->acceptJson()
            ->asJson()
            ->timeout(8)
            ->retry(1, 250);
    }
}
