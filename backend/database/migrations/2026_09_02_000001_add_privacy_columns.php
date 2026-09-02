<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('anonymized_at')->nullable()->after('notes');
        });

        // The retention sweep clears the slip image once the record is old enough.
        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->string('slip_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('anonymized_at');
        });
    }
};
