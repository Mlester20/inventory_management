<?php

namespace App\Services;

use App\Models\Location;
use App\Models\LocationStock;
use App\Models\SalesOrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Undelivered items per customer: what is still owed on the customers' Sales Orders
 * (ordered minus delivered), consolidated per customer and per item across all of
 * their Sales Orders. Drafts, cancelled and archived Sales Orders are left out — they
 * are not open commitments. Each line also carries the SO price and the quantity on hand
 * in the Warehouse (where a Delivery Receipt takes its stock from), as on Sir's sheet.
 */
class UndeliveredItemsReportService
{
    /**
     * @return Collection<int,array> one entry per customer:
     *   customer_id, customer_name, so_count, item_count, total_balance,
     *   items => [ generic_label, ordered, delivered, balance, on_hand, orders => [ so_id, so_no, po_no, order_date, product, price, ordered, delivered, balance, on_hand ] ]
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
                'customers.delivery_address as customer_address',
                'sales_orders.id as so_id',
                'sales_orders.so_no',
                'sales_orders.po_no',
                'sales_orders.order_date',
                'sales_order_items.generic_name_id',
                'generic_names.generic_name',
                'generic_names.unit',
                'sales_order_items.product_id',
                'products.item_name as product_name',
                'products.description as product_description',
                'products.brand_name as product_brand',
                'sales_order_items.price',
                'sales_order_items.qty',
                'sales_order_items.delivered_qty',
            ]);

        [$onHandByProduct, $onHandByGeneric] = $this->warehouseOnHand();

        return $lines->groupBy('customer_id')->map(function (Collection $customerLines) use ($onHandByProduct, $onHandByGeneric) {
            $items = $customerLines->groupBy('generic_name_id')->map(function (Collection $itemLines) use ($onHandByProduct, $onHandByGeneric) {
                $first = $itemLines->first();
                $ordered = (int) $itemLines->sum('qty');
                $delivered = (int) $itemLines->sum('delivered_qty');

                return [
                    'generic_label' => trim(($first->generic_name ?? 'Unknown item') . ($first->unit ? " ({$first->unit})" : '')),
                    'ordered' => $ordered,
                    'delivered' => $delivered,
                    'balance' => $ordered - $delivered,
                    'on_hand' => (int) ($onHandByGeneric[$first->generic_name_id] ?? 0),
                    'orders' => $itemLines->map(fn ($line) => [
                        'so_id' => $line->so_id,
                        'so_no' => $line->so_no,
                        'po_no' => $line->po_no,
                        'order_date' => $line->order_date,
                        // Same wording rule as the Sales Order's Item Description picker.
                        'product' => $line->product_id ? ($line->product_description ?: ($line->product_brand ?: $line->product_name)) : null,
                        'price' => $line->price !== null ? (float) $line->price : null,
                        // A line that names a product is checked against that product's stock;
                        // one that only names the generic item, against all of its products.
                        'on_hand' => (int) ($line->product_id
                            ? ($onHandByProduct[$line->product_id] ?? 0)
                            : ($onHandByGeneric[$line->generic_name_id] ?? 0)),
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
                'customer_address' => $first->customer_address,
                'so_count' => $customerLines->pluck('so_id')->unique()->count(),
                'item_count' => $items->count(),
                'total_balance' => (int) $items->sum('balance'),
                'items' => $items->all(),
            ];
        })->values();
    }

    /**
     * Warehouse stock, summed per product and per generic item.
     *
     * @return array{0: array<int,int>, 1: array<int,int>}
     */
    protected function warehouseOnHand(): array
    {
        $rows = LocationStock::query()
            ->join('product_batches', 'product_batches.id', '=', 'location_stocks.product_batch_id')
            ->join('products', 'products.id', '=', 'product_batches.product_id')
            ->where('location_stocks.location_id', Location::warehouse()->id)
            ->groupBy('products.id', 'products.generic_name_id')
            ->get(['products.id as product_id', 'products.generic_name_id', DB::raw('SUM(location_stocks.qty) as qty')]);

        return [
            $rows->pluck('qty', 'product_id')->map(fn ($qty) => (int) $qty)->all(),
            $rows->groupBy('generic_name_id')->map(fn (Collection $group) => (int) $group->sum('qty'))->all(),
        ];
    }
}
