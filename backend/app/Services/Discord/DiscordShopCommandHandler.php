<?php

namespace App\Services\Discord;

use App\Enums\InventoryStatus;
use App\Enums\ShopPermission;
use App\Jobs\SendDiscordShopNotification;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\DiscordInstallation;
use App\Models\DiscordUserLink;
use App\Models\InventoryItem;
use App\Models\Reservation;
use App\Models\Sale;
use App\Models\ShopMember;
use App\Services\PlanGate;
use App\Services\TagGenerator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class DiscordShopCommandHandler
{
    /** @var array<string, array<int, ShopPermission>> */
    private const PERMISSIONS = [
        'ร้าน.ค้นหา' => [ShopPermission::InventoryManage, ShopPermission::InventorySell],
        'ร้าน.รายการ' => [ShopPermission::InventoryManage, ShopPermission::InventorySell],
        'ร้าน.จอง' => [ShopPermission::InventorySell],
        'ร้าน.ยกเลิกจอง' => [ShopPermission::InventorySell],
        'ร้าน.ปิดการขาย' => [ShopPermission::InventorySell],
        'ร้าน.โน้ต' => [ShopPermission::InventoryManage, ShopPermission::InventorySell],
        'ร้าน.เพิ่มไอดี' => [ShopPermission::InventoryManage],
        'ร้าน.สรุป' => [],
        'ร้าน.ช่วยเหลือ' => [],
    ];

    private const MUTATING_COMMANDS = [
        'ร้าน.จอง',
        'ร้าน.ยกเลิกจอง',
        'ร้าน.ปิดการขาย',
        'ร้าน.โน้ต',
        'ร้าน.เพิ่มไอดี',
    ];

    public function __construct(
        private readonly TagGenerator $tags,
        private readonly PlanGate $planGate,
        private readonly DiscordNotificationMessageBuilder $notifications,
    ) {}

    public function supports(string $command): bool
    {
        return array_key_exists($command, self::PERMISSIONS);
    }

    public function canRun(string $command, ShopMember $member): bool
    {
        if ($member->role === 'owner') {
            return true;
        }

        $required = self::PERMISSIONS[$command] ?? [];
        if ($required === []) {
            return true;
        }

        return collect($required)
            ->pluck('value')
            ->intersect($member->permissions ?? [])
            ->isNotEmpty();
    }

    /** @return array{content: string, status: string} */
    public function execute(
        string $command,
        array $interaction,
        DiscordInstallation $installation,
        DiscordUserLink $link,
        ShopMember $member,
    ): array {
        $shop = $installation->shop;
        if (! $shop) {
            return $this->failure('ไม่พบร้านที่เชื่อมกับเซิร์ฟเวอร์นี้');
        }
        if (in_array($command, self::MUTATING_COMMANDS, true) && ! $shop->isWritable()) {
            return $this->failure('ร้านอยู่ในโหมดอ่านอย่างเดียว จึงยังแก้ไขสต็อกผ่าน Discord ไม่ได้', 'read_only');
        }

        return match ($command) {
            'ร้าน.ค้นหา' => $this->find($interaction, $installation),
            'ร้าน.สรุป' => $this->summary($installation, $member),
            'ร้าน.รายการ' => $this->inventoryList($interaction, $installation),
            'ร้าน.จอง' => $this->reserve($interaction, $installation, $link),
            'ร้าน.ยกเลิกจอง' => $this->releaseReservation($interaction, $installation, $link),
            'ร้าน.ปิดการขาย' => $this->completeSale($interaction, $installation, $link),
            'ร้าน.โน้ต' => $this->updateNote($interaction, $installation, $link),
            'ร้าน.เพิ่มไอดี' => $this->createInventory($interaction, $installation, $link),
            'ร้าน.ช่วยเหลือ' => $this->help($member),
            default => $this->failure('ไม่พบคำสั่งนี้ กรุณาใช้ `/ร้าน ช่วยเหลือ` เพื่อตรวจสอบคำสั่งที่ใช้งานได้', 'not_found'),
        };
    }

    public function permissionDeniedMessage(string $command): string
    {
        $label = match ($command) {
            'ร้าน.จอง', 'ร้าน.ยกเลิกจอง', 'ร้าน.ปิดการขาย' => 'จองและขาย',
            'ร้าน.เพิ่มไอดี' => 'จัดการสต็อก',
            default => 'จัดการสต็อก หรือ จองและขาย',
        };

        $displayCommand = '/'.str_replace('.', ' ', $command);

        return "บัญชีนี้ไม่มีสิทธิ์ใช้คำสั่ง {$displayCommand} กรุณาให้เจ้าของร้านเปิดสิทธิ์ “{$label}” ในเมนูทีมและสิทธิ์";
    }

    /** @return array{content: string, status: string} */
    private function find(array $interaction, DiscordInstallation $installation): array
    {
        $tag = $this->normalizedTag($this->optionValue($interaction, 'แท็ก', 'tag'));
        if ($tag === '') {
            return $this->failure('กรุณาระบุแท็กไอดี เช่น #23DX5');
        }
        $item = InventoryItem::query()
            ->where('shop_id', $installation->shop_id)
            ->where('tag', $tag)
            ->where('status', '!=', InventoryStatus::Archived)
            ->first();
        if (! $item) {
            return $this->failure("ไม่พบไอดี #{$tag} ในร้านนี้", 'not_found');
        }

        return $this->success($this->formatItem($item, true));
    }

    /** @return array{content: string, status: string} */
    private function summary(DiscordInstallation $installation, ShopMember $member): array
    {
        $counts = InventoryItem::query()
            ->where('shop_id', $installation->shop_id)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
        $sales = Sale::query()
            ->where('shop_id', $installation->shop_id)
            ->where('sold_at', '>=', now()->startOfMonth());
        $lines = [
            '**สรุปร้าน '.$this->escape($installation->shop?->name ?: 'GamoryID').'**',
            'พร้อมขาย: '.number_format((int) ($counts[InventoryStatus::Available->value] ?? 0)).' รายการ',
            'ถูกจอง: '.number_format((int) ($counts[InventoryStatus::Reserved->value] ?? 0)).' รายการ',
            'ขายเดือนนี้: '.number_format((clone $sales)->count()).' รายการ',
            'ยอดขายเดือนนี้: '.number_format((float) (clone $sales)->sum('sold_price'), 2).' บาท',
        ];
        if ($member->role === 'owner' || in_array(ShopPermission::ProfitView->value, $member->permissions ?? [], true)) {
            $lines[] = 'กำไรเดือนนี้: '.number_format((float) (clone $sales)->sum('profit'), 2).' บาท';
        }

        return $this->success(implode("\n", $lines));
    }

    /** @return array{content: string, status: string} */
    private function inventoryList(array $interaction, DiscordInstallation $installation): array
    {
        $status = $this->optionValue($interaction, 'สถานะ', 'status') ?: 'all';
        if (! in_array($status, ['all', 'available', 'reserved', 'sold'], true)) {
            return $this->failure('สถานะที่เลือกไม่ถูกต้อง');
        }
        $limit = (int) ($this->optionValue($interaction, 'จำนวน', 'limit') ?: 5);
        $limit = max(1, min($limit, 10));
        $query = InventoryItem::query()
            ->where('shop_id', $installation->shop_id)
            ->where('status', '!=', InventoryStatus::Archived)
            ->latest('updated_at');
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        $items = $query->limit($limit)->get();
        if ($items->isEmpty()) {
            return $this->failure('ไม่พบไอดีตามสถานะที่เลือก', 'not_found');
        }

        $heading = $status === 'all' ? 'รายการไอดีล่าสุด' : 'รายการสถานะ '.$this->statusLabel($status);
        $lines = $items->map(fn (InventoryItem $item) => sprintf(
            '**#%s** · %s · %s · %s บาท · %s',
            $item->tag,
            $this->escape($item->riot_id ?: $item->title),
            $this->escape($item->rank ?: 'ไม่ระบุแรงก์'),
            number_format((float) $item->list_price, 0),
            $this->statusLabel($item->status->value),
        ));

        return $this->success("**{$heading}**\n".$lines->implode("\n"));
    }

    /** @return array{content: string, status: string} */
    private function reserve(array $interaction, DiscordInstallation $installation, DiscordUserLink $link): array
    {
        $tag = $this->normalizedTag($this->optionValue($interaction, 'แท็ก', 'tag'));
        $customerName = trim($this->optionValue($interaction, 'ลูกค้า', 'customer'));
        $notes = trim($this->optionValue($interaction, 'โน้ต', 'note'));
        $hours = (int) ($this->optionValue($interaction, 'ชั่วโมง', 'hours') ?: 24);
        if ($tag === '' || $hours < 1 || $hours > 720) {
            return $this->failure('กรุณาตรวจสอบแท็กและระยะเวลาจอง 1–720 ชั่วโมง');
        }

        try {
            [$item, $reservation] = DB::transaction(function () use ($installation, $link, $tag, $customerName, $notes, $hours) {
                $item = InventoryItem::query()->where('shop_id', $installation->shop_id)->where('tag', $tag)->lockForUpdate()->first();
                if (! $item) {
                    throw new DiscordCommandException("ไม่พบไอดี #{$tag} ในร้านนี้");
                }
                if ($item->status !== InventoryStatus::Available) {
                    throw new DiscordCommandException('รายการนี้ไม่พร้อมให้จอง');
                }
                $customerId = $customerName !== ''
                    ? Customer::create(['shop_id' => $installation->shop_id, 'name' => $customerName])->id
                    : null;
                $reservation = Reservation::create([
                    'shop_id' => $installation->shop_id,
                    'inventory_item_id' => $item->id,
                    'customer_id' => $customerId,
                    'created_by' => $link->user_id,
                    'notes' => $notes !== '' ? $notes : null,
                    'expires_at' => now()->addHours($hours),
                ]);
                $item->update(['status' => InventoryStatus::Reserved, 'lock_version' => $item->lock_version + 1]);

                return [$item, $reservation];
            }, 3);
        } catch (DiscordCommandException $error) {
            return $this->failure($error->getMessage(), 'conflict');
        }

        $this->activity($installation->shop_id, $link->user_id, 'inventory.reserved', [
            'inventory_id' => $item->id,
            'source' => 'discord',
        ]);
        SendDiscordShopNotification::dispatch(
            $installation->shop_id,
            'reservations',
            'มีการจองไอดี',
            "**#{$item->tag}** · {$this->escape($item->riot_id ?: $item->title)}\nหมดเวลาจอง ".$reservation->expires_at->timezone('Asia/Bangkok')->format('d/m/Y H:i').' น.',
        );

        return $this->success("จอง **#{$item->tag}** สำเร็จ\nหมดเวลาจอง ".$reservation->expires_at->timezone('Asia/Bangkok')->format('d/m/Y H:i').' น.');
    }

    /** @return array{content: string, status: string} */
    private function releaseReservation(array $interaction, DiscordInstallation $installation, DiscordUserLink $link): array
    {
        $tag = $this->normalizedTag($this->optionValue($interaction, 'แท็ก', 'tag'));
        try {
            $item = DB::transaction(function () use ($installation, $tag) {
                $item = InventoryItem::query()->where('shop_id', $installation->shop_id)->where('tag', $tag)->lockForUpdate()->first();
                if (! $item) {
                    throw new DiscordCommandException("ไม่พบไอดี #{$tag} ในร้านนี้");
                }
                if ($item->status !== InventoryStatus::Reserved) {
                    throw new DiscordCommandException('รายการนี้ไม่ได้ถูกจอง');
                }
                Reservation::query()
                    ->where('shop_id', $installation->shop_id)
                    ->where('inventory_item_id', $item->id)
                    ->whereNull('released_at')
                    ->update(['released_at' => now()]);
                $item->update(['status' => InventoryStatus::Available, 'lock_version' => $item->lock_version + 1]);

                return $item;
            }, 3);
        } catch (DiscordCommandException $error) {
            return $this->failure($error->getMessage(), 'conflict');
        }

        $this->activity($installation->shop_id, $link->user_id, 'inventory.reservation_released', [
            'tag' => '#'.$item->tag,
            'source' => 'discord',
        ]);
        SendDiscordShopNotification::dispatch(
            $installation->shop_id,
            'reservations',
            'ยกเลิกการจองแล้ว',
            "**#{$item->tag}** · {$this->escape($item->riot_id ?: $item->title)}\nรายการกลับเป็นสถานะพร้อมขาย",
        );

        return $this->success("ยกเลิกการจอง **#{$item->tag}** แล้ว รายการกลับเป็นสถานะพร้อมขาย");
    }

    /** @return array{content: string, status: string} */
    private function completeSale(array $interaction, DiscordInstallation $installation, DiscordUserLink $link): array
    {
        $tag = $this->normalizedTag($this->optionValue($interaction, 'แท็ก', 'tag'));
        $customerName = trim($this->optionValue($interaction, 'ลูกค้า', 'customer'));
        $priceValue = $this->optionValue($interaction, 'ราคา', 'price');
        $facebook = trim($this->optionValue($interaction, 'เฟซบุ๊ก', 'facebook'));
        $line = trim($this->optionValue($interaction, 'ไลน์', 'line'));
        $phone = trim($this->optionValue($interaction, 'เบอร์โทร', 'phone'));
        $warrantyValue = trim($this->optionValue($interaction, 'หมดประกัน', 'warranty'));
        $notes = trim($this->optionValue($interaction, 'รายละเอียด', 'note'));
        if ($tag === '' || $customerName === '' || ! is_numeric($priceValue) || (float) $priceValue < 0) {
            return $this->failure('กรุณาระบุแท็ก ชื่อลูกค้า และราคาขายให้ถูกต้อง');
        }
        if ($facebook !== '' && filter_var($facebook, FILTER_VALIDATE_URL) === false) {
            return $this->failure('ลิงก์ Facebook ไม่ถูกต้อง กรุณาใส่ URL แบบเต็ม');
        }
        $warrantyEndsAt = null;
        if ($warrantyValue !== '') {
            if (! Carbon::canBeCreatedFromFormat($warrantyValue, 'Y-m-d')) {
                return $this->failure('วันที่หมดประกันไม่ถูกต้อง กรุณาใช้รูปแบบ YYYY-MM-DD');
            }
            try {
                $warrantyEndsAt = Carbon::createFromFormat('Y-m-d', $warrantyValue)->startOfDay();
            } catch (Throwable) {
                return $this->failure('วันที่หมดประกันไม่ถูกต้อง กรุณาใช้รูปแบบ YYYY-MM-DD');
            }
            if ($warrantyEndsAt->lt(today())) {
                return $this->failure('วันที่หมดประกันต้องเป็นวันนี้หรือวันถัดไป');
            }
        }

        try {
            $sale = DB::transaction(function () use ($installation, $link, $tag, $customerName, $facebook, $line, $phone, $priceValue, $warrantyEndsAt, $notes) {
                $item = InventoryItem::query()->where('shop_id', $installation->shop_id)->where('tag', $tag)->lockForUpdate()->first();
                if (! $item) {
                    throw new DiscordCommandException("ไม่พบไอดี #{$tag} ในร้านนี้");
                }
                if ($item->status === InventoryStatus::Sold || Sale::query()->where('inventory_item_id', $item->id)->exists()) {
                    throw new DiscordCommandException('รายการนี้ถูกขายไปแล้ว');
                }
                if ($item->status === InventoryStatus::Archived) {
                    throw new DiscordCommandException('รายการที่เก็บถาวรไม่สามารถขายได้');
                }
                $customer = Customer::create([
                    'shop_id' => $installation->shop_id,
                    'name' => $customerName,
                    'facebook_url' => $facebook !== '' ? $facebook : null,
                    'line_id' => $line !== '' ? $line : null,
                    'phone' => $phone !== '' ? $phone : null,
                ]);
                $soldPrice = (float) $priceValue;
                $sale = Sale::create([
                    'shop_id' => $installation->shop_id,
                    'inventory_item_id' => $item->id,
                    'customer_id' => $customer->id,
                    'created_by' => $link->user_id,
                    'sold_price' => $soldPrice,
                    'cost_snapshot' => (float) $item->cost,
                    'profit' => $soldPrice - (float) $item->cost,
                    'has_warranty' => $warrantyEndsAt !== null,
                    'warranty_ends_at' => $warrantyEndsAt?->toDateString(),
                    'notes' => $notes !== '' ? $notes : null,
                    'sold_at' => now(),
                ]);
                Reservation::query()->where('inventory_item_id', $item->id)->whereNull('released_at')->update(['released_at' => now()]);
                $item->update(['status' => InventoryStatus::Sold, 'lock_version' => $item->lock_version + 1]);

                return $sale;
            }, 3);
        } catch (DiscordCommandException $error) {
            return $this->failure($error->getMessage(), 'conflict');
        }

        $this->activity($installation->shop_id, $link->user_id, 'inventory.sold', [
            'inventory_id' => $sale->inventory_item_id,
            'sold_price' => $sale->sold_price,
            'has_warranty' => $sale->has_warranty,
            'source' => 'discord',
        ]);
        $sale->load(['inventoryItem', 'customer', 'creator']);
        SendDiscordShopNotification::dispatch(
            $installation->shop_id,
            'sales',
            'ปิดการขายสำเร็จ',
            $this->notifications->saleCompleted($sale),
        );

        return $this->success("ปิดการขาย **#{$sale->inventoryItem?->tag}** สำเร็จ\nราคาขาย ".number_format((float) $sale->sold_price, 2).' บาท');
    }

    /** @return array{content: string, status: string} */
    private function updateNote(array $interaction, DiscordInstallation $installation, DiscordUserLink $link): array
    {
        $tag = $this->normalizedTag($this->optionValue($interaction, 'แท็ก', 'tag'));
        $note = trim($this->optionValue($interaction, 'ข้อความ', 'note'));
        if ($tag === '' || $note === '') {
            return $this->failure('กรุณาระบุแท็กและข้อความโน้ต');
        }
        $item = InventoryItem::query()
            ->where('shop_id', $installation->shop_id)
            ->where('tag', $tag)
            ->where('status', '!=', InventoryStatus::Archived)
            ->first();
        if (! $item) {
            return $this->failure("ไม่พบไอดี #{$tag} ในร้านนี้", 'not_found');
        }
        $item->update(['notes' => mb_substr($note, 0, 2000)]);
        $this->activity($installation->shop_id, $link->user_id, 'inventory.note_updated', [
            'tag' => '#'.$item->tag,
            'has_note' => true,
            'source' => 'discord',
        ]);

        return $this->success("บันทึกโน้ตให้ **#{$item->tag}** แล้ว โน้ตนี้จะแสดงเฉพาะภายในร้าน");
    }

    /** @return array{content: string, status: string} */
    private function createInventory(array $interaction, DiscordInstallation $installation, DiscordUserLink $link): array
    {
        $riotId = trim($this->optionValue($interaction, 'ไอดี', 'riot_id'));
        $costValue = $this->optionValue($interaction, 'ต้นทุน', 'cost');
        $priceValue = $this->optionValue($interaction, 'ราคา', 'price');
        if ($riotId === '' || ! is_numeric($costValue) || ! is_numeric($priceValue) || (float) $costValue < 0 || (float) $priceValue < 0) {
            return $this->failure('กรุณาระบุ Riot ID ต้นทุน และราคาขายให้ถูกต้อง');
        }
        $shop = $installation->shop;
        if (! $shop) {
            return $this->failure('ไม่พบร้านที่เชื่อมกับเซิร์ฟเวอร์นี้');
        }
        $this->planGate->ensureInventoryCapacity($shop);

        $item = InventoryItem::create([
            'shop_id' => $installation->shop_id,
            'created_by' => $link->user_id,
            'tag' => $this->tags->generate(),
            'title' => $riotId,
            'riot_id' => $riotId,
            'region' => 'TH',
            'rank' => $this->blankToNull($this->optionValue($interaction, 'แรงก์', 'rank')),
            'level' => $this->nullableInteger($this->optionValue($interaction, 'เลเวล', 'level')),
            'description' => $this->blankToNull($this->optionValue($interaction, 'รายละเอียด', 'description')),
            'notes' => $this->blankToNull($this->optionValue($interaction, 'โน้ต', 'note')),
            'cost' => (float) $costValue,
            'list_price' => (float) $priceValue,
            'status' => InventoryStatus::Available,
        ]);
        $this->activity($installation->shop_id, $link->user_id, 'inventory.created', [
            'tag' => '#'.$item->tag,
            'source' => 'discord',
        ]);
        if ($link->user) {
            SendDiscordShopNotification::dispatch(
                $installation->shop_id,
                'inventory',
                'เพิ่มไอดีใหม่เข้าคลัง',
                $this->notifications->inventoryCreated($item, $link->user),
            );
        }

        return $this->success(
            "เพิ่ม **#{$item->tag} · {$this->escape($item->riot_id)}** เข้าคลังแล้ว\n".
            'ข้อมูลชื่อผู้ใช้และรหัสผ่านต้องเพิ่มจากหน้ารายละเอียดไอดีใน GamoryID เท่านั้น',
        );
    }

    /** @return array{content: string, status: string} */
    private function help(ShopMember $member): array
    {
        $commands = [
            'ร้าน.สรุป' => '`/ร้าน สรุป` — ภาพรวมสต็อกและยอดขาย',
            'ร้าน.ค้นหา' => '`/ร้าน ค้นหา` — ค้นหาไอดีด้วยแท็ก',
            'ร้าน.รายการ' => '`/ร้าน รายการ` — ดูไอดีล่าสุดตามสถานะ',
            'ร้าน.จอง' => '`/ร้าน จอง` — จองไอดีให้ลูกค้า',
            'ร้าน.ยกเลิกจอง' => '`/ร้าน ยกเลิกจอง` — คืนสถานะพร้อมขาย',
            'ร้าน.ปิดการขาย' => '`/ร้าน ปิดการขาย` — บันทึกลูกค้าและการขาย',
            'ร้าน.โน้ต' => '`/ร้าน โน้ต` — บันทึกโน้ตภายในทีม',
            'ร้าน.เพิ่มไอดี' => '`/ร้าน เพิ่มไอดี` — เพิ่มข้อมูลไอดีแบบไม่รวมรหัสผ่าน',
            'ร้าน.ช่วยเหลือ' => '`/ร้าน ช่วยเหลือ` — ดูสิทธิ์คำสั่งของคุณ',
        ];
        $available = collect($commands)
            ->filter(fn ($description, $command) => $this->canRun($command, $member))
            ->values()
            ->implode("\n");

        return $this->success("**คำสั่งที่คุณใช้งานได้**\n{$available}\n\nหากคำสั่งหายไป ให้เจ้าของร้านตรวจสิทธิ์ที่เมนูทีมและสิทธิ์");
    }

    private function formatItem(InventoryItem $item, bool $includeDescription): string
    {
        $lines = [
            "**#{$item->tag}**",
            'ไอดี Riot: '.$this->escape($item->riot_id ?: $item->title),
            'แรงก์: '.$this->escape($item->rank ?: '–'),
            'เลเวล: '.($item->level !== null ? number_format((int) $item->level) : '–'),
            'ราคา: ฿'.number_format((float) $item->list_price, 0),
            'สถานะ: '.$this->statusLabel($item->status->value),
        ];
        if ($includeDescription) {
            $lines[] = 'รายละเอียด: '.$this->escape(trim((string) $item->description) ?: '–');
        }

        return implode("\n", $lines);
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

    private function normalizedTag(string $tag): string
    {
        return ltrim(mb_strtoupper(trim($tag)), '#');
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            InventoryStatus::Available->value => 'พร้อมขาย',
            InventoryStatus::Reserved->value => 'ถูกจอง',
            InventoryStatus::Sold->value => 'ขายแล้ว',
            default => 'เก็บถาวร',
        };
    }

    private function escape(string $value): string
    {
        return preg_replace('/([\\\\`*_{}\[\]()<>#+\-.!|~])/u', '\\\\$1', trim($value)) ?: 'ไม่ระบุ';
    }

    private function blankToNull(string $value): ?string
    {
        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function nullableInteger(string $value): ?int
    {
        return $value !== '' ? max(0, (int) $value) : null;
    }

    private function activity(int $shopId, int $userId, string $event, array $metadata): void
    {
        ActivityLog::create([
            'shop_id' => $shopId,
            'user_id' => $userId,
            'event' => $event,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    /** @return array{content: string, status: string} */
    private function success(string $content): array
    {
        return ['content' => $content, 'status' => 'success'];
    }

    /** @return array{content: string, status: string} */
    private function failure(string $content, string $status = 'denied'): array
    {
        return ['content' => $content, 'status' => $status];
    }
}
