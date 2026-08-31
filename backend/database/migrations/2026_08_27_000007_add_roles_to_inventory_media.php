<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_media', function (Blueprint $table) {
            $table->string('role', 16)->default('detail')->after('inventory_item_id')->index();
            $table->string('original_name')->nullable()->after('path');
        });

        DB::table('inventory_media')
            ->orderBy('inventory_item_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'inventory_item_id'])
            ->groupBy('inventory_item_id')
            ->each(function ($files): void {
                $display = $files->first();
                if ($display) {
                    DB::table('inventory_media')->where('id', $display->id)->update(['role' => 'display', 'sort_order' => 0]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('inventory_media', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'original_name']);
        });
    }
};
