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
        Schema::table('customer_payments', function (Blueprint $table) {
            // Only meaningful for type='advance' rows — tracks how much of
            // this credit has already been consumed against an invoice, so a
            // single advance payment can be partially applied across more
            // than one future invoice. Collection payments never use this.
            $table->decimal('consumed_amount', 10, 2)->default(0)->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->dropColumn('consumed_amount');
        });
    }
};
