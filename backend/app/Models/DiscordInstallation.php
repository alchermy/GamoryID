<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscordInstallation extends Model
{
    protected $fillable = [
        'shop_id', 'installed_by', 'guild_id', 'guild_name', 'status',
        'bot_permissions', 'installed_at', 'last_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'bot_permissions' => 'array',
            'installed_at' => 'datetime',
            'last_verified_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function installer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installed_by');
    }

    public function channels(): HasMany
    {
        return $this->hasMany(DiscordChannelBinding::class);
    }
}
