<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Goods or Services for every Generic Item (Sir's mockup: the Generic Item
 * form carries a Product Type). It fixes the withholding tax rate the item
 * attracts (1% Goods, 2% Services — see GenericName::WITHHOLDING_RATES), so
 * the encoder no longer picks it per invoice line. Existing items are Goods.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generic_names', function (Blueprint $table) {
            $table->string('product_type', 20)->default('goods')->after('vat_type');
        });
    }

    public function down(): void
    {
        Schema::table('generic_names', function (Blueprint $table) {
            $table->dropColumn('product_type');
        });
    }
};
