<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additional Withholding VAT % for customers that withhold VAT on top of the
 * 1% / 2% income tax (Government accounts). Blank = the customer has none
 * (Private). When it is set the customer's VAT Type is forced to VAT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('withholding_vat_rate', 5, 2)->nullable()->after('vat_type');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('withholding_vat_rate');
        });
    }
};
