<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional per-line Brand (Product) reference on Sales Order / Sales
     * Quote items — purely for visibility and price-suggestion at encoding
     * time. Left null = "not yet identified" (unchanged default behavior);
     * this never drives inventory/DR logic, which still identifies the
     * actual batch/brand to deliver on its own at Delivery Receipt time.
     */
    public function up(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('generic_name_id')->constrained('products')->nullOnDelete();
        });

        Schema::table('sales_quote_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('generic_name_id')->constrained('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });

        Schema::table('sales_quote_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });
    }
};
