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
        'shop_id', 'created_by', 'tag', 'title', 'username', 'email', 'region', 'rank', 'level', 'skin_count',
        'battlepass_level', 'description', 'notes', 'cost', 'list_price', 'status',
        'custom_values', 'lock_version', 'archived_at', 'hidden_from_directory',
    ];

    protected function casts(): array
    {
        return [
            'status' => InventoryStatus::class,
            'custom_values' => 'array',
            'cost' => 'decimal:2',
            'list_price' => 'decimal:2',
            'view_count' => 'integer',
            'hidden_from_directory' => 'boolean',
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

    /**
     * The item's name for display. Falls back to the tag when no title is set
     * (imports don't require a ชื่อรายการ).
     */
    public function getListingNameAttribute(): string
    {
        return trim((string) $this->title) !== '' ? $this->title : '#'.$this->tag;
    }

    /** "#TAG · ชื่อรายการ", or just "#TAG" when the item has no title. */
    public function getTaggedNameAttribute(): string
    {
        return trim((string) $this->title) !== '' ? '#'.$this->tag.' · '.$this->title : '#'.$this->tag;
    }
}
