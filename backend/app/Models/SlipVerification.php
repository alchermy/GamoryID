<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlipVerification extends Model
{
    protected $fillable = ['payment_submission_id', 'provider', 'is_valid', 'amount', 'receiver_account', 'transaction_reference', 'transferred_at', 'response_summary'];

    protected function casts(): array
    {
        return ['is_valid' => 'boolean', 'amount' => 'decimal:2', 'transferred_at' => 'datetime', 'response_summary' => 'array'];
    }
}
