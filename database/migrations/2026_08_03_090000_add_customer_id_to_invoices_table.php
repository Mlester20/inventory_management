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
        Schema::table('invoices', function (Blueprint $table) {
            // Nullable: invoices.customer_name is free text and won't always
            // match an existing Customer (true walk-ins, or a typo'd name at
            // the time). nullOnDelete so deleting a Customer never silently
            // deletes their billing history — the invoice just becomes
            // unlinked, same as an unmatched-backfill row.
            $table->foreignId('customer_id')->nullable()->after('customer_name')
                ->constrained('customers')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }
};
