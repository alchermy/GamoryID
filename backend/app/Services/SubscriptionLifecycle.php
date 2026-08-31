<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Exceptions\InsufficientCreditsException;
use App\Models\Shop;
use App\Models\Subscription;
use Illuminate\Support\Str;

class SubscriptionLifecycle
{
    public function __construct(private readonly CreditWallet $wallet) {}

    public function run(): void
    {
        Subscription::where('status', SubscriptionStatus::Active->value)
            ->whereNotNull('ends_at')->where('ends_at', '<=', now())
            ->with(['shop', 'plan'])->orderBy('id')->each(function (Subscription $subscription) {
                if ($subscription->auto_renew && $subscription->shop && $subscription->plan) {
                    try {
                        $this->wallet->purchase($subscription->shop, $subscription->plan, true, (string) Str::uuid(), 'subscription_renewal');

                        return;
                    } catch (InsufficientCreditsException) {
                        // The shop goes to the normal read-only grace period below.
                    }
                }

                $graceEndsAt = $subscription->ends_at->copy()->addDays(14);
                $subscription->update(['status' => SubscriptionStatus::GraceReadOnly, 'grace_ends_at' => $graceEndsAt]);
                Shop::whereKey($subscription->shop_id)->update([
                    'status' => SubscriptionStatus::GraceReadOnly,
                    'grace_ends_at' => $graceEndsAt,
                ]);
            });

        Shop::whereIn('status', [SubscriptionStatus::Trialing->value, SubscriptionStatus::Active->value])
            ->whereNotNull('trial_ends_at')->where('trial_ends_at', '<', now())
            ->update(['status' => SubscriptionStatus::GraceReadOnly->value]);
        Shop::where('status', SubscriptionStatus::GraceReadOnly->value)
            ->whereNotNull('grace_ends_at')->where('grace_ends_at', '<', now())
            ->update(['status' => SubscriptionStatus::Suspended->value]);
    }
}
