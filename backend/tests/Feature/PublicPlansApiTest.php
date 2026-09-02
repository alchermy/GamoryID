<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPlansApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_plans_are_served_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/public/plans')->assertOk();

        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertSame(['free', 'starter', 'growth', 'pro'], $codes);

        $growth = collect($response->json('data'))->firstWhere('code', 'growth');
        $this->assertSame(699, $growth['price_monthly']);
        $this->assertSame(6990, $growth['price_yearly']);
        $this->assertTrue($growth['features']['discord']);
        $this->assertFalse($growth['features']['priority_support']);
        $this->assertNull(
            collect($response->json('data'))->firstWhere('code', 'pro')['member_limit'],
        );
    }

    public function test_inactive_plans_are_hidden_and_running_sales_are_surfaced(): void
    {
        SubscriptionPlan::query()->where('code', 'starter')->update([
            'is_active' => false,
        ]);
        SubscriptionPlan::query()->where('code', 'growth')->update([
            'sale_price_monthly' => 499,
            'sale_label' => 'โปรเปิดตัว',
            'sale_ends_at' => now()->addDays(7),
        ]);

        $data = collect($this->getJson('/api/v1/public/plans')->assertOk()->json('data'));

        $this->assertNull($data->firstWhere('code', 'starter'));
        $growth = $data->firstWhere('code', 'growth');
        $this->assertSame(499, $growth['sale_price_monthly']);
        $this->assertSame('โปรเปิดตัว', $growth['sale_label']);
    }
}
