<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer-level withholding tax rates (percent). A customer withholds a
 * different % on goods than on services, and it differs per customer, so both
 * live on the customer; blank = that kind of sale has no withholding. Used to
 * pre-fill the (still editable) Withholding Tax on an Invoice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('wt_rate_goods', 5, 2)->nullable()->after('vat_type');
            $table->decimal('wt_rate_services', 5, 2)->nullable()->after('wt_rate_goods');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['wt_rate_goods', 'wt_rate_services']);
        });
    }
};
