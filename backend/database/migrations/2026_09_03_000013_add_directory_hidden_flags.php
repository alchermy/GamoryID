<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->boolean('hidden_from_directory')->default(false)->after('storefront_enabled');
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->boolean('hidden_from_directory')->default(false)->after('view_count');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('hidden_from_directory');
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('hidden_from_directory');
        });
    }
};
