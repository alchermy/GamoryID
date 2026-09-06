<?php

namespace App\Services;

use App\Exceptions\TagConflictException;
use App\Models\InventoryItem;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
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
    public function generate(Shop $shop, ?string $providedNumber = null, ?int $ignoreItemId = null): string
    {
        $prefix = $shop->effective_tag_prefix;
        $number = $this->normalizeNumber($providedNumber);

        if ($number !== null) {
            $tag = "{$prefix}-{$number}";
            if ($this->taken($shop, $tag, $ignoreItemId)) {
                throw new TagConflictException("รหัสไอดี {$tag} มีอยู่ในร้านแล้ว");
            }

            return $tag;
        }

        $next = $this->nextNumber($shop, $prefix);
        for ($attempt = 0; $attempt < 1_000_000; $attempt++, $next++) {
            $tag = sprintf('%s-%04d', $prefix, $next);
            if (! $this->taken($shop, $tag, $ignoreItemId)) {
                return $tag;
            }
        }

        throw new RuntimeException('สร้างรหัสไอดีไม่สำเร็จ กรุณาลองอีกครั้ง');
    }

    /** The numeric/text part after the last "-" (or the whole tag for legacy codes). */
    public function numberOf(string $tag): string
    {
        $dash = strrpos($tag, '-');

        return $dash === false ? $tag : substr($tag, $dash + 1);
    }

    /**
     * Rebrand every "<something>-<number>" code in the shop to the shop's
     * current prefix, keeping the number. Legacy 5-char codes are left alone.
     * A target code already used by a different item is skipped.
     *
     * @return array{renamed: int, skipped: int}
     */
    public function retagShop(Shop $shop): array
    {
        $prefix = $shop->effective_tag_prefix;
        $renamed = 0;
        $skipped = 0;

        DB::transaction(function () use ($shop, $prefix, &$renamed, &$skipped) {
            $items = InventoryItem::withTrashed()
                ->where('shop_id', $shop->id)
                ->where('tag', 'like', '%-%')
                ->lockForUpdate()
                ->get(['id', 'tag']);
            $used = InventoryItem::withTrashed()->where('shop_id', $shop->id)->pluck('tag', 'id');

            foreach ($items as $item) {
                $target = $prefix.'-'.$this->numberOf($item->tag);
                if ($target === $item->tag) {
                    continue;
                }
                $clashId = $used->search($target, true);
                if ($clashId !== false && $clashId !== $item->id) {
                    $skipped++;

                    continue;
                }
                $item->update(['tag' => $target]);
                $used[$item->id] = $target;
                $renamed++;
            }
        });

        return ['renamed' => $renamed, 'skipped' => $skipped];
    }

    /** How many item codes a retag would change for this shop. */
    public function retaggableCount(Shop $shop): int
    {
        $prefix = $shop->effective_tag_prefix;

        return InventoryItem::withTrashed()
            ->where('shop_id', $shop->id)
            ->where('tag', 'like', '%-%')
            ->where('tag', 'not like', $prefix.'-%')
            ->count();
    }

    private function normalizeNumber(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return ctype_digit($value) ? str_pad($value, 4, '0', STR_PAD_LEFT) : mb_strtoupper($value);
    }

    private function taken(Shop $shop, string $tag, ?int $ignoreItemId = null): bool
    {
        return InventoryItem::withTrashed()
            ->where('shop_id', $shop->id)
            ->where('tag', $tag)
            ->when($ignoreItemId, fn ($query) => $query->whereKeyNot($ignoreItemId))
            ->exists();
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
