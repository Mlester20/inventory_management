<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_draft_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('desc')->nullable();
            $table->string('unit', 50)->nullable();
            $table->string('batch_no', 100)->nullable();
            $table->date('exp')->nullable();
            $table->unsignedInteger('qty')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('dis', 12, 2)->nullable();
            $table->string('tax_override', 10)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_draft_items');
    }
};
