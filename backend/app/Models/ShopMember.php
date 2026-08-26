<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopMember extends Model
{
    protected $fillable = ['shop_id', 'user_id', 'role', 'permissions', 'joined_at'];

    protected function casts(): array
    {
        return ['permissions' => 'array', 'joined_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
