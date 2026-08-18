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
        // A draft line can be left with no item/qty/price typed yet — only
        // a posted (finalized) order requires them, enforced by
        // application-level validation, not the schema.
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->foreignId('generic_name_id')->nullable()->change();
            $table->integer('qty')->nullable()->change();
            $table->decimal('price', 12, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->foreignId('generic_name_id')->nullable(false)->change();
            $table->integer('qty')->nullable(false)->change();
            $table->decimal('price', 12, 2)->nullable(false)->change();
        });
    }
};
