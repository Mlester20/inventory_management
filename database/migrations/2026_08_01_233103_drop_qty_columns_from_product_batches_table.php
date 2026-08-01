<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stock now lives entirely in location_stocks (see the data migration
     * that ran just before this one) — qty/reserved_qty here would be a
     * second, easily-drifting source of truth for exactly the numbers this
     * whole feature exists to make location-aware, so they're dropped
     * rather than kept as a denormalized cache.
     */
    public function up(): void
    {
        Schema::table('product_batches', function (Blueprint $table) {
            $table->dropColumn(['qty', 'reserved_qty']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_batches', function (Blueprint $table) {
            $table->integer('qty')->default(0);
            $table->integer('reserved_qty')->default(0);
        });
    }
};
