<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = ['shop_id', 'inventory_item_id', 'customer_id', 'created_by', 'sold_price', 'cost_snapshot', 'profit', 'notes', 'sold_at'];

    protected function casts(): array
    {
        return ['sold_price' => 'decimal:2', 'cost_snapshot' => 'decimal:2', 'profit' => 'decimal:2', 'sold_at' => 'datetime'];
    }
}
