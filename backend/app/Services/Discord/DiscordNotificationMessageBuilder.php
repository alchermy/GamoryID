<?php

namespace App\Services\Discord;

use App\Models\InventoryItem;
use App\Models\Sale;
use App\Models\User;

class DiscordNotificationMessageBuilder
{
    public function inventoryCreated(InventoryItem $item, User $actor): string
    {
        return implode("\n", [
            "**#{$item->tag} · {$this->escape($item->riot_id ?: $item->title)}**",
            'แรงก์: '.$this->escape($item->rank ?: 'ไม่ระบุ'),
            'เลเวล: '.($item->level !== null ? number_format((int) $item->level) : 'ไม่ระบุ'),
            'ราคาขาย: '.number_format((float) $item->list_price, 2).' บาท',
            'เพิ่มโดย: '.$this->escape($actor->name),
        ]);
    }

    /** @return array{label: string, url: string} */
    public function inventoryLink(InventoryItem $item): array
    {
        return [
            'label' => 'เปิดข้อมูลไอดีใน GamoryID',
            'url' => $this->frontendUrl('/inventory?item='.$item->id),
        ];
    }

    public function saleCompleted(Sale $sale): string
    {
        $item = $sale->inventoryItem;
        $customer = $sale->customer;
        $creator = $sale->creator;
        $warranty = $sale->has_warranty
            ? 'มีประกันถึง '.($sale->warranty_ends_at?->timezone('Asia/Bangkok')->format('d/m/Y') ?? 'ไม่ระบุวัน')
            : 'ไม่มีประกัน';

        return implode("\n", [
            $item
                ? "**#{$item->tag} · {$this->escape($item->riot_id ?: $item->title)}**"
                : '**รายการขาย #'.$sale->id.'**',
            'ขายให้: '.$this->escape($customer?->name ?: 'ไม่ระบุ'),
            'ผู้ขาย: '.$this->escape($creator?->name ?: 'ไม่ระบุ'),
            'ราคาขาย: '.number_format((float) $sale->sold_price, 2).' บาท',
            'ประกัน: '.$warranty,
            'เวลาขาย: '.$sale->sold_at->timezone('Asia/Bangkok')->format('d/m/Y H:i').' น.',
        ]);
    }

    /** @return array{label: string, url: string} */
    public function saleLink(Sale $sale): array
    {
        return [
            'label' => 'ตรวจสอบรายละเอียดการขายใน GamoryID',
            'url' => $this->frontendUrl('/sales/'.$sale->id),
        ];
    }

    private function frontendUrl(string $path): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/'.ltrim($path, '/');
    }

    private function escape(string $value): string
    {
        return preg_replace('/([\\\\`*_{}\[\]()<>#+\-.!|~])/u', '\\\\$1', trim($value)) ?: 'ไม่ระบุ';
    }
}
