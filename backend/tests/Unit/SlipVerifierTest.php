<?php

namespace Tests\Unit;

use App\Services\SlipVerifier;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SlipVerifierTest extends TestCase
{
    public function test_local_test_bypass_never_calls_slipok(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('slips/test.png', 'not-an-image-but-not-uploaded');
        config()->set('services.slipok.test_bypass', true);
        config()->set('services.slipok.api_key', null);

        $result = app(SlipVerifier::class)->verify(Storage::disk('private')->path('slips/test.png'));

        $this->assertSame('verified', $result['status']);
        $this->assertSame('test_bypass', $result['summary']['mode']);
        $this->assertStringStartsWith('test-', $result['transaction_reference']);
    }
}
