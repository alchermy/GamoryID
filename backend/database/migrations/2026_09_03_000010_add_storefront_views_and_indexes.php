<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->unsignedBigInteger('view_count')->default(0)->after('status');
            $table->index('view_count');
            $table->index(['status', 'list_price']);
            $table->index(['status', 'view_count']);
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->unsignedBigInteger('storefront_view_count')->default(0)->after('storefront_enabled');
            $table->index(['storefront_enabled', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropIndex(['view_count']);
            $table->dropIndex(['status', 'list_price']);
            $table->dropIndex(['status', 'view_count']);
            $table->dropColumn('view_count');
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->dropIndex(['storefront_enabled', 'deleted_at']);
            $table->dropColumn('storefront_view_count');
        });
    }
};
