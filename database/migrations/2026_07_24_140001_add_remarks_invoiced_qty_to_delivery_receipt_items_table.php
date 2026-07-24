<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_receipt_items', function (Blueprint $table) {
            $table->string('remarks')->nullable()->after('qty');
            $table->integer('invoiced_qty')->default(0)->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_receipt_items', function (Blueprint $table) {
            $table->dropColumn(['remarks', 'invoiced_qty']);
        });
    }
};
