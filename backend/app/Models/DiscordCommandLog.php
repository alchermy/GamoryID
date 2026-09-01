<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscordCommandLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'interaction_id', 'shop_id', 'user_id', 'discord_user_id',
        'command', 'status', 'latency_ms', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
