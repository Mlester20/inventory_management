<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Widened from VARCHAR(255) to TEXT so a full multi-line technical
     * specifications block (matching Sales Order's remarks, which is
     * already TEXT) fits — plain Schema::table()->change() needs
     * doctrine/dbal, which isn't installed, so this uses raw SQL instead.
     * MySQL-only: SQLite (used by the test suite) has no ALTER COLUMN TYPE
     * statement, but its type affinity already stores strings of any length
     * regardless of the declared column type, so skipping it there is a
     * true no-op, not a gap in test coverage.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE delivery_receipt_items MODIFY remarks TEXT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE delivery_receipt_items MODIFY remarks VARCHAR(255) NULL');
        }
    }
};
