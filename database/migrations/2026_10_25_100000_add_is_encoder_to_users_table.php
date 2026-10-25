<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An Encoder is a name that can be picked under "Prepared By" without being a login: a user row
     * that can never sign in. Keeping them in `users` means every existing prepared_by foreign key,
     * document view and report works unchanged.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_encoder')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_encoder');
        });
    }
};
