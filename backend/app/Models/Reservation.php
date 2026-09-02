<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    protected $fillable = ['shop_id', 'inventory_item_id', 'customer_id', 'created_by', 'notes', 'expires_at', 'released_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNull('released_at')->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }
}
