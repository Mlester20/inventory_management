<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_adjustment_lines', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('product_batch_id')->constrained()->nullOnDelete();
        });

        $warehouseId = DB::table('locations')->where('name', 'Warehouse')->value('id');
        DB::table('inventory_adjustment_lines')->whereNull('location_id')->update(['location_id' => $warehouseId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_adjustment_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
        });
    }
};
