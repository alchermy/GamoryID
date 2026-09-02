<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = ['shop_id', 'subscription_plan_id', 'billing_cycle', 'price_paid', 'status', 'starts_at', 'ends_at', 'grace_ends_at', 'auto_renew', 'expiry_reminder_stage'];

    protected function casts(): array
    {
        return ['status' => SubscriptionStatus::class, 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'grace_ends_at' => 'datetime', 'auto_renew' => 'boolean'];
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}
