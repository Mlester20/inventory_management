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
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            // 'payment' = money paid against an existing/received balance;
            // 'advance' = prepayment for a future purchase order not yet
            // received. Declared explicitly by whoever records the payment.
            $table->string('type')->default('payment');
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
        Schema::dropIfExists('supplier_payments');
    }
};
