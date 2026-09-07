<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Best-effort exact-name match only, since invoices.customer_name is free
     * text with no guarantee of matching a real Customer record. Invoices
     * that don't match stay customer_id = null and are simply excluded from
     * the customer_id-based aggregates going forward — expected, not a bug.
     */
    public function up(): void
    {
        // A plain join-update (rather than this per-customer loop) is
        // MySQL-only syntax — SQLite (used by the automated test suite)
        // has no UPDATE...JOIN, and Laravel's emulation of it here produces
        // invalid SQL. This loop reaches the identical result on both.
        DB::table('customers')->select('id', 'customer_name')->orderBy('id')->chunk(200, function ($customers) {
            foreach ($customers as $customer) {
                DB::table('invoices')
                    ->where('customer_name', $customer->customer_name)
                    ->whereNull('customer_id')
                    ->update(['customer_id' => $customer->id]);
            }
        });

        $unmatched = DB::table('invoices')->whereNull('customer_id')->get(['id', 'sales_no', 'customer_name']);

        if ($unmatched->isNotEmpty()) {
            Log::warning("Invoice customer_id backfill: {$unmatched->count()} invoice(s) had no exact customer_name match and remain unlinked.", [
                'invoices' => $unmatched->map(fn ($r) => [
                    'id' => $r->id,
                    'sales_no' => $r->sales_no,
                    'customer_name' => $r->customer_name,
                ])->toArray(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('invoices')->update(['customer_id' => null]);
    }
};
