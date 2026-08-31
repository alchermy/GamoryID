<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->boolean('has_warranty')->default(false)->after('profit');
            $table->date('warranty_ends_at')->nullable()->after('has_warranty');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['has_warranty', 'warranty_ends_at']);
        });
    }
};
