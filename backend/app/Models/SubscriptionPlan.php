<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    /** Feature flags a plan can grant. Keys map to entitlement checks. */
    public const FEATURES = [
        'bulk_import',
        'advanced_export',
        'activity_log',
        'discord',
        'analytics',
        'early_access',
        'priority_support',
    ];

    protected $fillable = [
        'name', 'code', 'active_inventory_limit', 'member_limit',
        'price_monthly', 'price_yearly', 'sale_price_monthly', 'sale_price_yearly',
        'sale_label', 'sale_ends_at', 'monthly_days', 'yearly_days',
        'features', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'active_inventory_limit' => 'integer',
            'member_limit' => 'integer',
            'price_monthly' => 'integer',
            'price_yearly' => 'integer',
            'sale_price_monthly' => 'integer',
            'sale_price_yearly' => 'integer',
            'sale_ends_at' => 'datetime',
            'monthly_days' => 'integer',
            'yearly_days' => 'integer',
            'features' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function isFree(): bool
    {
        return $this->code === 'free' || (int) $this->price_monthly === 0;
    }

    public function feature(string $key): bool
    {
        return (bool) ($this->features[$key] ?? false);
    }

    public function daysFor(string $cycle): int
    {
        return $cycle === 'yearly' ? (int) $this->yearly_days : (int) $this->monthly_days;
    }

    /** List price for a cycle, or null when the plan is not sold on that cycle. */
    public function priceFor(string $cycle): ?int
    {
        $value = $cycle === 'yearly' ? $this->price_yearly : $this->price_monthly;

        return $value === null ? null : (int) $value;
    }

    /** Price actually charged: the sale price while a sale is running, otherwise the list price. */
    public function effectivePriceFor(string $cycle): ?int
    {
        $list = $this->priceFor($cycle);
        if ($list === null) {
            return null;
        }
        $sale = $cycle === 'yearly' ? $this->sale_price_yearly : $this->sale_price_monthly;
        if ($sale === null) {
            return $list;
        }
        if ($this->sale_ends_at !== null && $this->sale_ends_at->isPast()) {
            return $list;
        }

        return (int) $sale;
    }

    public function saleIsRunning(string $cycle): bool
    {
        return $this->effectivePriceFor($cycle) !== $this->priceFor($cycle);
    }

    /**
     * Canonical plan catalogue — the source of truth for both the seeder and the
     * data migration that upserts plans on existing installs.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function defaults(): array
    {
        $feat = fn (string ...$on) => collect(self::FEATURES)
            ->mapWithKeys(fn ($key) => [$key => in_array($key, $on, true)])
            ->all();

        return [
            [
                'code' => 'free', 'name' => 'Free', 'sort_order' => 0,
                'price_monthly' => 0, 'price_yearly' => null,
                'active_inventory_limit' => 150, 'member_limit' => 1,
                'features' => $feat(),
            ],
            [
                'code' => 'starter', 'name' => 'Starter', 'sort_order' => 1,
                'price_monthly' => 199, 'price_yearly' => 1990,
                'active_inventory_limit' => 1000, 'member_limit' => 2,
                'features' => $feat('bulk_import', 'activity_log'),
            ],
            [
                'code' => 'growth', 'name' => 'Growth', 'sort_order' => 2,
                'price_monthly' => 499, 'price_yearly' => 4990,
                'active_inventory_limit' => 5000, 'member_limit' => 6,
                'features' => $feat('bulk_import', 'activity_log', 'advanced_export', 'discord', 'analytics', 'early_access'),
            ],
            [
                'code' => 'pro', 'name' => 'Pro', 'sort_order' => 3,
                'price_monthly' => 990, 'price_yearly' => 9900,
                'active_inventory_limit' => 50000, 'member_limit' => null,
                'features' => $feat('bulk_import', 'activity_log', 'advanced_export', 'discord', 'analytics', 'early_access', 'priority_support'),
            ],
        ];
    }

    public static function syncDefaults(): void
    {
        foreach (self::defaults() as $plan) {
            static::query()->updateOrCreate(
                ['code' => $plan['code']],
                array_merge($plan, ['is_active' => true, 'monthly_days' => 30, 'yearly_days' => 365]),
            );
        }
    }

    public function scopeSellable($query)
    {
        return $query->where('is_active', true)->where('price_monthly', '>', 0);
    }
}
