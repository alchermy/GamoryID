<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscordChannelBinding extends Model
{
    protected $fillable = ['discord_installation_id', 'purpose', 'channel_id', 'channel_name', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(DiscordInstallation::class, 'discord_installation_id');
    }
}
