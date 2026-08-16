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
        // A draft line records which Product was picked before a batch has
        // been resolved (Direct Receipt types a batch_no that may not exist
        // yet) — product_batch_id alone can't represent that mid-encoding
        // state. Posted items are also given it for a direct product
        // reference, but it's product_batch_id (resolved at post time) that
        // remains the source of truth once a receipt is finalized.
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('goods_receipt_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
        });
    }
};
