<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * System-generated note shown on a Delivery Receipt when its linked
     * Sales Order gets cancelled/voided — separate from the user-authored
     * `description` field so the two never overwrite each other.
     */
    public function up(): void
    {
        Schema::table('delivery_receipts', function (Blueprint $table) {
            $table->text('cancellation_note')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_receipts', function (Blueprint $table) {
            $table->dropColumn('cancellation_note');
        });
    }
};
