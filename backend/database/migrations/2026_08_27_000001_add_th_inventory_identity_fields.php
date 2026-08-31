<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('riot_id', 190)->nullable()->after('title');
            $table->string('username', 500)->nullable()->after('riot_id');
            $table->index(['shop_id', 'riot_id']);
            $table->index(['shop_id', 'username']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropIndex(['shop_id', 'riot_id']);
            $table->dropIndex(['shop_id', 'username']);
            $table->dropColumn(['riot_id', 'username']);
        });
    }
};
