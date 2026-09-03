<?php

use App\Models\SubscriptionPlan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Re-sync the canonical catalogue so existing plan rows pick up the new
        // `storefront` feature flag (granted to starter / growth / pro).
        SubscriptionPlan::syncDefaults();
    }

    public function down(): void
    {
        //
    }
};
