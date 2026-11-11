<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('qty');
            $table->timestamps();

            $table->unique(
                ['purchase_invoice_id', 'goods_receipt_item_id'],
                'pi_items_invoice_gr_line_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_items');
    }
};
