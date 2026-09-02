<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\ImportError;
use App\Models\ImportJob;
use App\Models\InventoryItem;
use App\Models\PaymentSubmission;
use App\Models\Sale;
use App\Models\Shop;
use App\Services\DataRetentionLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataRetentionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function shop(): Shop
    {
        return Shop::create(['name' => 'ร้านเก็บข้อมูล', 'slug' => 'retention-'.uniqid(), 'status' => 'active']);
    }

    public function test_it_anonymizes_customers_with_no_recent_activity_but_keeps_active_ones(): void
    {
        config()->set('privacy.customer_contact_months', 24);
        $shop = $this->shop();

        $stale = Customer::create(['shop_id' => $shop->id, 'name' => 'เก่า', 'phone' => '0811111111']);
        $stale->forceFill(['updated_at' => now()->subMonths(30)])->save();

        $activeByRecentSale = Customer::create(['shop_id' => $shop->id, 'name' => 'ยังซื้ออยู่', 'phone' => '0822222222']);
        $activeByRecentSale->forceFill(['updated_at' => now()->subMonths(30)])->save();
        $item = InventoryItem::create(['shop_id' => $shop->id, 'tag' => 'RET01', 'title' => 'x', 'cost' => 0, 'list_price' => 0, 'status' => 'sold']);
        Sale::create(['shop_id' => $shop->id, 'inventory_item_id' => $item->id, 'customer_id' => $activeByRecentSale->id, 'sold_price' => 0, 'cost_snapshot' => 0, 'profit' => 0, 'has_warranty' => false, 'sold_at' => now()->subDays(5)]);

        $recentlyUpdated = Customer::create(['shop_id' => $shop->id, 'name' => 'เพิ่งแก้', 'phone' => '0833333333']);

        app(DataRetentionLifecycle::class)->run();

        $this->assertNotNull($stale->fresh()->anonymized_at);
        $this->assertNull($stale->fresh()->phone);
        $this->assertNull($activeByRecentSale->fresh()->anonymized_at);
        $this->assertNull($recentlyUpdated->fresh()->anonymized_at);
    }

    public function test_it_prunes_old_activity_logs_and_import_history(): void
    {
        config()->set('privacy.activity_log_months', 24);
        config()->set('privacy.import_job_days', 90);
        $shop = $this->shop();

        ActivityLog::create(['shop_id' => $shop->id, 'event' => 'inventory.created', 'created_at' => now()->subMonths(30)]);
        ActivityLog::create(['shop_id' => $shop->id, 'event' => 'inventory.created', 'created_at' => now()->subMonths(2)]);

        $oldJob = ImportJob::create(['shop_id' => $shop->id, 'status' => 'completed', 'disk' => 'private', 'path' => 'x', 'mapping' => [], 'total_rows' => 1]);
        $oldJob->forceFill(['created_at' => now()->subDays(120)])->save();
        ImportError::create(['import_job_id' => $oldJob->id, 'row_number' => 1, 'message' => 'x', 'row_data' => []]);
        $freshJob = ImportJob::create(['shop_id' => $shop->id, 'status' => 'completed', 'disk' => 'private', 'path' => 'y', 'mapping' => [], 'total_rows' => 1]);

        app(DataRetentionLifecycle::class)->run();

        $this->assertDatabaseCount('activity_logs', 1);
        $this->assertDatabaseMissing('import_jobs', ['id' => $oldJob->id]);
        $this->assertDatabaseMissing('import_errors', ['import_job_id' => $oldJob->id]);
        $this->assertDatabaseHas('import_jobs', ['id' => $freshJob->id]);
    }

    public function test_it_deletes_old_slip_files_but_keeps_the_payment_record(): void
    {
        Storage::fake('private');
        config()->set('privacy.slip_file_days', 180);
        $shop = $this->shop();
        Storage::disk('private')->put('slips/old.png', 'x');
        Storage::disk('private')->put('slips/new.png', 'x');

        $old = PaymentSubmission::create(['shop_id' => $shop->id, 'status' => 'verified', 'expected_amount' => 100, 'credit_amount' => 100, 'slip_disk' => 'private', 'slip_path' => 'slips/old.png']);
        $old->forceFill(['created_at' => now()->subDays(200)])->save();
        $new = PaymentSubmission::create(['shop_id' => $shop->id, 'status' => 'verified', 'expected_amount' => 100, 'credit_amount' => 100, 'slip_disk' => 'private', 'slip_path' => 'slips/new.png']);

        app(DataRetentionLifecycle::class)->run();

        Storage::disk('private')->assertMissing('slips/old.png');
        Storage::disk('private')->assertExists('slips/new.png');
        $this->assertDatabaseHas('payment_submissions', ['id' => $old->id, 'slip_path' => null]);
        $this->assertDatabaseHas('payment_submissions', ['id' => $new->id, 'slip_path' => 'slips/new.png']);
    }
}
