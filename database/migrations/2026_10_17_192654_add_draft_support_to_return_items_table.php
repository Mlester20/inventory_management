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
        Schema::table('return_items', function (Blueprint $table) {
            // Same is_draft + separate status pattern as PurchaseOrder: status
            // (pending/approved/rejected) only has meaning once a Return Item
            // is actually posted, so it stays untouched by the draft itself.
            $table->boolean('is_draft')->default(false)->after('status');
            $table->integer('quantity')->nullable()->change();
            $table->date('return_date')->nullable()->change();
            $table->string('reason')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('return_items', function (Blueprint $table) {
            $table->dropColumn('is_draft');
            $table->integer('quantity')->nullable(false)->change();
            $table->date('return_date')->nullable(false)->change();
            $table->string('reason')->nullable(false)->change();
        });
    }
};
