<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('trialing')->index();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->string('timezone')->default('Asia/Bangkok');
            $table->string('currency', 3)->default('THB');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_shop_id')->nullable()->after('id')->constrained('shops')->nullOnDelete();
            $table->boolean('is_super_admin')->default(false)->after('password');
            $table->text('two_factor_secret')->nullable()->after('is_super_admin');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
        });

        Schema::create('shop_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('staff');
            $table->json('permissions')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['shop_id', 'user_id']);
        });

        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->unsignedInteger('active_inventory_limit');
            $table->unsignedInteger('member_limit');
            $table->decimal('price_thb', 10, 2);
            $table->unsignedSmallInteger('duration_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('trialing')->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamps();
            $table->index(['shop_id', 'status']);
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('tag', 5)->unique();
            $table->string('title');
            $table->string('region', 12)->nullable()->index();
            $table->string('rank', 80)->nullable()->index();
            $table->unsignedInteger('level')->nullable();
            $table->unsignedInteger('skin_count')->default(0);
            $table->unsignedInteger('battlepass_level')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->decimal('list_price', 12, 2)->default(0);
            $table->string('status')->default('available')->index();
            $table->json('custom_values')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['shop_id', 'status', 'updated_at']);
            $table->index(['shop_id', 'region', 'rank']);
        });

        Schema::create('inventory_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->longText('encrypted_payload');
            $table->unsignedSmallInteger('key_version')->default(1);
            $table->timestamp('last_revealed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key');
            $table->string('type');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['shop_id', 'key']);
        });

        Schema::create('inventory_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('private');
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 32)->nullable();
            $table->string('line_id')->nullable();
            $table->string('facebook_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['shop_id', 'name']);
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['shop_id', 'inventory_item_id', 'released_at']);
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('sold_price', 12, 2);
            $table->decimal('cost_snapshot', 12, 2);
            $table->decimal('profit', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamp('sold_at');
            $table->timestamps();
            $table->index(['shop_id', 'sold_at']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');
            $table->nullableMorphs('subject');
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['shop_id', 'created_at']);
        });

        Schema::create('import_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->string('disk')->default('private');
            $table->string('path');
            $table->json('mapping');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_job_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('field')->nullable();
            $table->text('message');
            $table->json('row_data')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->decimal('expected_amount', 10, 2);
            $table->string('slip_disk')->default('private');
            $table->string('slip_path');
            $table->string('provider_reference')->nullable()->unique();
            $table->text('review_note')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('slip_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_submission_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('slipok');
            $table->boolean('is_valid')->default(false);
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('receiver_account')->nullable();
            $table->string('transaction_reference')->nullable()->unique();
            $table->timestamp('transferred_at')->nullable();
            $table->json('response_summary')->nullable();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('slip_verifications');
        Schema::dropIfExists('payment_submissions');
        Schema::dropIfExists('import_errors');
        Schema::dropIfExists('import_jobs');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('inventory_media');
        Schema::dropIfExists('custom_field_definitions');
        Schema::dropIfExists('inventory_credentials');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('shop_members');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_shop_id');
            $table->dropColumn(['is_super_admin', 'two_factor_secret', 'two_factor_confirmed_at']);
        });
        Schema::dropIfExists('shops');
    }
};
