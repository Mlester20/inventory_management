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
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('product_batch_id')->constrained()->nullOnDelete();
        });

        // Every pre-existing movement (Goods Receipt, Delivery Receipt,
        // Inventory Adjustment, POS Purchase) happened before locations
        // existed, when there was only one undifferentiated pool — treat
        // that pool as the Warehouse for historical ledger purposes.
        $warehouseId = DB::table('locations')->where('name', 'Warehouse')->value('id');
        DB::table('stock_movements')->whereNull('location_id')->update(['location_id' => $warehouseId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
        });
    }
};
