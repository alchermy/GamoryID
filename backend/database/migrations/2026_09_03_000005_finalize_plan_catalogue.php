<?php

use App\Models\SubscriptionPlan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * One-time canonical reset of the four core plans to the finalised
     * pre-launch pricing and entitlements:
     *
     *   Free    ฿0      · 150 stock  · 1 member
     *   Starter ฿299    · 1,000      · 3 members · import, activity log
     *   Growth  ฿690    · 5,000      · 8 members · + Discord, exports, analytics, early access
     *   Pro     ฿1,490  · 50,000     · unlimited · + priority support
     *
     * Yearly = ×10. This overwrites any test values set in /admin/plans;
     * run a real launch promo afterwards via the sale-price fields.
     */
    public function up(): void
    {
        SubscriptionPlan::syncDefaults();
    }

    public function down(): void
    {
        // Canonical pricing change; nothing to roll back.
    }
};
