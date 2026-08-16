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
        // A draft line can be left with no batch/qty picked yet — only a
        // posted (finalized) transfer requires both, enforced by
        // application-level validation, not the schema.
        Schema::table('stock_transfer_lines', function (Blueprint $table) {
            $table->foreignId('product_batch_id')->nullable()->change();
            $table->integer('qty')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_transfer_lines', function (Blueprint $table) {
            $table->foreignId('product_batch_id')->nullable(false)->change();
            $table->integer('qty')->nullable(false)->change();
        });
    }
};
