<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMedia extends Model
{
    public const DISPLAY = 'display';

    public const DETAIL = 'detail';

    protected $fillable = [
        'inventory_item_id',
        'role',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'sort_order',
    ];

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
