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
        // A Purchase Order now orders a Generic Item — the specific brand
        // isn't decided until Goods Receipt time (matching the existing
        // Brand-field flow). product_id was already nullable (confirmed via
        // the live schema — a prior migration already relaxed it), so
        // nothing further needed there; only generic_name_id is new here.
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignId('generic_name_id')->nullable()->after('purchase_order_id')
                ->constrained('generic_names')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('generic_name_id');
        });
    }
};
