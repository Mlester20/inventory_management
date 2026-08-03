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
            // Nullable: historical return_items rows have no customer
            // association at all and can't be backfilled. Going forward, set
            // when an admin attaches a customer to a return at approval time
            // (see ReturnItemController::approve()) so it can generate a
            // credit on that customer's account.
            $table->foreignId('customer_id')->nullable()->after('user_id')
                ->constrained('customers')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('return_items', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }
};
