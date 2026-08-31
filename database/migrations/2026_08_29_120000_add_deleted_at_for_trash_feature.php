<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds soft-delete support to the 5 modules getting a Trash/Recycle Bin:
     * GenericName ("INV Item" - General Item), Product ("INV Item" - Products),
     * SalesOrder, DeliveryReceipt, Invoice. Delete on these now sets deleted_at
     * instead of removing the row, and a restore() brings it back.
     */
    public function up(): void
    {
        Schema::table('generic_names', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('delivery_receipts', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('generic_names', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('delivery_receipts', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
