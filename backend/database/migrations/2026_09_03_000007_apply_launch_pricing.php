<?php

use App\Models\SubscriptionPlan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Final launch catalogue (canonical). Re-syncs the four core plans from
     * SubscriptionPlan::defaults(), which now carries the launch promo:
     *
     *   Free Trial ฟรี      · 10 ไอดี  · 1 คน
     *   Starter    ฿250→199 · 50 ไอดี  · 2 คน · import, activity log, Discord
     *   Growth ⭐  ฿600→490 · 250 ไอดี · 4 คน · + exports, analytics, early access
     *   Pro        ฿1,190→890 · 500 ไอดี · ไม่จำกัด · + priority support
     *
     * โปรเปิดตัว has no end date — set one in /admin/plans when the promo closes.
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
