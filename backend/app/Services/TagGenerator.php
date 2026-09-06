<?php

namespace App\Services;

use App\Exceptions\TagConflictException;
use App\Models\InventoryItem;
use App\Models\Shop;
use RuntimeException;

class TagGenerator
{
    /**
     * Build an item code for a shop: "<PREFIX>-<number>".
     *
     * A shop-supplied number (from an import column or the add form) is used
     * as-is; otherwise the next number for that shop+prefix is allocated.
     *
     * @throws TagConflictException when the shop-supplied code already exists
     */
    public function generate(Shop $shop, ?string $providedNumber = null): string
    {
        $prefix = $shop->effective_tag_prefix;
        $number = $this->normalizeNumber($providedNumber);

        if ($number !== null) {
            $tag = "{$prefix}-{$number}";
            if ($this->taken($shop, $tag)) {
                throw new TagConflictException("รหัสไอดี {$tag} มีอยู่ในร้านแล้ว");
            }

            return $tag;
        }

        $next = $this->nextNumber($shop, $prefix);
        for ($attempt = 0; $attempt < 1_000_000; $attempt++, $next++) {
            $tag = sprintf('%s-%04d', $prefix, $next);
            if (! $this->taken($shop, $tag)) {
                return $tag;
            }
        }

        throw new RuntimeException('สร้างรหัสไอดีไม่สำเร็จ กรุณาลองอีกครั้ง');
    }

    private function normalizeNumber(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return ctype_digit($value) ? str_pad($value, 4, '0', STR_PAD_LEFT) : mb_strtoupper($value);
    }

    private function taken(Shop $shop, string $tag): bool
    {
        return InventoryItem::withTrashed()->where('shop_id', $shop->id)->where('tag', $tag)->exists();
    }

    /** Highest numeric suffix currently used for this shop+prefix, plus one. */
    private function nextNumber(Shop $shop, string $prefix): int
    {
        $highest = 0;
        InventoryItem::withTrashed()
            ->where('shop_id', $shop->id)
            ->where('tag', 'like', $prefix.'-%')
            ->pluck('tag')
            ->each(function (string $tag) use (&$highest) {
                $suffix = substr($tag, strrpos($tag, '-') + 1);
                if (ctype_digit($suffix)) {
                    $highest = max($highest, (int) $suffix);
                }
            });

        return $highest + 1;
    }
}
