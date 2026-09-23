<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Save Draft for Sales Invoice, same idea as Sales Order/Sales Quote/Stock
 * Transfer drafts: a half-filled invoice can be saved and resumed later
 * without deducting any stock or counting toward any total. Draft lines live
 * in invoice_draft_items (never in `sales`, which every report and the COGS/
 * dashboard queries join on), and the Invoice model hides drafts behind a
 * global scope, so nothing that sums invoices can ever see one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('is_draft')->default(false)->after('sales_no');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('is_draft');
        });
    }
};
