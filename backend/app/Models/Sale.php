<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    protected $fillable = ['shop_id', 'inventory_item_id', 'customer_id', 'created_by', 'sold_price', 'cost_snapshot', 'profit', 'has_warranty', 'warranty_ends_at', 'notes', 'sold_at'];

    protected function casts(): array
    {
        return ['sold_price' => 'decimal:2', 'cost_snapshot' => 'decimal:2', 'profit' => 'decimal:2', 'has_warranty' => 'boolean', 'warranty_ends_at' => 'date', 'sold_at' => 'datetime'];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
