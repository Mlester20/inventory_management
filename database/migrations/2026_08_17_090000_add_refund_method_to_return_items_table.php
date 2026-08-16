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
        // Set only at approval time (ReturnItemService::approve()) — a
        // pending return hasn't been disposed of yet. 'credit' issues an
        // advance CustomerPayment (counted in the customer's Available
        // Credit); 'cash' hands the money back immediately and is not
        // credit — no CustomerPayment is created for it, this pair of
        // columns is its only record.
        Schema::table('return_items', function (Blueprint $table) {
            $table->string('refund_method')->nullable()->after('status');
            $table->decimal('refund_amount', 12, 2)->nullable()->after('refund_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('return_items', function (Blueprint $table) {
            $table->dropColumn(['refund_method', 'refund_amount']);
        });
    }
};
