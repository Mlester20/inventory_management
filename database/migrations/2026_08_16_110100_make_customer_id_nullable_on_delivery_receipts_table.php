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
        // A draft can be saved before the user has picked a transaction
        // type, customer, or Sales Order yet — only a posted (finalized)
        // receipt requires them, enforced by application-level validation,
        // not the schema.
        Schema::table('delivery_receipts', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->change();
            $table->string('transaction_type')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_receipts', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable(false)->change();
            $table->string('transaction_type')->nullable(false)->change();
        });
    }
};
