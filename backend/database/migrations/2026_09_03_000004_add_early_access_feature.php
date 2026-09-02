<?php

use App\Models\SubscriptionPlan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * New "ได้ฟีเจอร์ใหม่ก่อนใคร" (early_access) perk — on for Growth/Pro, off
     * for the rest. Merges into the existing features map so admin edits stay.
     */
    public function up(): void
    {
        $onFor = ['growth', 'pro'];

        SubscriptionPlan::query()->get()->each(function (SubscriptionPlan $plan) use ($onFor) {
            $features = $plan->features ?? [];
            if (! array_key_exists('early_access', $features)) {
                $features['early_access'] = in_array($plan->code, $onFor, true);
                $plan->update(['features' => $features]);
            }
        });
    }

    public function down(): void
    {
        SubscriptionPlan::query()->get()->each(function (SubscriptionPlan $plan) {
            $features = $plan->features ?? [];
            unset($features['early_access']);
            $plan->update(['features' => $features]);
        });
    }
};
