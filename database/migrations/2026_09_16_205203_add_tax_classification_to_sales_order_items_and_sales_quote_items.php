<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Manual, per-line tax flag the encoder can optionally set (VATable /
     * VAT-Exempt / Zero-Rated) — SalesOrderItem/SalesQuoteItem are still
     * generic-level (no product_id), so there's no Product.tax_id to derive
     * this from automatically the way DeliveryReceiptService::
     * createInvoiceFromLines() does for a real Invoice line. Null means
     * "not yet identified", matching today's blank-VAT-box behavior.
     */
    public function up(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->string('tax_classification')->nullable()->after('price');
        });

        Schema::table('sales_quote_items', function (Blueprint $table) {
            $table->string('tax_classification')->nullable()->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropColumn('tax_classification');
        });

        Schema::table('sales_quote_items', function (Blueprint $table) {
            $table->dropColumn('tax_classification');
        });
    }
};
