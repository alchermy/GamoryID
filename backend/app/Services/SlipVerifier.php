<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SlipVerifier
{
    /**
     * @return array{status: string, amount?: mixed, receiver_account?: mixed, transaction_reference?: mixed, transferred_at?: mixed, summary?: array, reason?: string, http_status?: int}
     */
    public function verify(string $absolutePath): array
    {
        if (config('services.slipok.test_bypass') && app()->environment(['local', 'testing'])) {
            return [
                'status' => 'verified',
                'amount' => null,
                'receiver_account' => null,
                'transaction_reference' => 'test-'.hash_file('sha256', $absolutePath),
                'transferred_at' => now()->toDateTimeString(),
                'summary' => ['mode' => 'test_bypass'],
            ];
        }
        if (! filled(config('services.slipok.api_key')) || ! filled(config('services.slipok.branch_id'))) {
            return ['status' => 'pending_review', 'reason' => 'SlipOK ยังไม่ได้ตั้งค่า'];
        }

        $response = Http::timeout(15)
            ->withHeaders(['x-authorization' => (string) config('services.slipok.api_key')])
            ->attach('files', file_get_contents($absolutePath), basename($absolutePath))
            ->post(rtrim((string) config('services.slipok.endpoint'), '/').'/api/line/apikey/'.config('services.slipok.branch_id'));

        // Transport / auth / quota failures — never a 2xx from SlipOK.
        if ($response->serverError() || in_array($response->status(), [401, 403, 429], true) || $response->status() === 0) {
            return ['status' => 'pending_review', 'reason' => 'SlipOK ไม่ตอบกลับ', 'http_status' => $response->status()];
        }

        // SlipOK returns HTTP 200/400 with { success: false, code, message } for
        // rejected slips (invalid image, duplicate, unsupported bank, ...) and for
        // account-level problems (1003 = package expired, 1004 = quota exhausted).
        if ($response->json('success') !== true) {
            $code = $response->json('code');
            $message = (string) ($response->json('message') ?? 'สลิปไม่ผ่านการตรวจสอบของ SlipOK');
            $accountIssue = in_array((int) $code, [1003, 1004], true);

            return [
                'status' => 'pending_review',
                'reason' => $accountIssue
                    ? "SlipOK ใช้งานไม่ได้ ({$code}): {$message} — ตรวจสลิปด้วยตนเอง"
                    : ($code ? "SlipOK ({$code}): {$message}" : $message),
                'summary' => ['success' => false, 'code' => $code, 'message' => $message, 'account_issue' => $accountIssue],
            ];
        }

        $data = $response->json('data', []);

        return [
            'status' => 'verified',
            'amount' => data_get($data, 'amount'),
            'receiver_account' => data_get($data, 'receiver.account.value'),
            'transaction_reference' => data_get($data, 'transRef'),
            'transferred_at' => trim(data_get($data, 'transDate').' '.data_get($data, 'transTime')),
            'summary' => [
                'success' => true,
                'sending_bank' => data_get($data, 'sendingBank'),
                'receiving_bank' => data_get($data, 'receivingBank'),
                'sender_name' => data_get($data, 'sender.name'),
                'receiver_name' => data_get($data, 'receiver.name'),
            ],
        ];
    }
}
