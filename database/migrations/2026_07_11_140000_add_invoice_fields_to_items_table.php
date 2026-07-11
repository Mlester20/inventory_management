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
            $table->string('serial_number')->nullable()->after('item_name');
            $table->string('image')->nullable()->after('low_stock_threshold');
            $table->foreignId('tax_id')->nullable()->after('image')
                ->constrained('taxes')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_id');
            $table->dropColumn(['serial_number', 'image']);
        });
    }
};
