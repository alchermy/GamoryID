<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicLegalApiTest extends TestCase
{
    public function test_legal_versions_are_served_without_authentication(): void
    {
        config([
            'legal.terms_version' => '2026-09-03',
            'legal.privacy_version' => '2026-09-03',
            'legal.effective_date' => '2026-09-03',
        ]);

        $this->getJson('/api/v1/public/legal')
            ->assertOk()
            ->assertJsonPath('data.terms_version', '2026-09-03')
            ->assertJsonPath('data.privacy_version', '2026-09-03')
            ->assertJsonPath('data.effective_date', '2026-09-03');
    }
}
