<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 2 of the indexing audit — the Medium/Low priority columns held
     * back from the first pass (see 2026_08_20_090000). Same reasoning:
     * pure storage-layer change, no query logic touched.
     */
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('delivery_receipts', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('return_items', function (Blueprint $table) {
            $table->index('status');
            $table->index('return_date');
        });

        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('barcode');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('delivery_receipts', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('return_items', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['return_date']);
        });

        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['barcode']);
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['role']);
        });
    }
};
