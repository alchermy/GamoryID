<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TermsReconsentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{User, Shop}
     */
    private function owner(?string $termsVersion, bool $superAdmin = false): array
    {
        $shop = Shop::create(['name' => 'ร้าน '.uniqid(), 'slug' => 'terms-'.uniqid(), 'status' => 'active']);
        $user = User::create([
            'name' => 'เจ้าของร้าน',
            'email' => uniqid().'@example.test',
            'password' => 'password',
            'current_shop_id' => $shop->id,
            'email_verified_at' => now(),
            'terms_version' => $termsVersion,
            'terms_accepted_at' => $termsVersion ? now() : null,
        ]);
        if ($superAdmin) {
            $user->forceFill(['is_super_admin' => true])->save();
        }
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $user->id, 'role' => 'owner', 'permissions' => [], 'joined_at' => now()]);

        return [$user, $shop];
    }

    private function req(User $user, Shop $shop, string $method, string $uri, array $data = [])
    {
        return $this->actingAs($user)->withHeader('X-Shop-Id', (string) $shop->id)->json($method, $uri, $data);
    }

    public function test_a_user_on_an_old_terms_version_is_blocked_from_mutating_requests(): void
    {
        [$user, $shop] = $this->owner('2020-01-01');

        $this->req($user, $shop, 'POST', '/api/v1/inventory', ['title' => 'x'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'TERMS_REACCEPT_REQUIRED');
    }

    public function test_read_only_requests_still_pass_while_terms_are_stale(): void
    {
        [$user, $shop] = $this->owner('2020-01-01');

        $this->req($user, $shop, 'GET', '/api/v1/dashboard')->assertOk();
        $this->req($user, $shop, 'GET', '/api/v1/inventory')->assertOk();
    }

    public function test_the_session_payload_reports_whether_terms_are_current(): void
    {
        [$stale] = $this->owner('2020-01-01');
        $this->actingAs($stale)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.terms_current', false);

        [$fresh] = $this->owner(config('legal.terms_version'));
        $this->actingAs($fresh)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.terms_current', true);
    }

    public function test_accepting_the_terms_clears_the_gate(): void
    {
        [$user, $shop] = $this->owner('2020-01-01');

        $this->actingAs($user)->postJson('/api/v1/terms/accept')
            ->assertOk()
            ->assertJsonPath('user.terms_current', true);

        $user->refresh();
        $this->assertSame(config('legal.terms_version'), $user->terms_version);
        $this->assertNotNull($user->terms_accepted_at);

        // A mutating request now gets past the gate (422 = validation, not 409).
        $this->req($user, $shop, 'POST', '/api/v1/inventory', [])->assertStatus(422);
    }

    public function test_super_admins_bypass_the_terms_gate(): void
    {
        [$user, $shop] = $this->owner('2020-01-01', superAdmin: true);

        // Reaches validation (422) rather than the terms 409.
        $this->req($user, $shop, 'POST', '/api/v1/inventory', [])->assertStatus(422);
    }
}
