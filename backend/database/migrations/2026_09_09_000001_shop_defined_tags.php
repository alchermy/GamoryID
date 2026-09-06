<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // 3-letter prefix a shop puts in front of its item codes (PCX-1282).
            // null → derived from the shop name.
            $table->char('tag_prefix', 3)->nullable()->after('currency');
        });

        // Item codes used to be a fixed 5-char random string; they are now
        // "<PREFIX>-<number>" and unique per shop rather than globally.
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('tag', 24)->change();
        });
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropUnique(['tag']);
            $table->unique(['shop_id', 'tag']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropUnique(['shop_id', 'tag']);
            $table->unique(['tag']);
        });
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->char('tag', 5)->change();
        });
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('tag_prefix');
        });
    }
};
