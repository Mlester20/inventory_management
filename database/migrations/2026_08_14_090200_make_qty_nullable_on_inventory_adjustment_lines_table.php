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
        // A draft line can be left with no quantity yet (item picked, qty
        // not typed before the encoder was interrupted) — only a posted
        // (finalized) adjustment requires one, enforced by validation, not
        // the schema.
        Schema::table('inventory_adjustment_lines', function (Blueprint $table) {
            $table->integer('qty')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_adjustment_lines', function (Blueprint $table) {
            $table->integer('qty')->nullable(false)->change();
        });
    }
};
