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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name');
            $table->string('po_no')->nullable();
            $table->string('osca_no')->nullable();
            $table->string('sales_no')->unique();
            $table->decimal('vat_sales', 12, 2)->default(0);
            $table->decimal('vatex_sales', 12, 2)->default(0);
            $table->decimal('zero_sales', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('total_sales', 12, 2)->default(0);
            $table->decimal('less_vat', 12, 2)->default(0);
            $table->decimal('amount_net', 12, 2)->default(0);
            $table->decimal('less_sc', 12, 2)->default(0);
            $table->decimal('less_wt', 12, 2)->default(0);
            $table->decimal('amount_due', 12, 2)->default(0);
            $table->decimal('add_vat', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
