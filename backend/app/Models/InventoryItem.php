<?php

namespace App\Models;

use App\Enums\InventoryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'shop_id', 'created_by', 'tag', 'title', 'riot_id', 'username', 'region', 'rank', 'level', 'skin_count',
        'battlepass_level', 'description', 'notes', 'cost', 'list_price', 'status',
        'custom_values', 'lock_version', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InventoryStatus::class,
            'custom_values' => 'array',
            'cost' => 'decimal:2',
            'list_price' => 'decimal:2',
            'archived_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function credentials(): HasOne
    {
        return $this->hasOne(InventoryCredential::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(InventoryMedia::class)
            ->orderByRaw("case when role = 'display' then 0 else 1 end")
            ->orderBy('sort_order');
    }

    public function scopeForShop(Builder $query, Shop|int $shop): Builder
    {
        return $query->where('shop_id', $shop instanceof Shop ? $shop->id : $shop);
    }

    public function getDisplayTagAttribute(): string
    {
        return '#'.$this->tag;
    }
}
