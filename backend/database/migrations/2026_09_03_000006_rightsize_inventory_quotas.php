<?php

use App\Models\SubscriptionPlan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Right-size the active-inventory quotas to real shop sizes (market
     * research: รายย่อย 1–20 · ร้านเล็ก 20–80 · ร้านกลาง 80–300 ·
     * ร้านกลาง-ใหญ่ 300–700 · ร้านใหญ่ 700–1,500 · Supplier 1,500–5,000+):
     *
     *   Free 30 · Starter 150 · Growth 1,000 · Pro 10,000
     */
    public function up(): void
    {
        $limits = ['free' => 30, 'starter' => 150, 'growth' => 1000, 'pro' => 10000];

        foreach ($limits as $code => $limit) {
            SubscriptionPlan::query()->where('code', $code)->update(['active_inventory_limit' => $limit]);
        }
    }

    public function down(): void
    {
        $limits = ['free' => 150, 'starter' => 1000, 'growth' => 5000, 'pro' => 50000];

        foreach ($limits as $code => $limit) {
            SubscriptionPlan::query()->where('code', $code)->update(['active_inventory_limit' => $limit]);
        }
    }
};
