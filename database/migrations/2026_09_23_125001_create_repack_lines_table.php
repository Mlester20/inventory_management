<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repack_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repack_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_batch_id')->constrained('product_batches')->restrictOnDelete();
            $table->integer('source_qty');
            $table->foreignId('destination_product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('destination_batch_id')->constrained('product_batches')->restrictOnDelete();
            $table->integer('destination_qty');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repack_lines');
    }
};
