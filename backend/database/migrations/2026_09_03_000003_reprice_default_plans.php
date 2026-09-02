<?php

use App\Models\SubscriptionPlan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Lower the launch pricing to be more accessible. Only rows still on the
     * original numbers are touched, so any pricing a Super Admin has already
     * customised is left alone.
     */
    public function up(): void
    {
        $repricing = [
            ['code' => 'starter', 'from' => 299, 'to' => 199, 'yearly' => 1990],
            ['code' => 'growth', 'from' => 699, 'to' => 499, 'yearly' => 4990],
            ['code' => 'pro', 'from' => 1490, 'to' => 990, 'yearly' => 9900],
        ];

        foreach ($repricing as $row) {
            SubscriptionPlan::query()
                ->where('code', $row['code'])
                ->where('price_monthly', $row['from'])
                ->update([
                    'price_monthly' => $row['to'],
                    'price_yearly' => $row['yearly'],
                ]);
        }

        SubscriptionPlan::query()
            ->where('code', 'free')
            ->where('active_inventory_limit', 50)
            ->update(['active_inventory_limit' => 150]);
    }

    public function down(): void
    {
        // One-way pricing change; nothing to roll back.
    }
};
