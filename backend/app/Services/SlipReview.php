<?php

namespace App\Services;

use App\Models\SlipVerification;

class SlipReview
{
    public function __construct(private readonly SlipVerifier $verifier) {}

    /**
     * Run the automated slip check and decide what should happen to a top-up.
     *
     * @return array{
     *     outcome: 'verified'|'mismatch'|'invalid'|'unavailable',
     *     note: string,
     *     verification: array{amount: mixed, receiver_account: mixed, transaction_reference: string|null, transferred_at: mixed, summary: array, is_test_bypass: bool}|null
     * }
     */
    public function evaluate(string $absoluteSlipPath, float $expectedAmount): array
    {
        $result = $this->verifier->verify($absoluteSlipPath);

        if (($result['status'] ?? null) !== 'verified') {
            $reason = (string) ($result['reason'] ?? 'ตรวจสลิปอัตโนมัติไม่สำเร็จ');
            // "unavailable" = the checker itself could not run (SlipOK down, not
            // configured, package/quota exhausted). The merchant is let through
            // and an admin reviews. Everything else is SlipOK actively rejecting
            // the slip (no QR, unreadable, unsupported bank, ...) → block.
            $checkerDown = ! array_key_exists('summary', $result)
                || data_get($result, 'summary.account_issue') === true;

            return [
                'outcome' => $checkerDown ? 'unavailable' : 'invalid',
                'note' => $checkerDown
                    ? 'ตรวจสลิปอัตโนมัติไม่ทำงาน ('.$reason.') — รอผู้ดูแลระบบตรวจสอบ'
                    : 'สลิปไม่ถูกต้อง: '.$reason,
                'verification' => null,
            ];
        }

        $isTestBypass = data_get($result, 'summary.mode') === 'test_bypass';
        $amountMatches = $isTestBypass || (float) ($result['amount'] ?? 0) === $expectedAmount;
        $configuredReceiver = config('services.slipok.receiver_account');
        $receiverMatches = $isTestBypass || ! $configuredReceiver || ($result['receiver_account'] ?? null) === $configuredReceiver;
        $reference = $result['transaction_reference'] ?? null;
        $duplicate = ! $isTestBypass && $reference && SlipVerification::where('transaction_reference', $reference)->exists();

        $verification = [
            'amount' => $isTestBypass ? $expectedAmount : ($result['amount'] ?? null),
            'receiver_account' => $result['receiver_account'] ?? null,
            'transaction_reference' => $duplicate ? null : $reference,
            'transferred_at' => $result['transferred_at'] ?? null,
            'summary' => $result['summary'] ?? [],
            'is_test_bypass' => $isTestBypass,
        ];

        if (! $amountMatches) {
            return ['outcome' => 'mismatch', 'note' => 'สลิปไม่ถูกต้อง: ยอดเงินในสลิปไม่ตรงกับจำนวนที่แจ้ง', 'verification' => $verification];
        }
        if (! $receiverMatches) {
            return ['outcome' => 'mismatch', 'note' => 'สลิปไม่ถูกต้อง: บัญชีผู้รับในสลิปไม่ตรงกับบัญชีร้าน', 'verification' => $verification];
        }
        if ($duplicate) {
            return ['outcome' => 'mismatch', 'note' => 'สลิปไม่ถูกต้อง: สลิปนี้ถูกใช้เติมเครดิตไปแล้ว', 'verification' => $verification];
        }

        return [
            'outcome' => 'verified',
            'note' => $isTestBypass
                ? 'โหมดทดสอบ: ตรวจสลิปผ่าน รอผู้ดูแลระบบอนุมัติ'
                : 'ตรวจสลิปอัตโนมัติผ่าน รอผู้ดูแลระบบอนุมัติ',
            'verification' => $verification,
        ];
    }
}
