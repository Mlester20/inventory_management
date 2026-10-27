<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A return can point at the Delivery Receipt the goods were delivered on, with or without an Invoice. */
    public function up(): void
    {
        Schema::table('return_items', function (Blueprint $table) {
            $table->foreignId('delivery_receipt_id')->nullable()->after('customer_id')
                ->constrained('delivery_receipts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('return_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_receipt_id');
        });
    }
};
