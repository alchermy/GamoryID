<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;

class PaymentController extends Controller
{
    public function plans()
    {
        $plans = SubscriptionPlan::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (SubscriptionPlan $plan) => [
                'id' => $plan->id,
                'code' => $plan->code,
                'name' => $plan->name,
                'sort_order' => $plan->sort_order,
                'is_free' => $plan->isFree(),
                'price_monthly' => $plan->price_monthly,
                'price_yearly' => $plan->price_yearly,
                'sale_price_monthly' => $plan->effectivePriceFor('monthly') !== $plan->price_monthly ? $plan->sale_price_monthly : null,
                'sale_price_yearly' => $plan->price_yearly !== null && $plan->effectivePriceFor('yearly') !== $plan->price_yearly ? $plan->sale_price_yearly : null,
                'sale_label' => $plan->sale_label,
                'sale_ends_at' => $plan->sale_ends_at,
                'monthly_days' => $plan->monthly_days,
                'yearly_days' => $plan->yearly_days,
                'active_inventory_limit' => $plan->active_inventory_limit,
                'member_limit' => $plan->member_limit,
                'features' => collect(SubscriptionPlan::FEATURES)
                    ->mapWithKeys(fn ($key) => [$key => $plan->feature($key)])
                    ->all(),
            ]);

        return response()->json(['data' => $plans]);
    }
}
