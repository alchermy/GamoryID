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
    /** Pinned-panel button action → shop command. */
    private const MENU_COMMANDS = [
        'find' => 'ร้าน.ค้นหา',
        'list' => 'ร้าน.รายการ',
        'reserve' => 'ร้าน.จอง',
        'release' => 'ร้าน.ยกเลิกจอง',
        'sell' => 'ร้าน.ปิดการขาย',
        'note' => 'ร้าน.โน้ต',
        'add' => 'ร้าน.เพิ่มไอดี',
        'summary' => 'ร้าน.สรุป',
        'help' => 'ร้าน.ช่วยเหลือ',
    ];

    /** Buttons that run straight away — the rest pop a modal to collect input. */
    private const MENU_DIRECT = ['summary', 'help', 'list'];

    public function __construct(
        private readonly DiscordShopCommandHandler $shopCommands,
        private readonly DiscordApiClient $api,
    ) {}

    public function handle(array $interaction): array
    {
        $startedAt = hrtime(true);
        $type = (int) ($interaction['type'] ?? 2);
        $label = $this->interactionLabel($interaction, $type);
        $context = ['shop_id' => null, 'user_id' => null, 'status' => 'denied'];

        try {
            [$response, $context] = match ($type) {
                3 => $this->component($interaction),      // MESSAGE_COMPONENT (button)
                5 => $this->modalSubmit($interaction),    // MODAL_SUBMIT
                default => $this->applicationCommand($interaction, $label, $context),
            };
        } catch (Throwable $error) {
            report($error);
            $response = $this->ephemeral('คำสั่งยังทำงานไม่สำเร็จ กรุณาลองใหม่อีกครั้ง');
            $context['status'] = 'error';
        }

        $this->log($interaction, $label, $context, (int) ((hrtime(true) - $startedAt) / 1_000_000));

        return $response;
    }

    private function applicationCommand(array $interaction, string $command, array $context): array
    {
        return match ($command) {
            'ร้าน.ตั้งค่า', 'gid.setup' => $this->setup($interaction),
            'ร้าน.เชื่อมบัญชี', 'gid.link' => $this->link($interaction),
            'ร้าน.เมนู' => $this->postMenu($interaction),
            'gid.find' => $this->shopCommand($interaction, 'ร้าน.ค้นหา'),
            default => $this->shopCommands->supports($command)
                ? $this->shopCommand($interaction, $command)
                : [$this->ephemeral('ไม่พบคำสั่งนี้ กรุณาเลือกคำสั่งจากรายการของ Discord'), $context],
        };
    }

    private function interactionLabel(array $interaction, int $type): string
    {
        if ($type === 3 || $type === 5) {
            $prefix = $type === 3 ? 'ปุ่ม' : 'modal';

            return $prefix.'.'.($this->parseCustomId((string) ($interaction['data']['custom_id'] ?? ''))['action'] ?? 'unknown');
        }

        return $this->commandName($interaction);
    }

    /** @return array{action: ?string, id: ?int} */
    private function parseCustomId(string $customId): array
    {
        if (preg_match('/^gid:(?:menu|modal):([a-z]+)(?::(\d+))?$/', $customId, $m)) {
            return ['action' => $m[1], 'id' => isset($m[2]) ? (int) $m[2] : null];
        }

        return ['action' => null, 'id' => null];
    }

    /** Follow-up buttons that open a second modal for the optional fields. */
    private const MENU_FOLLOWUPS = [
        'addmore' => 'ร้าน.เพิ่มไอดี',   // uses the same permission as the first step
        'sellmore' => 'ร้าน.ปิดการขาย',
    ];

    /**
     * Button on the pinned panel — run it now, or open a modal to collect input.
     */
    private function component(array $interaction): array
    {
        ['action' => $action, 'id' => $id] = $this->parseCustomId((string) ($interaction['data']['custom_id'] ?? ''));
        $command = self::MENU_COMMANDS[$action] ?? self::MENU_FOLLOWUPS[$action] ?? null;
        if (! $command) {
            return [$this->ephemeral('ปุ่มนี้ไม่รองรับแล้ว กรุณากด `/ร้าน เมนู` เพื่อสร้างแผงใหม่'), ['shop_id' => null, 'user_id' => null, 'status' => 'not_found']];
        }

        $ctx = $this->resolveShopContext($interaction);
        if (! $ctx['ok']) {
            return [$ctx['response'], $ctx['context']];
        }
        if (! $this->shopCommands->canRun($command, $ctx['member'])) {
            return [$this->ephemeral($this->shopCommands->permissionDeniedMessage($command)), [...$ctx['context'], 'status' => 'denied']];
        }

        if (isset(self::MENU_FOLLOWUPS[$action])) {
            return [$this->modalResponse($action, $id), [...$ctx['context'], 'status' => 'modal']];
        }
        if (in_array($action, self::MENU_DIRECT, true)) {
            return $this->runShopCommand($command, $this->syntheticInteraction($interaction, []), $ctx);
        }

        return [$this->modalResponse($action), [...$ctx['context'], 'status' => 'modal']];
    }

    /**
     * The staff member filled in a modal that a panel button opened.
     */
    private function modalSubmit(array $interaction): array
    {
        ['action' => $action, 'id' => $id] = $this->parseCustomId((string) ($interaction['data']['custom_id'] ?? ''));
        $command = self::MENU_COMMANDS[$action] ?? self::MENU_FOLLOWUPS[$action] ?? null;
        if (! $command) {
            return [$this->ephemeral('แบบฟอร์มนี้ไม่รองรับแล้ว'), ['shop_id' => null, 'user_id' => null, 'status' => 'not_found']];
        }

        $ctx = $this->resolveShopContext($interaction);
        if (! $ctx['ok']) {
            return [$ctx['response'], $ctx['context']];
        }
        if (! $this->shopCommands->canRun($command, $ctx['member'])) {
            return [$this->ephemeral($this->shopCommands->permissionDeniedMessage($command)), [...$ctx['context'], 'status' => 'denied']];
        }

        $params = collect($this->modalOptions($interaction))->mapWithKeys(fn ($o) => [$o['name'] => $o['value']])->all();

        if ($action === 'addmore' && $id) {
            $result = $this->shopCommands->applyInventoryExtras($params, $ctx['installation'], $ctx['link'], $id);

            return [$this->ephemeral($result['content']), [...$ctx['context'], 'status' => $result['status']]];
        }
        if ($action === 'sellmore' && $id) {
            $result = $this->shopCommands->applySaleExtras($params, $ctx['installation'], $ctx['link'], $id);

            return [$this->ephemeral($result['content']), [...$ctx['context'], 'status' => $result['status']]];
        }

        return $this->runShopCommand($command, $this->syntheticInteraction($interaction, $this->modalOptions($interaction)), $ctx);
    }

    /** @return array<int, array{name: string, value: string}> */
    private function modalOptions(array $interaction): array
    {
        $options = [];
        foreach ($interaction['data']['components'] ?? [] as $row) {
            foreach ($row['components'] ?? [] as $field) {
                $name = (string) ($field['custom_id'] ?? '');
                $value = trim((string) ($field['value'] ?? ''));
                if ($name !== '' && $value !== '') {
                    $options[] = ['name' => $name, 'value' => $value];
                }
            }
        }

        return $options;
    }

    /**
     * Reshape a component/modal interaction so the slash-command handlers, which
     * read `data.options[0].options`, can run it unchanged.
     */
    private function syntheticInteraction(array $interaction, array $options): array
    {
        return [
            ...$interaction,
            'data' => ['name' => 'ร้าน', 'options' => [['name' => 'menu', 'options' => $options]]],
        ];
    }

    private function runShopCommand(string $command, array $synthetic, array $ctx): array
    {
        try {
            $result = $this->shopCommands->execute($command, $synthetic, $ctx['installation'], $ctx['link'], $ctx['member']);
        } catch (HttpExceptionInterface $error) {
            $message = $error->getStatusCode() < 500 && $error->getMessage() !== ''
                ? $error->getMessage()
                : 'คำสั่งยังทำงานไม่สำเร็จ กรุณาลองใหม่อีกครั้ง';

            return [$this->ephemeral($message), [...$ctx['context'], 'status' => 'denied']];
        }

        $followUp = null;
        if ($result['item_id'] ?? null) {
            $followUp = ['action' => 'addmore', 'id' => (int) $result['item_id'], 'label' => '➕ เพิ่มข้อมูลไอดี (แรงก์ เลเวล อีเมล…)'];
        } elseif ($result['sale_id'] ?? null) {
            $followUp = ['action' => 'sellmore', 'id' => (int) $result['sale_id'], 'label' => '➕ เพิ่มข้อมูลลูกค้า/ประกัน'];
        }

        return [
            $this->ephemeral($result['content'], $result['link'] ?? null, $followUp),
            [...$ctx['context'], 'status' => $result['status']],
        ];
    }

    private function modalResponse(string $action, ?int $id = null): array
    {
        [$title, $fields] = $this->modalFields($action);

        return [
            'type' => 9,
            'data' => [
                'custom_id' => 'gid:modal:'.$action.($id ? ':'.$id : ''),
                'title' => $title,
                'components' => array_map(fn (array $field) => [
                    'type' => 1,
                    'components' => [array_filter([
                        'type' => 4,
                        'custom_id' => $field['name'],
                        'label' => $field['label'],
                        'style' => $field['style'] ?? 1,
                        'required' => $field['required'] ?? false,
                        'max_length' => $field['max'] ?? 200,
                        'placeholder' => $field['placeholder'] ?? null,
                    ], fn ($value) => $value !== null)],
                ], $fields),
            ],
        ];
    }

    /** @return array{0: string, 1: array<int, array<string, mixed>>} */
    private function modalFields(string $action): array
    {
        // custom_id values are ASCII (Discord-safe); the shop handlers already
        // accept these as aliases for their Thai option names.
        return match ($action) {
            'find' => ['เช็คสถานะไอดี', [
                ['name' => 'tag', 'label' => 'แท็กไอดี เช่น #23DX5', 'required' => true, 'max' => 24],
            ]],
            'release' => ['ยกเลิกการจอง', [
                ['name' => 'tag', 'label' => 'แท็กไอดีที่จะยกเลิกจอง', 'required' => true, 'max' => 24],
            ]],
            'note' => ['บันทึกโน้ตไอดี', [
                ['name' => 'tag', 'label' => 'แท็กไอดี', 'required' => true, 'max' => 24],
                ['name' => 'note', 'label' => 'ข้อความโน้ต (เห็นเฉพาะในร้าน)', 'style' => 2, 'required' => true, 'max' => 2000],
            ]],
            'reserve' => ['จองไอดีให้ลูกค้า', [
                ['name' => 'tag', 'label' => 'แท็กไอดี', 'required' => true, 'max' => 24],
                ['name' => 'customer', 'label' => 'ชื่อลูกค้า (ไม่บังคับ)', 'max' => 120],
                ['name' => 'hours', 'label' => 'จองกี่ชั่วโมง 1–720 (เว้นว่าง = 24)', 'max' => 4],
                ['name' => 'note', 'label' => 'โน้ตการจอง (ไม่บังคับ)', 'style' => 2, 'max' => 500],
            ]],
            'sell' => ['ปิดการขายไอดี', [
                ['name' => 'tag', 'label' => 'แท็กไอดี', 'required' => true, 'max' => 24],
                ['name' => 'customer', 'label' => 'ชื่อลูกค้า', 'required' => true, 'max' => 120],
                ['name' => 'price', 'label' => 'ราคาขาย (บาท)', 'required' => true, 'max' => 12],
                ['name' => 'line', 'label' => 'LINE ของลูกค้า (ไม่บังคับ)', 'max' => 120],
                ['name' => 'note', 'label' => 'รายละเอียดการขาย (ไม่บังคับ)', 'style' => 2, 'max' => 500],
            ]],
            'add' => ['เพิ่มไอดีเข้าคลัง', [
                ['name' => 'title', 'label' => 'ชื่อรายการ', 'required' => true, 'max' => 120],
                ['name' => 'number', 'label' => 'เลขรหัสไอดี (เว้นว่าง = ระบบรันให้)', 'max' => 16],
                ['name' => 'cost', 'label' => 'ต้นทุน (บาท)', 'required' => true, 'max' => 12],
                ['name' => 'price', 'label' => 'ราคาตั้งขาย (บาท)', 'required' => true, 'max' => 12],
                ['name' => 'username', 'label' => 'ยูสเซอร์เนม — ห้ามใส่รหัสผ่าน', 'max' => 200],
            ]],
            // Second step opened by the "เพิ่มข้อมูล" button after the item is created.
            'addmore' => ['ข้อมูลเพิ่มเติมของไอดี', [
                ['name' => 'rank', 'label' => 'แรงก์', 'max' => 60],
                ['name' => 'level', 'label' => 'เลเวล', 'max' => 6],
                ['name' => 'email', 'label' => 'อีเมลติดไอดี', 'max' => 200],
                ['name' => 'description', 'label' => 'รายละเอียด (แสดงหน้าร้าน)', 'style' => 2, 'max' => 2000],
                ['name' => 'note', 'label' => 'โน้ตภายในทีม', 'style' => 2, 'max' => 2000],
            ]],
            // Second step opened by the "เพิ่มข้อมูล" button after the sale is closed.
            'sellmore' => ['ข้อมูลเพิ่มเติมของการขาย', [
                ['name' => 'facebook', 'label' => 'ลิงก์ Facebook ลูกค้า', 'max' => 200],
                ['name' => 'phone', 'label' => 'เบอร์โทรลูกค้า', 'max' => 30],
                ['name' => 'warranty', 'label' => 'วันหมดประกัน (YYYY-MM-DD)', 'max' => 10],
            ]],
            default => ['ทำรายการ', []],
        };
    }

    /**
     * Post the button panel into the commands channel and pin it.
     */
    private function postMenu(array $interaction): array
    {
        $ctx = $this->resolveShopContext($interaction);
        if (! $ctx['ok']) {
            return [$ctx['response'], $ctx['context']];
        }
        $channel = $ctx['installation']->channels()->where('purpose', 'commands')->where('enabled', true)->first();

        if ($this->api->isTestMode() || ! $this->api->isConfigured() || ! $channel) {
            // No live Discord to post to — echo the panel back so it is still visible/testable.
            $payload = $this->menuPayload($ctx['installation']->shop?->name ?: 'ร้าน');

            return [
                ['type' => 4, 'data' => [...$payload, 'flags' => 64, 'allowed_mentions' => ['parse' => []]]],
                [...$ctx['context'], 'status' => 'success'],
            ];
        }

        $pinned = $this->publishMenu($ctx['installation']);

        return [
            $this->ephemeral($pinned
                ? 'โพสต์แผงปุ่มควบคุมและปักหมุดไว้ในห้องนี้แล้ว'
                : 'โพสต์แผงปุ่มควบคุมแล้ว แต่ปักหมุดอัตโนมัติไม่ได้ กรุณาปักหมุดข้อความเอง (บอทต้องมีสิทธิ์ Manage Messages)'),
            [...$ctx['context'], 'status' => 'success'],
        ];
    }

    /**
     * Post the pinned control panel into the shop's commands channel. Used by
     * `/ร้าน เมนู` and by the "สร้างห้องอัตโนมัติ" button right after the channels
     * are provisioned. No-op (returns false) when there is no live Discord.
     *
     * @return bool true when the panel was posted AND pinned
     */
    public function publishMenu(DiscordInstallation $installation): bool
    {
        $channel = $installation->channels()
            ->where('purpose', 'commands')
            ->where('enabled', true)
            ->first();
        if (! $channel || $this->api->isTestMode() || ! $this->api->isConfigured()) {
            return false;
        }

        $message = $this->api->sendMessage(
            $channel->channel_id,
            $this->menuPayload($installation->shop?->name ?: 'ร้าน'),
        );
        try {
            $this->api->pinMessage($channel->channel_id, (string) ($message['id'] ?? ''));
        } catch (Throwable $error) {
            report($error);

            return false;
        }

        return true;
    }

    /** The pinned control-panel message: intro text + rows of action buttons. */
    public function menuPayload(string $shopName): array
    {
        $button = fn (string $action, string $label, int $style = 2) => [
            'type' => 2, 'style' => $style, 'label' => $label, 'custom_id' => "gid:menu:{$action}",
        ];

        return [
            'content' => "**แผงควบคุมร้าน {$shopName}**\n".
                "กดปุ่มเพื่อทำรายการได้เลย — ปุ่มที่ต้องกรอกข้อมูลจะเปิดหน้าต่างให้กรอก\n".
                'ยังใช้คำสั่ง `/ร้าน …` ได้ตามปกติ',
            'components' => [
                ['type' => 1, 'components' => [
                    $button('add', '➕ เพิ่มไอดี', 3),
                    $button('note', '📝 บันทึกโน้ตไอดี'),
                    $button('find', '🔍 เช็คสถานะไอดี'),
                ]],
                ['type' => 1, 'components' => [
                    $button('reserve', '📌 จองไอดี'),
                    $button('release', '↩️ ยกเลิกจอง'),
                    $button('sell', '💰 ปิดการขาย', 1),
                ]],
                ['type' => 1, 'components' => [
                    $button('list', '📦 ไอดีล่าสุด'),
                    $button('summary', '📊 สรุปยอด'),
                    $button('help', '❔ ช่วยเหลือ'),
                ]],
            ],
        ];
    }

    /**
     * Shared auth chain for slash / button / modal interactions: connected
     * installation → command channel → linked member of the shop.
     *
     * @return array{ok: bool, response?: array, context: array, installation?: DiscordInstallation, link?: DiscordUserLink, member?: ShopMember}
     */
    private function resolveShopContext(array $interaction): array
    {
        $deny = fn (string $message, ?int $shopId = null, ?int $userId = null, string $status = 'denied') => [
            'ok' => false,
            'response' => $this->ephemeral($message),
            'context' => ['shop_id' => $shopId, 'user_id' => $userId, 'status' => $status],
        ];

        $guildId = (string) ($interaction['guild_id'] ?? '');
        $discordUserId = $this->discordUserId($interaction);
        $installation = DiscordInstallation::query()
            ->where('guild_id', $guildId)
            ->where('status', 'connected')
            ->with('shop')
            ->first();
        if (! $installation) {
            return $deny('เซิร์ฟเวอร์นี้ยังไม่ได้เชื่อมกับร้าน GamoryID');
        }
        if ($message = $this->commandChannelError($interaction, $installation)) {
            return $deny($message, $installation->shop_id, null, 'wrong_channel');
        }
        $link = DiscordUserLink::query()
            ->where('shop_id', $installation->shop_id)
            ->where('discord_user_id', $discordUserId)
            ->with('user')
            ->first();
        if (! $link || ! $link->user) {
            return $deny('บัญชี Discord นี้ยังไม่ได้เชื่อมกับสมาชิกในร้าน กรุณาสร้างรหัสจากหน้า Discord ใน GamoryID แล้วใช้ `/ร้าน เชื่อมบัญชี` ในห้องคำสั่งทั่วไป', $installation->shop_id);
        }
        $member = ShopMember::query()
            ->where('shop_id', $installation->shop_id)
            ->where('user_id', $link->user_id)
            ->first();
        if (! $member) {
            return $deny('บัญชีนี้ไม่ได้เป็นสมาชิกของร้านแล้ว กรุณาติดต่อเจ้าของร้าน', $installation->shop_id, $link->user_id);
        }

        return [
            'ok' => true,
            'context' => ['shop_id' => $installation->shop_id, 'user_id' => $link->user_id, 'status' => 'success'],
            'installation' => $installation,
            'link' => $link,
            'member' => $member,
        ];
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
        $ctx = $this->resolveShopContext($interaction);
        if (! $ctx['ok']) {
            return [$ctx['response'], $ctx['context']];
        }
        if (! $this->shopCommands->canRun($command, $ctx['member'])) {
            return [$this->ephemeral($this->shopCommands->permissionDeniedMessage($command)), [...$ctx['context'], 'status' => 'denied']];
        }

        return $this->runShopCommand($command, $interaction, $ctx);
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
            return 'ร้านนี้ยังไม่มีห้องคำสั่งทั่วไป กรุณาให้ผู้ดูแลกด “สร้างห้องอัตโนมัติ” ในหน้า Discord ของ GamoryID ก่อน';
        }

        if ((string) ($interaction['channel_id'] ?? '') !== $channel->channel_id) {
            return "กรุณาใช้คำสั่งนี้ในห้อง #{$channel->channel_name} เท่านั้น";
        }

        return null;
    }

    /**
     * @param  array{label: string, url: string}|null  $link
     * @param  array{action: string, id: int, label: string}|null  $followUp  opens a second modal
     */
    private function ephemeral(string $content, ?array $link = null, ?array $followUp = null): array
    {
        $data = [
            'content' => $content,
            'flags' => 64,
            'allowed_mentions' => ['parse' => []],
        ];

        $buttons = [];
        if ($followUp) {
            $buttons[] = [
                'type' => 2,
                'style' => 2,
                'label' => mb_substr($followUp['label'], 0, 80),
                'custom_id' => "gid:menu:{$followUp['action']}:{$followUp['id']}",
            ];
        }
        if ($link && filter_var($link['url'] ?? null, FILTER_VALIDATE_URL) && preg_match('#^https?://#', (string) $link['url'])) {
            $buttons[] = [
                'type' => 2,
                'style' => 5,
                'label' => mb_substr($link['label'], 0, 80),
                'url' => $link['url'],
            ];
        }
        if ($buttons) {
            $data['components'] = [['type' => 1, 'components' => $buttons]];
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
