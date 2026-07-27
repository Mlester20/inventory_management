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
        Schema::table('purchases', function (Blueprint $table) {
            // Groups every line belonging to the same checkout — a customer
            // tenders payment once for the whole cart, not per line, so
            // amount_tendered/change_amount are the same value repeated
            // across all lines of one transaction_id.
            $table->string('transaction_id')->nullable()->after('user_id');
            $table->decimal('amount_tendered', 10, 2)->nullable()->after('total_price');
            $table->decimal('change_amount', 10, 2)->nullable()->after('amount_tendered');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['transaction_id', 'amount_tendered', 'change_amount']);
        });
    }
};
