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
        // A draft can be saved before the user has picked a supplier or
        // typed the invoice_no/amount yet — only a posted (finalized)
        // record requires them, enforced by application-level validation,
        // not the schema. invoice_date stays NOT NULL — the service
        // defaults it to now() when absent, same as every other module's
        // draft support.
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->change();
            $table->string('invoice_no')->nullable()->change();
            $table->decimal('amount', 12, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable(false)->change();
            $table->string('invoice_no')->nullable(false)->change();
            $table->decimal('amount', 12, 2)->nullable(false)->change();
        });
    }
};
