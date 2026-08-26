<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMedia extends Model
{
    protected $fillable = ['inventory_item_id', 'disk', 'path', 'mime_type', 'size_bytes', 'sort_order'];

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
