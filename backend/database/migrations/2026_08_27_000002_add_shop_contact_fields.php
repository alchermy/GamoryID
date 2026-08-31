<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('facebook_url', 500)->nullable()->after('description');
            $table->string('line_url', 500)->nullable()->after('facebook_url');
            $table->string('phone', 32)->nullable()->after('line_url');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['description', 'facebook_url', 'line_url', 'phone']);
        });
    }
};
