<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = ['shop_id', 'inventory_item_id', 'customer_id', 'created_by', 'notes', 'expires_at', 'released_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'released_at' => 'datetime'];
    }
}
