<?php

namespace Tests\Unit;

use App\Services\SlipVerifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SlipVerifierTest extends TestCase
{
    private function slipPath(): string
    {
        Storage::fake('private');
        Storage::disk('private')->put('slips/test.png', 'not-an-image-but-not-uploaded');

        return Storage::disk('private')->path('slips/test.png');
    }

    private function configureSlipOk(): void
    {
        config()->set('services.slipok.test_bypass', false);
        config()->set('services.slipok.api_key', 'SLIPOK_TEST_KEY');
        config()->set('services.slipok.branch_id', '65722');
        config()->set('services.slipok.endpoint', 'https://api.slipok.com');
    }

    public function test_local_test_bypass_never_calls_slipok(): void
    {
        config()->set('services.slipok.test_bypass', true);
        config()->set('services.slipok.api_key', null);
        Http::fake();

        $result = app(SlipVerifier::class)->verify($this->slipPath());

        $this->assertSame('verified', $result['status']);
        $this->assertSame('test_bypass', $result['summary']['mode']);
        $this->assertStringStartsWith('test-', $result['transaction_reference']);
        Http::assertNothingSent();
    }

    public function test_missing_configuration_falls_back_to_manual_review(): void
    {
        config()->set('services.slipok.test_bypass', false);
        config()->set('services.slipok.api_key', null);

        $result = app(SlipVerifier::class)->verify($this->slipPath());

        $this->assertSame('pending_review', $result['status']);
        $this->assertSame('SlipOK ยังไม่ได้ตั้งค่า', $result['reason']);
    }

    public function test_a_successful_slipok_response_is_parsed(): void
    {
        $this->configureSlipOk();
        Http::fake(['api.slipok.com/*' => Http::response([
            'success' => true,
            'data' => [
                'amount' => 350.0,
                'transRef' => 'ABC123',
                'transDate' => '20260902',
                'transTime' => '12:30:00',
                'receiver' => ['name' => 'ร้าน GamoryID', 'account' => ['value' => 'xxx-x-x1234-x']],
                'sendingBank' => '004',
            ],
        ], 200)]);

        $result = app(SlipVerifier::class)->verify($this->slipPath());

        $this->assertSame('verified', $result['status']);
        $this->assertEquals(350.0, $result['amount']);
        $this->assertSame('ABC123', $result['transaction_reference']);
        $this->assertSame('xxx-x-x1234-x', $result['receiver_account']);
        Http::assertSent(fn ($request) => $request->hasHeader('x-authorization', 'SLIPOK_TEST_KEY')
            && str_contains($request->url(), '/api/line/apikey/65722'));
    }

    public function test_a_rejected_slip_goes_to_manual_review_with_the_reason(): void
    {
        $this->configureSlipOk();
        Http::fake(['api.slipok.com/*' => Http::response([
            'success' => false, 'code' => 1012, 'message' => 'รายการนี้มีการทำซ้ำ',
        ], 200)]);

        $result = app(SlipVerifier::class)->verify($this->slipPath());

        $this->assertSame('pending_review', $result['status']);
        $this->assertStringContainsString('1012', $result['reason']);
        $this->assertStringContainsString('ทำซ้ำ', $result['reason']);
    }

    public function test_an_auth_or_quota_failure_goes_to_manual_review(): void
    {
        $this->configureSlipOk();
        Http::fake(['api.slipok.com/*' => Http::response(['message' => 'Unauthorized'], 401)]);

        $result = app(SlipVerifier::class)->verify($this->slipPath());

        $this->assertSame('pending_review', $result['status']);
        $this->assertSame(401, $result['http_status']);
    }
}
