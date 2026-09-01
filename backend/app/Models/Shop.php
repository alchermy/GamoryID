<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shop extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'slug', 'description', 'facebook_url', 'line_url', 'phone', 'inventory_copy_footer', 'status', 'trial_ends_at', 'grace_ends_at', 'timezone', 'currency', 'credit_balance'];

    protected function casts(): array
    {
        return ['trial_ends_at' => 'datetime', 'grace_ends_at' => 'datetime', 'credit_balance' => 'integer'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shop_members')->withPivot(['role', 'permissions', 'joined_at'])->withTimestamps();
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function latestSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function paymentSubmissions(): HasMany
    {
        return $this->hasMany(PaymentSubmission::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function discordInstallation(): HasOne
    {
        return $this->hasOne(DiscordInstallation::class);
    }

    public function isWritable(): bool
    {
        return in_array($this->status, [SubscriptionStatus::Trialing->value, SubscriptionStatus::Active->value], true);
    }
}
