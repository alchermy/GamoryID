<?php

namespace App\Jobs;

use App\Enums\SubscriptionStatus;
use App\Models\PaymentSubmission;
use App\Models\SlipVerification;
use App\Models\Subscription;
use App\Services\SlipVerifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VerifyPaymentSlip implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $submissionId) {}

    public function handle(SlipVerifier $verifier): void
    {
        $submission = PaymentSubmission::with(['plan', 'shop'])->findOrFail($this->submissionId);
        $result = $verifier->verify(Storage::disk($submission->slip_disk)->path($submission->slip_path));
        if ($result['status'] !== 'verified') {
            $submission->update(['status' => 'pending_review', 'review_note' => $result['reason'] ?? 'ตรวจอัตโนมัติไม่สำเร็จ']);

            return;
        }

        $amountMatches = (float) $result['amount'] === (float) $submission->expected_amount;
        $receiverMatches = ! config('services.slipok.receiver_account') || $result['receiver_account'] === config('services.slipok.receiver_account');
        $duplicate = SlipVerification::where('transaction_reference', $result['transaction_reference'])->exists();

        DB::transaction(function () use ($submission, $result, $amountMatches, $receiverMatches, $duplicate) {
            SlipVerification::create([
                'payment_submission_id' => $submission->id,
                'is_valid' => $amountMatches && $receiverMatches && ! $duplicate,
                'amount' => $result['amount'],
                'receiver_account' => $result['receiver_account'],
                'transaction_reference' => $duplicate ? null : $result['transaction_reference'],
                'transferred_at' => $result['transferred_at'],
                'response_summary' => $result['summary'],
            ]);
            if (! $amountMatches || ! $receiverMatches || $duplicate) {
                $submission->update(['status' => 'pending_review', 'review_note' => $duplicate ? 'พบเลขอ้างอิงสลิปซ้ำ' : 'ยอดเงินหรือบัญชีผู้รับไม่ตรง']);

                return;
            }
            $startsAt = now();
            $endsAt = $startsAt->copy()->addDays($submission->plan->duration_days);
            Subscription::create([
                'shop_id' => $submission->shop_id,
                'subscription_plan_id' => $submission->subscription_plan_id,
                'status' => SubscriptionStatus::Active,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'grace_ends_at' => $endsAt->copy()->addDays(14),
            ]);
            $submission->shop->update(['status' => SubscriptionStatus::Active, 'trial_ends_at' => null, 'grace_ends_at' => $endsAt->copy()->addDays(14)]);
            $submission->update(['status' => 'verified', 'provider_reference' => $result['transaction_reference'], 'verified_at' => now()]);
        });
    }
}
