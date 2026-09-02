<?php

namespace Tests\Feature;

use App\Models\PaymentSubmission;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\PaymentReviewedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_table_uses_the_requested_columns_and_links_to_shop_detail(): void
    {
        $admin = $this->admin();
        [$shop, $plan] = $this->shopWithSubscription();
        $staff = User::create(['name' => 'พนักงาน', 'email' => 'staff-admin@example.test', 'password' => 'password']);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $staff->id, 'role' => 'staff', 'permissions' => [], 'joined_at' => now()]);

        $this->withSession(['admin_user_id' => $admin->id])->get(route('admin.shops.index'))
            ->assertOk()
            ->assertSeeInOrder(['ร้านค้า', 'พนักงาน', 'Package', 'วันที่สมัคร', 'วันที่หมดอายุ', 'เครดิตคงเหลือ', 'สถานะ', 'Action'])
            ->assertSee($shop->name)
            ->assertSee($plan->name)
            ->assertSee(route('admin.shops.show', $shop), false);
    }

    public function test_super_admin_can_create_edit_archive_and_restore_a_shop(): void
    {
        $admin = $this->admin();

        $response = $this->withSession(['admin_user_id' => $admin->id])->post(route('admin.shops.store'), [
            'name' => 'ร้านใหม่', 'slug' => 'new-shop', 'owner_name' => 'เจ้าของใหม่',
            'owner_email' => 'new-owner@example.test', 'password' => 'strong-password',
            'password_confirmation' => 'strong-password', 'trial_days' => 30,
        ]);
        $shop = Shop::where('slug', 'new-shop')->firstOrFail();
        $response->assertRedirect(route('admin.shops.show', $shop));
        $this->assertDatabaseHas('shop_members', ['shop_id' => $shop->id, 'role' => 'owner']);
        $this->assertDatabaseHas('subscriptions', ['shop_id' => $shop->id, 'status' => 'trialing']);

        $this->withSession(['admin_user_id' => $admin->id])->patch(route('admin.shops.update', $shop), [
            'name' => 'ร้านใหม่แก้ไข', 'slug' => 'new-shop-edited', 'status' => 'active',
            'description' => 'ร้านทดสอบ', 'facebook_url' => null, 'line_url' => null, 'phone' => '0812345678',
        ])->assertRedirect(route('admin.shops.show', $shop));
        $this->assertDatabaseHas('shops', ['id' => $shop->id, 'name' => 'ร้านใหม่แก้ไข', 'status' => 'active']);

        $this->withSession(['admin_user_id' => $admin->id])->delete(route('admin.shops.destroy', $shop))->assertRedirect(route('admin.shops.index'));
        $this->assertSoftDeleted('shops', ['id' => $shop->id]);
        $archived = Shop::withTrashed()->findOrFail($shop->id);
        $this->withSession(['admin_user_id' => $admin->id])->get(route('admin.shops.show', $archived))->assertOk()->assertSee('เก็บถาวร');

        $this->withSession(['admin_user_id' => $admin->id])->patch(route('admin.shops.restore', $archived))->assertRedirect();
        $this->assertDatabaseHas('shops', ['id' => $shop->id, 'deleted_at' => null, 'status' => 'suspended']);
    }

    public function test_shop_detail_shows_history_and_admin_can_toggle_auto_renew(): void
    {
        $admin = $this->admin();
        [$shop, $plan, $subscription] = $this->shopWithSubscription();
        PaymentSubmission::create([
            'shop_id' => $shop->id, 'status' => 'rejected', 'expected_amount' => 500,
            'credit_amount' => 500, 'slip_path' => 'slips/history.png', 'review_note' => 'ยอดไม่ตรง',
        ]);

        $this->withSession(['admin_user_id' => $admin->id])->get(route('admin.shops.show', $shop))
            ->assertOk()->assertSee('ประวัติการสมัครแพ็กเกจ')->assertSee($plan->name)
            ->assertSee('ประวัติการเติมเครดิต')->assertSee('ยอดไม่ตรง');

        $this->withSession(['admin_user_id' => $admin->id])->patch(route('admin.shops.auto-renew', $shop), ['auto_renew' => 1])
            ->assertSessionHas('message', 'เปิดต่ออายุอัตโนมัติแล้ว');
        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id, 'auto_renew' => true]);
    }

    public function test_plan_index_uses_cards_and_edit_form_has_its_own_page(): void
    {
        $admin = $this->admin();
        $plan = SubscriptionPlan::create(['name' => 'Growth', 'code' => 'growth', 'active_inventory_limit' => 5000, 'member_limit' => 8, 'price_thb' => 699, 'duration_days' => 30, 'is_active' => true]);

        $this->withSession(['admin_user_id' => $admin->id])->get(route('admin.plans.index'))
            ->assertOk()->assertSee('plan-card', false)->assertSee(route('admin.plans.edit', $plan), false)
            ->assertDontSee('name="price_thb"', false);
        $this->withSession(['admin_user_id' => $admin->id])->get(route('admin.plans.edit', $plan))
            ->assertOk()->assertSee('name="price_thb"', false)->assertSee('บันทึกแพ็กเกจ');
    }

    public function test_top_up_review_requires_a_reason_and_records_rejection(): void
    {
        $admin = $this->admin();
        [$shop] = $this->shopWithSubscription();
        $payment = PaymentSubmission::create([
            'shop_id' => $shop->id, 'status' => 'pending_review', 'expected_amount' => 350,
            'credit_amount' => 350, 'slip_path' => 'slips/reject.png',
        ]);

        $this->withSession(['admin_user_id' => $admin->id])->patch(route('admin.top-ups.review', $payment), ['decision' => 'rejected'])
            ->assertSessionHasErrors('review_note');
        $this->assertDatabaseHas('payment_submissions', ['id' => $payment->id, 'status' => 'pending_review']);

        $this->withSession(['admin_user_id' => $admin->id])->patch(route('admin.top-ups.review', $payment), [
            'decision' => 'rejected', 'review_note' => 'ชื่อบัญชีผู้รับไม่ตรง',
        ])->assertSessionHas('message', 'ไม่อนุมัติการเติมเครดิตแล้ว');
        $this->assertDatabaseHas('payment_submissions', ['id' => $payment->id, 'status' => 'rejected', 'review_note' => 'ชื่อบัญชีผู้รับไม่ตรง']);
    }

    public function test_top_up_list_has_index_filters_and_a_dedicated_review_page(): void
    {
        $admin = $this->admin();
        [$shop] = $this->shopWithSubscription();
        $otherShop = Shop::create(['name' => 'ร้านอื่น', 'slug' => 'other-shop', 'status' => 'active']);
        $payment = PaymentSubmission::create([
            'shop_id' => $shop->id, 'status' => 'pending', 'expected_amount' => 420,
            'credit_amount' => 420, 'slip_path' => 'slips/pending.png',
        ]);
        PaymentSubmission::create([
            'shop_id' => $otherShop->id, 'status' => 'verified', 'expected_amount' => 100,
            'credit_amount' => 100, 'slip_path' => 'slips/verified.png',
        ]);

        $this->withSession(['admin_user_id' => $admin->id])->get(route('admin.top-ups.index', [
            'q' => $shop->name, 'date' => $payment->created_at->format('Y-m-d'), 'status' => 'pending',
        ]))->assertOk()
            ->assertSeeInOrder(['#', 'ร้านค้า', 'วันที่ส่ง', 'เครดิต', 'ผู้ส่ง', 'สถานะ', 'Action'])
            ->assertSee($shop->name)->assertDontSee($otherShop->name)
            ->assertSee(route('admin.top-ups.show', $payment), false)
            ->assertDontSee('filter-tabs', false);

        $this->withSession(['admin_user_id' => $admin->id])->get(route('admin.top-ups.show', $payment))
            ->assertOk()->assertSee('สลิปการเติมเครดิต')->assertSee('ข้อมูลรายการ')
            ->assertSee('ปฏิเสธ')->assertSee('อนุมัติ');

        $this->withSession(['admin_user_id' => $admin->id])->patch(route('admin.top-ups.review', $payment), [
            'decision' => 'approved',
        ])->assertRedirect(route('admin.top-ups.show', $payment));
        $this->assertDatabaseHas('payment_submissions', [
            'id' => $payment->id, 'status' => 'verified', 'review_note' => 'ตรวจสอบสลิปและยอดเงินถูกต้อง',
        ]);
        $this->assertDatabaseHas('shops', ['id' => $shop->id, 'credit_balance' => 1220]);
    }

    public function test_reviewing_a_top_up_emails_the_shop_billing_recipients(): void
    {
        Notification::fake();
        $admin = $this->admin();
        [$shop] = $this->shopWithSubscription();
        $owner = User::where('current_shop_id', $shop->id)->firstOrFail();

        $approved = PaymentSubmission::create([
            'shop_id' => $shop->id, 'status' => 'pending_review', 'expected_amount' => 300,
            'credit_amount' => 300, 'slip_path' => 'slips/ok.png',
        ]);
        $this->withSession(['admin_user_id' => $admin->id])
            ->patch(route('admin.top-ups.review', $approved), ['decision' => 'approved'])
            ->assertRedirect(route('admin.top-ups.show', $approved));

        $rejected = PaymentSubmission::create([
            'shop_id' => $shop->id, 'status' => 'pending_review', 'expected_amount' => 300,
            'credit_amount' => 300, 'slip_path' => 'slips/bad.png',
        ]);
        $this->withSession(['admin_user_id' => $admin->id])
            ->patch(route('admin.top-ups.review', $rejected), ['decision' => 'rejected', 'review_note' => 'ยอดเงินไม่ตรง'])
            ->assertRedirect(route('admin.top-ups.show', $rejected));

        Notification::assertSentToTimes($owner, PaymentReviewedNotification::class, 2);
    }

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => uniqid('admin-').'@example.test', 'password' => 'password', 'is_super_admin' => true]);
    }

    private function shopWithSubscription(): array
    {
        $shop = Shop::create(['name' => 'ร้านทดสอบ', 'slug' => uniqid('shop-'), 'status' => 'active', 'credit_balance' => 800]);
        $owner = User::create(['name' => 'เจ้าของร้าน', 'email' => uniqid('owner-').'@example.test', 'password' => 'password', 'current_shop_id' => $shop->id]);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $owner->id, 'role' => 'owner', 'permissions' => [], 'joined_at' => now()]);
        $plan = SubscriptionPlan::create(['name' => 'Starter', 'code' => uniqid('starter-'), 'active_inventory_limit' => 1000, 'member_limit' => 3, 'price_thb' => 299, 'duration_days' => 30, 'is_active' => true]);
        $subscription = Subscription::create(['shop_id' => $shop->id, 'subscription_plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addDays(30)]);

        return [$shop, $plan, $subscription];
    }
}
