<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditTransaction extends Model
{
    protected $fillable = [
        'shop_id', 'payment_submission_id', 'subscription_id', 'subscription_plan_id',
        'type', 'credits', 'balance_after', 'idempotency_key', 'metadata',
    ];

    protected function casts(): array
    {
        return ['credits' => 'integer', 'balance_after' => 'integer', 'metadata' => 'array'];
    }

    public function paymentSubmission()
    {
        return $this->belongsTo(PaymentSubmission::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
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
