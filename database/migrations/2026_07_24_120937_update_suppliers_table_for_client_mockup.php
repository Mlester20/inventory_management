<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Same rename constraint as the customers migration — see its comment.
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('contact_number')->nullable()->after('phone');
            $table->text('delivery_address')->nullable()->after('address');
            $table->string('vat_type')->default('VAT')->after('contact_person');
        });

        DB::statement('UPDATE suppliers SET contact_number = phone, delivery_address = address');

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['phone', 'address']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('contact_number');
            $table->text('address')->nullable()->after('delivery_address');
        });

        DB::statement('UPDATE suppliers SET phone = contact_number, address = delivery_address');

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['contact_number', 'delivery_address', 'vat_type']);
        });
    }
};
