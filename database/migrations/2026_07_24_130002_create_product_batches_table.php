<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('batch_no')->nullable();
            $table->date('expiration_date')->nullable();
            $table->integer('qty')->default(0);
            $table->integer('reserved_qty')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'batch_no']);
            $table->index('expiration_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};
