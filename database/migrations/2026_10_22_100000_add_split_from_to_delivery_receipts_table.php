<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_receipts', function (Blueprint $table) {
            $table->foreignId('split_from_delivery_receipt_id')->nullable()->after('sales_order_id')
                ->constrained('delivery_receipts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('split_from_delivery_receipt_id');
        });
    }
};
