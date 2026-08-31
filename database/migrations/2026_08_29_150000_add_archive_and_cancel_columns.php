<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extends the Archive (listing-declutter) + Cancel/Void (status-only,
     * does NOT reverse stock) pattern from Sales Order to Delivery Receipt,
     * Invoice, GenericName, and Product. Delivery Receipt already has a
     * plain string `status` column, so it reuses that for 'cancelled' like
     * Sales Order does. Invoice has no status column at all, so it gets a
     * dedicated `cancelled_at` timestamp instead of inventing a new status
     * field. GenericName/Product are master data (no document lifecycle to
     * void), so they only get `archived_at`.
     */
    public function up(): void
    {
        Schema::table('delivery_receipts', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('status');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('amount_due');
            $table->timestamp('cancelled_at')->nullable()->after('archived_at');
        });

        Schema::table('generic_names', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('vat_type');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('low_stock_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_receipts', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['archived_at', 'cancelled_at']);
        });

        Schema::table('generic_names', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
