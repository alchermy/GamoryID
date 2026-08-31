<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SlipVerifier
{
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
        if (! config('services.slipok.api_key')) {
            return ['status' => 'pending_review', 'reason' => 'SlipOK ยังไม่ได้ตั้งค่า'];
        }

        $response = Http::timeout(15)
            ->withToken(config('services.slipok.api_key'))
            ->attach('files', file_get_contents($absolutePath), basename($absolutePath))
            ->post(rtrim(config('services.slipok.endpoint'), '/').'/api/line/apikey/'.config('services.slipok.branch_id'));

        if (! $response->successful()) {
            return ['status' => 'pending_review', 'reason' => 'SlipOK ไม่ตอบกลับ', 'http_status' => $response->status()];
        }

        $data = $response->json('data', []);

        return [
            'status' => 'verified',
            'amount' => data_get($data, 'amount'),
            'receiver_account' => data_get($data, 'receiver.account.value'),
            'transaction_reference' => data_get($data, 'transRef'),
            'transferred_at' => data_get($data, 'transDate').' '.data_get($data, 'transTime'),
            'summary' => ['success' => $response->json('success'), 'message' => $response->json('message')],
        ];
    }
}
