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
        Schema::table('inventory_adjustments', function (Blueprint $table) {
            // Defaulting to 'posted' means every existing row (all of which
            // already moved stock) is automatically correct — no backfill step.
            $table->string('status')->default('posted')->after('reverses_adjustment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
