<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A separate flag from `status` — that column already tracks the
        // delivery lifecycle (for_delivery/delivered) and must keep meaning
        // only that. Defaulting to false means every existing row (all of
        // which already moved stock) is automatically correct — no backfill.
        Schema::table('delivery_receipts', function (Blueprint $table) {
            $table->boolean('is_draft')->default(false)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_receipts', function (Blueprint $table) {
            $table->dropColumn('is_draft');
        });
    }
};
