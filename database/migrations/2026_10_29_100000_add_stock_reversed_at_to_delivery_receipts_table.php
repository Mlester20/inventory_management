<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** When a cancelled Delivery Receipt's goods were put back in the Warehouse (null = not yet / never needed). */
    public function up(): void
    {
        Schema::table('delivery_receipts', function (Blueprint $table) {
            $table->timestamp('stock_reversed_at')->nullable()->after('cancellation_note');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_receipts', function (Blueprint $table) {
            $table->dropColumn('stock_reversed_at');
        });
    }
};
