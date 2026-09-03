<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\ImportError;
use App\Models\ImportJob;
use App\Models\PaymentSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Psr\Log\LoggerInterface;

/**
 * Applies the retention windows in config/privacy.php. Runs daily from the
 * scheduler. Everything here is additive-safe: it only anonymizes or prunes
 * data that is past its window.
 */
class DataRetentionLifecycle
{
    public function run(): void
    {
        $log = Log::channel('scheduler');

        $this->anonymizeInactiveCustomers($log);
        $this->pruneActivityLogs($log);
        $this->pruneImportHistory($log);
        $this->pruneSlipFiles($log);
        $this->pruneStorefrontViewDaily($log);
    }

    private function anonymizeInactiveCustomers(LoggerInterface $log): void
    {
        $months = (int) config('privacy.customer_contact_months');
        if ($months <= 0) {
            return;
        }
        $cutoff = now()->subMonths($months);
        $count = 0;

        Customer::query()
            ->whereNull('anonymized_at')
            ->where('updated_at', '<', $cutoff)
            ->whereDoesntHave('sales', fn ($q) => $q->where('sold_at', '>=', $cutoff))
            ->orderBy('id')
            ->chunkById(200, function ($customers) use (&$count) {
                foreach ($customers as $customer) {
                    $customer->anonymize();
                    $count++;
                }
            });

        if ($count > 0) {
            $log->info('ลบข้อมูลติดต่อลูกค้าที่ไม่มีความเคลื่อนไหวตามนโยบายเก็บข้อมูล', ['customers' => $count, 'older_than_months' => $months]);
        }
    }

    private function pruneActivityLogs(LoggerInterface $log): void
    {
        $months = (int) config('privacy.activity_log_months');
        if ($months <= 0) {
            return;
        }
        $deleted = ActivityLog::where('created_at', '<', now()->subMonths($months))->delete();
        if ($deleted > 0) {
            $log->info('ลบบันทึกกิจกรรมเก่าตามนโยบายเก็บข้อมูล', ['rows' => $deleted, 'older_than_months' => $months]);
        }
    }

    private function pruneImportHistory(LoggerInterface $log): void
    {
        $days = (int) config('privacy.import_job_days');
        if ($days <= 0) {
            return;
        }
        $oldJobIds = ImportJob::where('created_at', '<', now()->subDays($days))->pluck('id');
        if ($oldJobIds->isEmpty()) {
            return;
        }
        ImportError::whereIn('import_job_id', $oldJobIds)->delete();
        ImportJob::whereIn('id', $oldJobIds)->delete();
        $log->info('ลบประวัติงานนำเข้าเก่าตามนโยบายเก็บข้อมูล', ['jobs' => $oldJobIds->count(), 'older_than_days' => $days]);
    }

    private function pruneSlipFiles(LoggerInterface $log): void
    {
        $days = (int) config('privacy.slip_file_days');
        if ($days <= 0) {
            return;
        }
        $count = 0;

        PaymentSubmission::query()
            ->whereNotNull('slip_path')
            ->where('created_at', '<', now()->subDays($days))
            ->orderBy('id')
            ->chunkById(200, function ($submissions) use (&$count) {
                foreach ($submissions as $submission) {
                    $disk = $submission->slip_disk ?: 'private';
                    if ($submission->slip_path && Storage::disk($disk)->exists($submission->slip_path)) {
                        Storage::disk($disk)->delete($submission->slip_path);
                    }
                    $submission->update(['slip_path' => null]);
                    $count++;
                }
            });

        if ($count > 0) {
            $log->info('ลบไฟล์สลิปเก่าตามนโยบายเก็บข้อมูล (เก็บเฉพาะรายการชำระเงิน)', ['slips' => $count, 'older_than_days' => $days]);
        }
    }

    private function pruneStorefrontViewDaily(LoggerInterface $log): void
    {
        $days = (int) config('privacy.storefront_view_days');
        if ($days <= 0) {
            return;
        }

        $deleted = DB::table('shop_view_daily')
            ->where('date', '<', now()->subDays($days)->toDateString())
            ->delete();

        if ($deleted > 0) {
            $log->info('ลบสถิติยอดเข้าชมร้านรายวันที่เกินช่วงเก็บข้อมูล', ['rows' => $deleted, 'older_than_days' => $days]);
        }
    }
}
