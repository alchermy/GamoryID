<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_submissions', function (Blueprint $table) {
            // 'passed'  = SlipOK verified the slip automatically
            // 'unavailable' = the automated check could not run (SlipOK down / quota)
            // null = legacy row from before synchronous verification
            $table->string('auto_slip_check', 20)->nullable()->after('provider_reference');
        });
    }

    public function down(): void
    {
        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->dropColumn('auto_slip_check');
        });
    }
};
