<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Data migration, not a schema change — `users.role` is a plain string
     * column with no DB-level constraint. Per Sir's confirmed decision
     * (2026-10-17): every account that was using the 'user' role was
     * actually the POS/cashier role in practice, so they all move to a new,
     * dedicated 'pos' role value. The 'user' role itself is being
     * repurposed into a new, more restricted third admin-side tier (built
     * separately) — it stays unused by any existing account until someone
     * is deliberately assigned to it going forward.
     */
    public function up(): void
    {
        DB::table('users')->where('role', 'user')->update(['role' => 'pos']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->where('role', 'pos')->update(['role' => 'user']);
    }
};
