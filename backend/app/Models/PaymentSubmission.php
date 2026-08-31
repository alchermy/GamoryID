<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSubmission extends Model
{
    protected $fillable = ['shop_id', 'subscription_plan_id', 'submitted_by', 'status', 'expected_amount', 'credit_amount', 'slip_disk', 'slip_path', 'provider_reference', 'idempotency_key', 'review_note', 'verified_at'];

    protected function casts(): array
    {
        return ['expected_amount' => 'decimal:2', 'credit_amount' => 'integer', 'verified_at' => 'datetime'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class)->withTrashed();
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
