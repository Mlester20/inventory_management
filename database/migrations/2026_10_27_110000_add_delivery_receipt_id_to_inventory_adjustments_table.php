<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A customer return points at the Delivery Receipt the goods were returned from. */
    public function up(): void
    {
        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->foreignId('delivery_receipt_id')->nullable()->after('reverses_adjustment_id')
                ->constrained('delivery_receipts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_receipt_id');
        });
    }
};
