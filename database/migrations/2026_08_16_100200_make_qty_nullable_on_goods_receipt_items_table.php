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
        // A draft line can be left with no qty/cost typed yet — only a
        // posted (finalized) receipt requires them, enforced by
        // application-level validation, not the schema.
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->integer('qty')->nullable()->change();
            $table->decimal('unit_cost', 12, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->integer('qty')->nullable(false)->change();
            $table->decimal('unit_cost', 12, 2)->nullable(false)->change();
        });
    }
};
