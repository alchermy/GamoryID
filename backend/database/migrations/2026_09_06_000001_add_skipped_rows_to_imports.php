<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_jobs', function (Blueprint $table) {
            $table->unsignedInteger('skipped_rows')->default(0)->after('failed_rows');
        });
        Schema::table('import_errors', function (Blueprint $table) {
            // 'error' = a row the batch cannot accept; 'duplicate' = a row
            // skipped because its username already exists (batch still imports
            // the rest).
            $table->string('kind', 16)->default('error')->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('import_jobs', function (Blueprint $table) {
            $table->dropColumn('skipped_rows');
        });
        Schema::table('import_errors', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
