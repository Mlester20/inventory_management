<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Product Return is the printable document behind a return from a Delivery Receipt: one number
     * (PR-2026-00001) over the lines that were actually returned. Each line stays a Return Item, so
     * approval, stock and refunds keep working exactly as they do for a single-item return.
     */
    public function up(): void
    {
        Schema::create('product_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no')->unique();
            $table->foreignId('delivery_receipt_id')->nullable()->constrained('delivery_receipts')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->date('return_date');
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('return_items', function (Blueprint $table) {
            $table->foreignId('product_return_id')->nullable()->after('delivery_receipt_id')
                ->constrained('product_returns')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('return_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_return_id');
        });

        Schema::dropIfExists('product_returns');
    }
};
