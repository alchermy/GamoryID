<?php

namespace App\Models;

use App\Enums\ShopPermission;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, MustVerifyEmail, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'email', 'password', 'current_shop_id', 'email_verified_at', 'is_super_admin', 'terms_accepted_at', 'terms_version'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
        ];
    }

    public function currentShop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'current_shop_id');
    }

    public function shops(): BelongsToMany
    {
        return $this->belongsToMany(Shop::class, 'shop_members')
            ->withPivot(['role', 'permissions', 'joined_at'])
            ->withTimestamps();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function hasShopPermission(Shop $shop, ShopPermission|string $permission): bool
    {
        $member = ShopMember::query()
            ->where('shop_id', $shop->id)
            ->where('user_id', $this->id)
            ->first();

        if (! $member) {
            return false;
        }

        if ($member->role === 'owner') {
            return true;
        }

        $value = $permission instanceof ShopPermission ? $permission->value : $permission;

        return in_array($value, $member->permissions ?? [], true);
    }
}
