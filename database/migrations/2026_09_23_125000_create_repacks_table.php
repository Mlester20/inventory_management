<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repack: converts stock of one product into stock of another at the same
 * location (e.g. breaking a BX into loose PC), through the same
 * deduct/restock ledger mechanism Stock Transfer uses — see
 * docs/repacking-unit-conversion-plan.md. v1 posts immediately, no draft
 * status yet (mirrors createStockTransfer(), not its draft/finalize pair) —
 * several open design questions (destination-product creation, void
 * support, wastage) are still pending confirmation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repacks', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->date('date');
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('posted');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repacks');
    }
};
