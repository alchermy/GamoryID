<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->unsignedBigInteger('credit_balance')->default(0)->after('currency');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('auto_renew')->default(false)->after('grace_ends_at');
        });

        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->foreignId('subscription_plan_id')->nullable()->change();
            $table->unsignedBigInteger('credit_amount')->nullable()->after('expected_amount');
            $table->uuid('idempotency_key')->nullable()->unique()->after('provider_reference');
        });

        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_submission_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->bigInteger('credits');
            $table->unsignedBigInteger('balance_after');
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['shop_id', 'created_at']);
            $table->index(['shop_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');

        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['credit_amount', 'idempotency_key']);
            $table->foreignId('subscription_plan_id')->nullable(false)->change();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('auto_renew');
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('credit_balance');
        });
    }
};
