<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generic_names', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('id');
            $table->string('vat_type')->default('VAT')->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('generic_names', function (Blueprint $table) {
            $table->dropColumn(['code', 'vat_type']);
        });
    }
};
