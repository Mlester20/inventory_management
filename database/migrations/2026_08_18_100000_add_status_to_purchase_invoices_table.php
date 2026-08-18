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
        // No pre-existing lifecycle status on this table (unlike Purchase
        // Order/Delivery Receipt) — safe to use `status` directly, same as
        // Inventory Adjustment/Stock Transfer/Goods Receipt. Defaulting to
        // 'posted' means every existing row is automatically correct — no
        // backfill needed.
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->string('status')->default('posted')->after('prepared_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
