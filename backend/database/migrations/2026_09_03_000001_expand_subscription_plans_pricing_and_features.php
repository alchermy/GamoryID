<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->unsignedInteger('price_monthly')->default(0)->after('member_limit');
            $table->unsignedInteger('price_yearly')->nullable()->after('price_monthly');
            $table->unsignedInteger('sale_price_monthly')->nullable()->after('price_yearly');
            $table->unsignedInteger('sale_price_yearly')->nullable()->after('sale_price_monthly');
            $table->string('sale_label')->nullable()->after('sale_price_yearly');
            $table->timestamp('sale_ends_at')->nullable()->after('sale_label');
            $table->unsignedSmallInteger('monthly_days')->default(30)->after('sale_ends_at');
            $table->unsignedSmallInteger('yearly_days')->default(365)->after('monthly_days');
            $table->json('features')->nullable()->after('yearly_days');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('features');
        });

        // Carry the old single price forward, then retire the legacy columns.
        foreach (DB::table('subscription_plans')->get(['id', 'price_thb', 'duration_days']) as $plan) {
            DB::table('subscription_plans')->where('id', $plan->id)->update([
                'price_monthly' => (int) $plan->price_thb,
                'monthly_days' => (int) ($plan->duration_days ?: 30),
            ]);
        }

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->unsignedInteger('active_inventory_limit')->nullable()->change();
            $table->unsignedInteger('member_limit')->nullable()->change();
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['price_thb', 'duration_days']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('billing_cycle')->default('monthly')->after('subscription_plan_id');
            $table->unsignedInteger('price_paid')->nullable()->after('billing_cycle');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['billing_cycle', 'price_paid']);
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->decimal('price_thb', 10, 2)->default(0)->after('member_limit');
            $table->unsignedSmallInteger('duration_days')->default(30)->after('price_thb');
        });

        foreach (DB::table('subscription_plans')->get(['id', 'price_monthly', 'monthly_days', 'active_inventory_limit', 'member_limit']) as $plan) {
            DB::table('subscription_plans')->where('id', $plan->id)->update([
                'price_thb' => (int) $plan->price_monthly,
                'duration_days' => (int) ($plan->monthly_days ?: 30),
                'active_inventory_limit' => (int) ($plan->active_inventory_limit ?: 1000),
                'member_limit' => (int) ($plan->member_limit ?: 3),
            ]);
        }

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->unsignedInteger('active_inventory_limit')->default(1000)->change();
            $table->unsignedInteger('member_limit')->default(3)->change();
            $table->dropColumn([
                'price_monthly', 'price_yearly', 'sale_price_monthly', 'sale_price_yearly',
                'sale_label', 'sale_ends_at', 'monthly_days', 'yearly_days', 'features', 'sort_order',
            ]);
        });
    }
};
