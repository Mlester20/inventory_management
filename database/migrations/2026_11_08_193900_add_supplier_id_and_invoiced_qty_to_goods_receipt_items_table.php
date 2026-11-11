<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('purchase_order_item_id')
                ->constrained('suppliers')
                ->nullOnDelete();
            $table->integer('invoiced_qty')->default(0)->after('qty');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn('invoiced_qty');
        });
    }
};
