<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Shop extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'slug', 'description', 'facebook_url', 'line_url', 'phone', 'inventory_copy_footer', 'storefront_enabled', 'hidden_from_directory', 'status', 'trial_ends_at', 'grace_ends_at', 'timezone', 'currency', 'credit_balance'];

    protected function casts(): array
    {
        return ['trial_ends_at' => 'datetime', 'grace_ends_at' => 'datetime', 'credit_balance' => 'integer', 'storefront_enabled' => 'boolean', 'storefront_view_count' => 'integer', 'hidden_from_directory' => 'boolean'];
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

    public function logoUrl(): ?string
    {
        return $this->logo_path ? url("/api/v1/public/shops/{$this->slug}/logo") : null;
    }

    public function bannerUrl(): ?string
    {
        return $this->banner_path ? url("/api/v1/public/shops/{$this->slug}/banner") : null;
    }

    /**
     * Members who should receive billing/expiry notices: the owner and anyone with billing.manage.
     *
     * @return Collection<int, User>
     */
    public function billingRecipients(): Collection
    {
        return ShopMember::query()
            ->where('shop_id', $this->id)
            ->with('user')
            ->get()
            ->filter(fn (ShopMember $member) => $member->role === 'owner' || in_array('billing.manage', $member->permissions ?? [], true))
            ->pluck('user')
            ->filter()
            ->values();
    }
}
