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
        // A draft can be saved before the user has picked an adjustment
        // type yet — only a posted (finalized) adjustment requires one,
        // enforced by application-level validation, not the schema.
        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->string('adjustment_type')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_adjustments', function (Blueprint $table) {
            $table->string('adjustment_type')->nullable(false)->change();
        });
    }
};
