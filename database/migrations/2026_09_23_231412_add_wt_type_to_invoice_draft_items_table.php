<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A draft invoice line remembers whether it was marked Goods or Services (for
 * the withholding suggestion), so resuming the draft restores the choice. It
 * is only an input to the suggestion — a posted invoice stores just the final
 * Withholding Tax amount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_draft_items', function (Blueprint $table) {
            $table->string('wt_type', 10)->nullable()->after('tax_override');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_draft_items', function (Blueprint $table) {
            $table->dropColumn('wt_type');
        });
    }
};
