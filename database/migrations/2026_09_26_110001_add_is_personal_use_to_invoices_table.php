<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Personal use" on a Sales Invoice: medicine bought for personal use is not covered by
 * withholding tax (Sir), so the encoder can tick it on any invoice, whoever the customer is.
 * A ticked invoice never carries a Withholding Tax. Existing invoices are not personal use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('is_personal_use')->default(false)->after('less_wt');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('is_personal_use');
        });
    }
};
