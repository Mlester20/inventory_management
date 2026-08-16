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
        // A draft can be saved before the user has picked From/To locations
        // yet — only a posted (finalized) transfer requires both, enforced
        // by application-level validation, not the schema.
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->foreignId('from_location_id')->nullable()->change();
            $table->foreignId('to_location_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->foreignId('from_location_id')->nullable(false)->change();
            $table->foreignId('to_location_id')->nullable(false)->change();
        });
    }
};
