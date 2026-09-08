<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_jobs', function (Blueprint $table) {
            // Maps a shop's own status labels (raw cell values) to system
            // statuses, e.g. {"ออกแล้ว":"sold","ยังไม่ขาย":"available"}.
            // Null when the import doesn't map a status column.
            $table->json('status_map')->nullable()->after('mapping');
        });
    }

    public function down(): void
    {
        Schema::table('import_jobs', function (Blueprint $table) {
            $table->dropColumn('status_map');
        });
    }
};
