<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per SAIMS-REV-2.0B.pdf item 10: a document-level free-text Notes
     * field on Sales Order, plus a per-line free-text field (e.g. for
     * technical specifications) on each Sales Order line item.
     */
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('po_no');
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->text('remarks')->nullable()->after('advance_order_qty');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('notes');
        });

        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropColumn('remarks');
        });
    }
};
