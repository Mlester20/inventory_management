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
        // A draft can be saved before the user has picked a supplier or a
        // Purchase Order yet — only a posted (finalized) receipt requires
        // one, enforced by application-level validation, not the schema.
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable(false)->change();
        });
    }
};
