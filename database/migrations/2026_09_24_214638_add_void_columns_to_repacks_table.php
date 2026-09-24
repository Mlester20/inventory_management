<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Repack can be voided while it is still "complete" — every piece it
 * produced is still at the location (Sir: once some of the pieces are sold
 * it can't be voided any more; correct that with an Inventory Adjustment).
 * Voiding reverses both sides through the stock ledger; the row itself is
 * never deleted, it just becomes status "voided".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repacks', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('remarks');
            $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable()->after('voided_by');
        });
    }

    public function down(): void
    {
        Schema::table('repacks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['voided_at', 'void_reason']);
        });
    }
};
