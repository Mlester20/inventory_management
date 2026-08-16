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
        // A draft line can be left with no qty typed yet — only a posted
        // (finalized) receipt requires one, enforced by application-level
        // validation, not the schema. product_batch_id is already nullable
        // from the earlier item_id -> product_batch_id migration.
        Schema::table('delivery_receipt_items', function (Blueprint $table) {
            $table->integer('qty')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_receipt_items', function (Blueprint $table) {
            $table->integer('qty')->nullable(false)->change();
        });
    }
};
