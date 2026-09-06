<?php

namespace App\Jobs;

use App\Models\PaymentSubmission;
use App\Models\SlipVerification;
use App\Services\SlipReview;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Re-runs the automated slip check for a submission that is still awaiting review.
 * Top-ups are now checked synchronously at submit time (CreditController::topUp
 * via App\Services\SlipReview), so this job is only for a manual/admin re-check.
 */
class VerifyPaymentSlip implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $submissionId) {}

    public function handle(SlipReview $review): void
    {
        $submission = PaymentSubmission::with('shop')->findOrFail($this->submissionId);
        $log = Log::channel('billing')->withContext([
            'payment_submission_id' => $submission->id,
            'shop_id' => $submission->shop_id,
        ]);

        if (! in_array($submission->status, ['pending', 'pending_review'], true)) {
            $log->info('ข้ามการตรวจสลิป: รายการถูกตรวจสอบแล้ว', ['status' => $submission->status]);

            return;
        }

        $outcome = $review->evaluate(
            Storage::disk($submission->slip_disk)->path($submission->slip_path),
            (float) $submission->expected_amount,
        );

        DB::transaction(function () use ($submission, $outcome) {
            if ($outcome['outcome'] === 'verified' && $outcome['verification']) {
                SlipVerification::updateOrCreate(
                    ['payment_submission_id' => $submission->id],
                    [
                        'is_valid' => true,
                        'amount' => $outcome['verification']['amount'],
                        'receiver_account' => $outcome['verification']['receiver_account'],
                        'transaction_reference' => $outcome['verification']['transaction_reference'],
                        'transferred_at' => $outcome['verification']['transferred_at'],
                        'response_summary' => $outcome['verification']['summary'],
                    ],
                );
            }
            $submission->update([
                'status' => 'pending_review',
                'auto_slip_check' => $outcome['outcome'] === 'verified' ? 'passed' : 'unavailable',
                'provider_reference' => $outcome['verification']['transaction_reference'] ?? $submission->provider_reference,
                'review_note' => $outcome['note'],
            ]);
        });

        $log->info('ตรวจสลิปซ้ำเสร็จ', ['outcome' => $outcome['outcome']]);
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('billing')->error('งานตรวจสลิปล้มเหลว', [
            'payment_submission_id' => $this->submissionId,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }
}
