<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Advance Qty counts as already delivered, so an open Sales Order whose every line is now
     * covered (delivered + advance >= ordered) is Completed. Orders that still have something
     * to deliver keep their status.
     */
    public function up(): void
    {
        DB::table('sales_orders')
            ->where('is_draft', false)
            ->whereIn('status', ['open', 'partially_delivered'])
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('sales_order_items')
                ->whereColumn('sales_order_items.sales_order_id', 'sales_orders.id'))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('sales_order_items')
                ->whereColumn('sales_order_items.sales_order_id', 'sales_orders.id')
                ->whereRaw('(sales_order_items.qty IS NULL OR sales_order_items.qty > sales_order_items.delivered_qty + sales_order_items.advance_order_qty)'))
            ->update(['status' => 'completed']);
    }

    public function down(): void
    {
        // Status is derived data; there is no way to tell which orders this changed.
    }
};
