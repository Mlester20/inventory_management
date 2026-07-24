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
        // MariaDB 10.4 on this server doesn't support native RENAME COLUMN
        // (added in 10.5.2) and doctrine/dbal isn't installed, so rename via
        // add-new -> copy -> drop-old instead of Schema::renameColumn().
        Schema::table('customers', function (Blueprint $table) {
            $table->string('contact_number')->nullable()->after('phone');
            $table->text('delivery_address')->nullable()->after('address');
            $table->string('price_level')->default('retail')->after('customer_type');
            $table->string('vat_type')->default('VAT')->after('price_level');
        });

        DB::statement('UPDATE customers SET contact_number = phone, delivery_address = address');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['phone', 'address']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('contact_number');
            $table->text('address')->nullable()->after('delivery_address');
        });

        DB::statement('UPDATE customers SET phone = contact_number, address = delivery_address');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['contact_number', 'delivery_address', 'price_level', 'vat_type']);
        });
    }
};
