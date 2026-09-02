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

        $base = [
            'sale_price_monthly' => null, 'sale_price_yearly' => null,
            'sale_label' => null, 'sale_ends_at' => null,
        ];
        $promo = fn (int $m, int $y) => [
            'sale_price_monthly' => $m, 'sale_price_yearly' => $y,
            'sale_label' => 'โปรเปิดตัว', 'sale_ends_at' => null,
        ];

        return [
            [
                'code' => 'free', 'name' => 'Free Trial', 'sort_order' => 0,
                'price_monthly' => 0, 'price_yearly' => null,
                'active_inventory_limit' => 10, 'member_limit' => 1,
                'features' => $feat(),
                ...$base,
            ],
            [
                'code' => 'starter', 'name' => 'Starter', 'sort_order' => 1,
                'price_monthly' => 250, 'price_yearly' => 2500,
                'active_inventory_limit' => 50, 'member_limit' => 2,
                'features' => $feat('bulk_import', 'activity_log', 'discord'),
                ...$promo(199, 1990),
            ],
            [
                'code' => 'growth', 'name' => 'Growth', 'sort_order' => 2,
                'price_monthly' => 600, 'price_yearly' => 6000,
                'active_inventory_limit' => 250, 'member_limit' => 4,
                'features' => $feat('bulk_import', 'activity_log', 'advanced_export', 'discord', 'analytics', 'early_access'),
                ...$promo(490, 4900),
            ],
            [
                'code' => 'pro', 'name' => 'Pro', 'sort_order' => 3,
                'price_monthly' => 1190, 'price_yearly' => 11900,
                'active_inventory_limit' => 500, 'member_limit' => null,
                'features' => $feat('bulk_import', 'activity_log', 'advanced_export', 'discord', 'analytics', 'early_access', 'priority_support'),
                ...$promo(890, 8900),
            ],
        ];
    }

    public static function syncDefaults(): void
    {
        foreach (self::defaults() as $plan) {
            $attributes = array_merge($plan, ['is_active' => true, 'monthly_days' => 30, 'yearly_days' => 365]);
            static::query()->updateOrCreate(['code' => $plan['code']], $attributes);
        }
    }

    public function scopeSellable($query)
    {
        return $query->where('is_active', true)->where('price_monthly', '>', 0);
    }
}
