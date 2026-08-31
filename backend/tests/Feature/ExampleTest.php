<?php

namespace Tests\Feature;

use App\Models\PaymentSubmission;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_root_redirects_to_super_admin_login_when_no_admin_session_exists(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('admin.login'));
    }

    public function test_the_root_redirects_to_super_admin_dashboard_when_an_admin_session_exists(): void
    {
        $this->withSession(['admin_user_id' => 1])
            ->get('/')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_super_admin_can_login_without_a_two_factor_code(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
            'is_super_admin' => true,
        ]);

        $this->post(route('admin.login'), ['email' => 'admin@example.test', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('admin_user_id');
    }

    public function test_super_admin_can_open_all_management_pages_and_approve_a_pending_top_up(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin-pages@example.test', 'password' => 'password', 'is_super_admin' => true]);
        $shop = Shop::create(['name' => 'ร้านทดสอบ', 'slug' => 'admin-review-shop', 'status' => 'trialing']);
        $payment = PaymentSubmission::create([
            'shop_id' => $shop->id,
            'status' => 'pending_review',
            'expected_amount' => 300,
            'credit_amount' => 300,
            'slip_path' => 'slips/admin-review.png',
        ]);

        foreach (['admin.dashboard', 'admin.shops.index', 'admin.plans.index', 'admin.top-ups.index', 'admin.logs.index'] as $route) {
            $this->withSession(['admin_user_id' => $admin->id])->get(route($route))->assertOk();
        }
        $this->withSession(['admin_user_id' => $admin->id])
            ->patch(route('admin.top-ups.review', $payment), ['decision' => 'approved', 'review_note' => 'ยอดและบัญชีผู้รับถูกต้อง'])
            ->assertSessionHas('message', 'อนุมัติการเติมเครดิตแล้ว');

        $this->assertDatabaseHas('payment_submissions', ['id' => $payment->id, 'status' => 'verified']);
        $this->assertDatabaseHas('shops', ['id' => $shop->id, 'credit_balance' => 300]);
        $this->assertDatabaseHas('activity_logs', ['shop_id' => $shop->id, 'user_id' => $admin->id, 'event' => 'credit.top_up_approved']);
    }
}
