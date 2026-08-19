<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A snapshot of the actor's role at the time of the action — deliberately
     * not derived by joining to the live users.role at display time, since a
     * role could change (or the account could be deleted) later and the
     * audit trail should still show what privilege level performed the
     * action, not what the account's role happens to be today.
     */
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('role')->nullable()->after('user_id');
        });

        // Backfill existing rows from each user's current role — safe
        // because this app has never supported editing a user's role after
        // creation, so "current role" and "role at the time of any past
        // action" are the same thing for every existing log row. Looping
        // per-user (rather than a single UPDATE...JOIN) keeps this portable
        // across MySQL (production) and SQLite (the test suite).
        DB::table('users')->select('id', 'role')->orderBy('id')->each(function ($user) {
            DB::table('activity_logs')->where('user_id', $user->id)->update(['role' => $user->role]);
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
