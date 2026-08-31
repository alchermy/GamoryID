<?php

namespace App\Jobs;

use App\Models\PaymentSubmission;
use App\Models\SlipVerification;
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
        $submission = PaymentSubmission::with('shop')->findOrFail($this->submissionId);
        if ($submission->status !== 'pending') {
            return;
        }
        $result = $verifier->verify(Storage::disk($submission->slip_disk)->path($submission->slip_path));
        if ($result['status'] !== 'verified') {
            $submission->update(['status' => 'pending_review', 'review_note' => $result['reason'] ?? 'ตรวจอัตโนมัติไม่สำเร็จ']);

            return;
        }

        $isTestBypass = data_get($result, 'summary.mode') === 'test_bypass';
        $amountMatches = $isTestBypass || (float) $result['amount'] === (float) $submission->expected_amount;
        $receiverMatches = $isTestBypass || ! config('services.slipok.receiver_account') || $result['receiver_account'] === config('services.slipok.receiver_account');
        $duplicate = SlipVerification::where('transaction_reference', $result['transaction_reference'])->exists();

        DB::transaction(function () use ($submission, $result, $isTestBypass, $amountMatches, $receiverMatches, $duplicate) {
            SlipVerification::create([
                'payment_submission_id' => $submission->id,
                'is_valid' => $amountMatches && $receiverMatches && ! $duplicate,
                'amount' => $isTestBypass ? $submission->expected_amount : $result['amount'],
                'receiver_account' => $result['receiver_account'],
                'transaction_reference' => $duplicate ? null : $result['transaction_reference'],
                'transferred_at' => $result['transferred_at'],
                'response_summary' => $result['summary'],
            ]);
            if (! $amountMatches || ! $receiverMatches || $duplicate) {
                $submission->update(['status' => 'pending_review', 'review_note' => $duplicate ? 'พบเลขอ้างอิงสลิปซ้ำ' : 'ยอดเงินหรือบัญชีผู้รับไม่ตรง']);

                return;
            }
            $submission->update([
                'status' => 'pending_review',
                'provider_reference' => $result['transaction_reference'],
                'review_note' => $isTestBypass ? 'โหมดทดสอบ: รอผู้ดูแลระบบอนุมัติ' : 'ตรวจสลิปอัตโนมัติผ่าน รอผู้ดูแลระบบอนุมัติ',
            ]);
        });
    }
}
