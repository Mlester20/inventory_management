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
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('generic_name_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('brand_name')->nullable()->after('generic_name_id');
            $table->decimal('unit_cost', 10, 2)->nullable()->after('unit_price');
            $table->decimal('wholesale_percent', 5, 2)->nullable()->after('unit_cost');
            $table->decimal('wholesale_price', 10, 2)->nullable()->after('wholesale_percent');
            $table->decimal('price_1_percent', 5, 2)->nullable()->after('wholesale_price');
            $table->decimal('price_1', 10, 2)->nullable()->after('price_1_percent');
            $table->decimal('price_2_percent', 5, 2)->nullable()->after('price_1');
            $table->decimal('price_2', 10, 2)->nullable()->after('price_2_percent');
            $table->decimal('price_3_percent', 5, 2)->nullable()->after('price_2');
            $table->decimal('price_3', 10, 2)->nullable()->after('price_3_percent');
            $table->string('location')->nullable()->after('price_3');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('generic_name_id');
            $table->dropColumn([
                'brand_name', 'unit_cost',
                'wholesale_percent', 'wholesale_price',
                'price_1_percent', 'price_1',
                'price_2_percent', 'price_2',
                'price_3_percent', 'price_3',
                'location',
            ]);
        });
    }
};
