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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->string('desc')->nullable();
            $table->integer('qty');
            $table->string('unit')->nullable();
            $table->string('batch_no')->nullable();
            $table->date('exp')->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('vat', 12, 2)->default(0);
            $table->decimal('dis', 12, 2)->default(0);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
