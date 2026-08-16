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
        // Set only at approval time (ReturnItemService::approve()). 'sellable'
        // restocks into POS as before; 'write_off' restocks into POS then
        // immediately disposes the same qty via StockDisposalService — net
        // zero change to sellable stock, but both movements land in the
        // shared Product History ledger, and stock_disposal_id links back to
        // that disposal record for traceability.
        Schema::table('return_items', function (Blueprint $table) {
            $table->string('stock_disposition')->nullable()->after('refund_amount');
            $table->foreignId('stock_disposal_id')->nullable()->after('stock_disposition')
                ->constrained('stock_disposals')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('return_items', function (Blueprint $table) {
            $table->dropForeign(['stock_disposal_id']);
            $table->dropColumn(['stock_disposition', 'stock_disposal_id']);
        });
    }
};
