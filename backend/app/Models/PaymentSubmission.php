<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSubmission extends Model
{
    protected $fillable = ['shop_id', 'subscription_plan_id', 'submitted_by', 'status', 'expected_amount', 'slip_disk', 'slip_path', 'provider_reference', 'review_note', 'verified_at'];

    protected function casts(): array
    {
        return ['expected_amount' => 'decimal:2', 'verified_at' => 'datetime'];
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
