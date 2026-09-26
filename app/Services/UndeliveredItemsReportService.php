<?php

namespace App\Services;

use App\Models\SalesOrderItem;
use Illuminate\Support\Collection;

/**
 * Undelivered items per customer: what is still owed on the customers' Sales Orders
 * (ordered minus delivered), consolidated per customer and per item across all of
 * their Sales Orders. Quantity only (Sir). Drafts, cancelled and archived Sales
 * Orders are left out — they are not open commitments.
 */
class UndeliveredItemsReportService
{
    /**
     * @return Collection<int,array> one entry per customer:
     *   customer_id, customer_name, so_count, item_count, total_balance,
     *   items => [ generic_label, ordered, delivered, balance, orders => [ so_id, so_no, po_no, order_date, product, ordered, delivered, balance ] ]
     */
    public function build(?int $customerId = null, ?string $from = null, ?string $to = null): Collection
    {
        $lines = SalesOrderItem::query()
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
            ->join('customers', 'customers.id', '=', 'sales_orders.customer_id')
            ->leftJoin('generic_names', 'generic_names.id', '=', 'sales_order_items.generic_name_id')
            ->leftJoin('products', 'products.id', '=', 'sales_order_items.product_id')
            ->where('sales_orders.is_draft', false)
            ->where('sales_orders.status', '!=', 'cancelled')
            ->whereNull('sales_orders.archived_at')
            ->whereNull('sales_orders.deleted_at')
            ->whereColumn('sales_order_items.qty', '>', 'sales_order_items.delivered_qty')
            ->when($customerId, fn ($q) => $q->where('sales_orders.customer_id', $customerId))
            ->when($from, fn ($q) => $q->whereDate('sales_orders.order_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('sales_orders.order_date', '<=', $to))
            ->orderBy('customers.customer_name')
            ->orderBy('generic_names.generic_name')
            ->orderBy('generic_names.unit')
            ->orderBy('sales_orders.order_date')
            ->orderBy('sales_orders.id')
            ->get([
                'customers.id as customer_id',
                'customers.customer_name',
                'sales_orders.id as so_id',
                'sales_orders.so_no',
                'sales_orders.po_no',
                'sales_orders.order_date',
                'sales_order_items.generic_name_id',
                'generic_names.generic_name',
                'generic_names.unit',
                'products.item_name as product_name',
                'sales_order_items.qty',
                'sales_order_items.delivered_qty',
            ]);

        return $lines->groupBy('customer_id')->map(function (Collection $customerLines) {
            $items = $customerLines->groupBy('generic_name_id')->map(function (Collection $itemLines) {
                $first = $itemLines->first();
                $ordered = (int) $itemLines->sum('qty');
                $delivered = (int) $itemLines->sum('delivered_qty');

                return [
                    'generic_label' => trim(($first->generic_name ?? 'Unknown item') . ($first->unit ? " ({$first->unit})" : '')),
                    'ordered' => $ordered,
                    'delivered' => $delivered,
                    'balance' => $ordered - $delivered,
                    'orders' => $itemLines->map(fn ($line) => [
                        'so_id' => $line->so_id,
                        'so_no' => $line->so_no,
                        'po_no' => $line->po_no,
                        'order_date' => $line->order_date,
                        'product' => $line->product_name,
                        'ordered' => (int) $line->qty,
                        'delivered' => (int) $line->delivered_qty,
                        'balance' => (int) $line->qty - (int) $line->delivered_qty,
                    ])->values()->all(),
                ];
            })->values();

            $first = $customerLines->first();

            return [
                'customer_id' => $first->customer_id,
                'customer_name' => $first->customer_name,
                'so_count' => $customerLines->pluck('so_id')->unique()->count(),
                'item_count' => $items->count(),
                'total_balance' => (int) $items->sum('balance'),
                'items' => $items->all(),
            ];
        })->values();
    }
}
