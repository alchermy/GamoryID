<?php

use App\Models\SubscriptionPlan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Upsert the four-tier catalogue so existing installs gain Free/Pro and
        // the new pricing/feature columns without running the full seeder.
        SubscriptionPlan::syncDefaults();
    }

    public function down(): void
    {
        SubscriptionPlan::query()->whereIn('code', ['free', 'pro'])->delete();
    }
};
