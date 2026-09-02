<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\InventoryItem;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\SubscriptionPlan;

/**
 * Resolves what a shop's current plan lets it do — the effective plan (paid
 * subscription, trial, or the Free fallback), its quotas, and its feature flags.
 */
class PlanEntitlements
{
    /** @var array<int, SubscriptionPlan> */
    private array $planCache = [];

    private ?SubscriptionPlan $freePlan = null;

    public function effectivePlan(Shop $shop): SubscriptionPlan
    {
        if (isset($this->planCache[$shop->id])) {
            return $this->planCache[$shop->id];
        }

        $subscription = $shop->subscriptions()
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value])
            ->latest('id')
            ->with('plan')
            ->first();

        if ($subscription?->plan) {
            return $this->planCache[$shop->id] = $subscription->plan;
        }

        // A shop still inside its trial window (even without an explicit
        // subscription row) gets the full trial-tier experience.
        if ($shop->status === SubscriptionStatus::Trialing->value && $shop->trial_ends_at?->isFuture()) {
            return $this->planCache[$shop->id] = $this->trialPlan();
        }

        return $this->planCache[$shop->id] = $this->freePlan();
    }

    private ?SubscriptionPlan $trialPlan = null;

    public function trialPlan(): SubscriptionPlan
    {
        return $this->trialPlan ??= SubscriptionPlan::query()
            ->whereIn('code', ['growth', 'pro', 'starter'])
            ->orderByRaw("code = 'growth' desc, code = 'pro' desc")
            ->first() ?? $this->freePlan();
    }

    public function freePlan(): SubscriptionPlan
    {
        return $this->freePlan ??= SubscriptionPlan::query()->firstOrCreate(
            ['code' => 'free'],
            [
                'name' => 'Free',
                'price_monthly' => 0,
                'active_inventory_limit' => 50,
                'member_limit' => 1,
                'features' => [],
                'monthly_days' => 30,
                'yearly_days' => 365,
                'sort_order' => 0,
                'is_active' => true,
            ],
        );
    }

    /** null = unlimited */
    public function inventoryLimit(Shop $shop): ?int
    {
        return $this->effectivePlan($shop)->active_inventory_limit;
    }

    /** null = unlimited */
    public function memberLimit(Shop $shop): ?int
    {
        return $this->effectivePlan($shop)->member_limit;
    }

    public function can(Shop $shop, string $feature): bool
    {
        return $this->effectivePlan($shop)->feature($feature);
    }

    public function ensureFeature(Shop $shop, string $feature): void
    {
        abort_unless(
            $this->can($shop, $feature),
            403,
            'แพ็กเกจของคุณยังไม่รองรับฟีเจอร์นี้ อัปเกรดได้ที่หน้าแพ็กเกจ',
        );
    }

    public function ensureInventoryCapacity(Shop $shop, int $additional = 1): void
    {
        $limit = $this->inventoryLimit($shop);
        if ($limit === null) {
            return;
        }
        $active = InventoryItem::forShop($shop)->whereIn('status', ['available', 'reserved'])->count();
        abort_if($active + $additional > $limit, 422, "สต็อกพร้อมขายเต็มตามแพ็กเกจ ({$limit} รายการ)");
    }

    public function ensureMemberCapacity(Shop $shop): void
    {
        $limit = $this->memberLimit($shop);
        if ($limit === null) {
            return;
        }
        $count = ShopMember::where('shop_id', $shop->id)->count();
        abort_if($count >= $limit, 422, "จำนวนสมาชิกเต็มตามแพ็กเกจ ({$limit} คน)");
    }

    /** Usage snapshot for the billing UI. */
    public function usage(Shop $shop): array
    {
        return [
            'inventory_active' => InventoryItem::forShop($shop)->whereIn('status', ['available', 'reserved'])->count(),
            'members' => ShopMember::where('shop_id', $shop->id)->count(),
        ];
    }

    /** Everything the merchant UI needs to render plan state + gate features. */
    public function summary(Shop $shop): array
    {
        $plan = $this->effectivePlan($shop);
        $paid = $shop->subscriptions()
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value])
            ->latest('id')->first();

        return [
            'status' => $shop->status,
            'trial_ends_at' => $shop->trial_ends_at,
            'grace_ends_at' => $shop->grace_ends_at,
            'writable' => $shop->isWritable(),
            'billing_cycle' => $paid?->billing_cycle,
            'current_period_ends_at' => $paid?->ends_at,
            'effective_plan' => [
                'code' => $plan->code,
                'name' => $plan->name,
                'is_free' => $plan->isFree(),
                'active_inventory_limit' => $plan->active_inventory_limit,
                'member_limit' => $plan->member_limit,
                'features' => collect(SubscriptionPlan::FEATURES)
                    ->mapWithKeys(fn ($key) => [$key => $plan->feature($key)])
                    ->all(),
            ],
            'usage' => $this->usage($shop),
        ];
    }
}
