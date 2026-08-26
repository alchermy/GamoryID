<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCredential extends Model
{
    protected $fillable = ['inventory_item_id', 'encrypted_payload', 'key_version', 'last_revealed_at'];

    protected $hidden = ['encrypted_payload'];

    protected function casts(): array
    {
        return ['last_revealed_at' => 'datetime'];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
