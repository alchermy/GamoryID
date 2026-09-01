<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscordSetupCode extends Model
{
    protected $fillable = ['shop_id', 'created_by', 'token_hash', 'expires_at', 'used_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }
}
