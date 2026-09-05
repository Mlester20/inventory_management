<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extends the Archive (listing-declutter, manual, reversible) pattern
     * already built for Sales Order/Delivery Receipt/Invoice/GenericName/
     * Product to Sales Quote — the one remaining transaction-document
     * module without it.
     */
    public function up(): void
    {
        Schema::table('sales_quotes', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('sales_quotes', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
