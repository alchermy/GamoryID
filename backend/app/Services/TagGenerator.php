<?php

namespace App\Services;

use App\Models\InventoryItem;
use RuntimeException;

class TagGenerator
{
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public function generate(): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $tag = '';
            for ($index = 0; $index < 5; $index++) {
                $tag .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            if (! InventoryItem::withTrashed()->where('tag', $tag)->exists()) {
                return $tag;
            }
        }

        throw new RuntimeException('ไม่สามารถสร้าง Tag ที่ไม่ซ้ำได้ กรุณาลองอีกครั้ง');
    }
}
