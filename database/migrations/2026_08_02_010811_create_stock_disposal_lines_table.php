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
        Schema::create('stock_disposal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_disposal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_batch_id')->constrained()->restrictOnDelete();
            // A batch split across multiple locations disposes as one line
            // per location it's actually sitting in — kept explicit (not
            // auto-split inside the service) so this reuses the exact same
            // single-location StockService::deduct() every other document
            // already uses, no new stock-deduction code path.
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->integer('qty');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_disposal_lines');
    }
};
