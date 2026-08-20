<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every "document" table's date/status columns get filtered or sorted
     * on nearly every Report/Dashboard page load, but only their foreign
     * keys were ever indexed — these were full table scans. Pure storage-
     * layer change: no query logic changes, results are identical, just
     * faster once these tables have real volume.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->index('purchase_date');
        });

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->index('receipt_date');
        });

        Schema::table('delivery_receipts', function (Blueprint $table) {
            $table->index('receipt_date');
            $table->index('status');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->index('expense_date');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex(['purchase_date']);
        });

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropIndex(['receipt_date']);
        });

        Schema::table('delivery_receipts', function (Blueprint $table) {
            $table->dropIndex(['receipt_date']);
            $table->dropIndex(['status']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['expense_date']);
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
    }
};
