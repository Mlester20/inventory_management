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
        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            // 'collection' = payment against an existing/invoiced balance;
            // 'advance' = prepayment for a future order not yet invoiced.
            // Declared explicitly by whoever records the payment — not
            // inferred from the numbers, since invoices aren't reliably
            // linked to a specific customer_id in this app (customer_name
            // is free text), so precise per-invoice netting isn't possible.
            $table->string('type')->default('collection');
            $table->decimal('amount', 10, 2);
            $table->date('payment_date');
            $table->string('payment_method')->nullable();
            $table->string('remarks')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_payments');
    }
};
