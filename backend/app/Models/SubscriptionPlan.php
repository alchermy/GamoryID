<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = ['name', 'code', 'active_inventory_limit', 'member_limit', 'price_thb', 'duration_days', 'is_active'];

    protected function casts(): array
    {
        return ['price_thb' => 'decimal:2', 'is_active' => 'boolean'];
    }
}
