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
        DB::table('invoices as i')
            ->join('customers as c', 'c.customer_name', '=', 'i.customer_name')
            ->update(['i.customer_id' => DB::raw('c.id')]);

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
