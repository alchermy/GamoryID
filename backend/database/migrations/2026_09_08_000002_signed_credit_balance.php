<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Allow shops.credit_balance to go negative so an admin can claw back
    // credits from a wrongly-approved top-up even after the shop has spent them.
    // SQLite (tests) stores INTEGER signed already — only MySQL/MariaDB needs the
    // column widened from UNSIGNED to signed.
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE shops MODIFY credit_balance BIGINT NOT NULL DEFAULT 0');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE shops MODIFY credit_balance BIGINT UNSIGNED NOT NULL DEFAULT 0');
        }
    }
};
