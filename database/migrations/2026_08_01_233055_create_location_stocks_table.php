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
        Schema::create('location_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_batch_id')->constrained()->cascadeOnDelete();
            $table->integer('qty')->default(0);
            // Mirrors product_batches.reserved_qty's original intent
            // (per-location now) — no live code writes to this yet (there's
            // no reservation flow anywhere in the app today), kept for
            // schema parity only.
            $table->integer('reserved_qty')->default(0);
            $table->timestamps();

            $table->unique(['location_id', 'product_batch_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_stocks');
    }
};
