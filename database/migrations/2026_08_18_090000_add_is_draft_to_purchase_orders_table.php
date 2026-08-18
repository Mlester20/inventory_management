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
        // A separate flag from `status` — that column already tracks the PO
        // lifecycle (open/partially_received/completed) and must keep
        // meaning only that. Defaulting to false means every existing row
        // is automatically correct — no backfill needed.
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->boolean('is_draft')->default(false)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('is_draft');
        });
    }
};
