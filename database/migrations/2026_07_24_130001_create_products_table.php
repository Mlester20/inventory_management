<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('generic_name_id')->nullable()->constrained('generic_names')->nullOnDelete();
            $table->string('brand_name')->nullable();
            $table->string('item_name');
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('barcode')->nullable();
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->decimal('unit_price_percent', 5, 2)->nullable();
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('wholesale_percent', 5, 2)->nullable();
            $table->decimal('wholesale_price', 10, 2)->nullable();
            $table->decimal('price_1_percent', 5, 2)->nullable();
            $table->decimal('price_1', 10, 2)->nullable();
            $table->decimal('price_2_percent', 5, 2)->nullable();
            $table->decimal('price_2', 10, 2)->nullable();
            $table->decimal('price_3_percent', 5, 2)->nullable();
            $table->decimal('price_3', 10, 2)->nullable();
            $table->string('fda_reg_no')->nullable();
            $table->date('fda_reg_exp')->nullable();
            $table->string('custom_field_1')->nullable();
            $table->string('custom_field_2')->nullable();
            $table->string('custom_field_3')->nullable();
            $table->string('custom_field_4')->nullable();
            $table->string('location')->nullable();
            $table->integer('low_stock_threshold')->default(5);
            $table->string('image')->nullable();
            $table->foreignId('tax_id')->nullable()->constrained('taxes')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
