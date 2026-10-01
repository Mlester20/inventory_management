<?php

namespace App\Services;

use App\Models\DeliveryReceiptItem;
use App\Models\Location;
use App\Models\LocationStock;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sales Order Summary (Sir's mockup): what each customer has ordered and what is still owed,
 * consolidated across all of their Sales Orders.
 *
 * Buckets are mutually exclusive, so they add up to the S.O count: an archived Sales Order is
 * counted only as Archived (it still counts toward the total, Sir), otherwise by its status —
 * Completed, Cancelled, or Open (open + partially delivered). Drafts are not orders yet and
 * soft-deleted ones are gone, so neither counts.
 *
 * Qty Ordered leaves out cancelled and archived Sales Orders; Undelivered is Open ones only.
 */
class SalesOrderSummaryService
{
    public const SCOPE_UNDELIVERED = 'undelivered';
    public const SCOPE_ORDERED = 'ordered';

    /** Statuses that still owe deliveries. */
    public const OPEN_STATUSES = ['open', 'partially_delivered'];

    /**
     * One row per customer that has Sales Orders, for the summary page.
     *
     * @return Collection<int,array{customer_id:int,customer_name:string,so_count:int,completed:int,open:int,cancelled:int,archived:int,qty_ordered:int,undelivered:int}>
     */
    public function customerSummary(?string $search = null): Collection
    {
        $counts = SalesOrder::query()
            ->join('customers', 'customers.id', '=', 'sales_orders.customer_id')
            ->where('sales_orders.is_draft', false)
            ->when($search, fn ($q) => $q->where('customers.customer_name', 'like', "%{$search}%"))
            ->groupBy('customers.id', 'customers.customer_name')
            ->orderBy('customers.customer_name')
            ->selectRaw("customers.id as customer_id, customers.customer_name,
                COUNT(*) as so_count,
                SUM(sales_orders.archived_at IS NOT NULL) as archived,
                SUM(sales_orders.archived_at IS NULL AND sales_orders.status = 'cancelled') as cancelled,
                SUM(sales_orders.archived_at IS NULL AND sales_orders.status = 'completed') as completed")
            ->get();

        $qty = SalesOrderItem::query()
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
            ->where('sales_orders.is_draft', false)
            ->whereNull('sales_orders.deleted_at')
            ->whereNull('sales_orders.archived_at')
            ->where('sales_orders.status', '!=', 'cancelled')
            ->groupBy('sales_orders.customer_id')
            ->selectRaw("sales_orders.customer_id,
                SUM(sales_order_items.qty) as qty_ordered,
                SUM(CASE WHEN sales_orders.status IN ('open', 'partially_delivered') AND sales_order_items.qty > sales_order_items.delivered_qty
                         THEN sales_order_items.qty - sales_order_items.delivered_qty ELSE 0 END) as undelivered")
            ->get()
            ->keyBy('customer_id');

        return $counts->map(function ($row) use ($qty) {
            $soCount = (int) $row->so_count;
            $archived = (int) $row->archived;
            $cancelled = (int) $row->cancelled;
            $completed = (int) $row->completed;

            return [
                'customer_id' => (int) $row->customer_id,
                'customer_name' => $row->customer_name,
                'so_count' => $soCount,
                'completed' => $completed,
                // Whatever is left after the other buckets, so the four always add up to the count.
                'open' => $soCount - $archived - $cancelled - $completed,
                'cancelled' => $cancelled,
                'archived' => $archived,
                'qty_ordered' => (int) ($qty[$row->customer_id]->qty_ordered ?? 0),
                'undelivered' => (int) ($qty[$row->customer_id]->undelivered ?? 0),
            ];
        })->values();
    }

    /**
     * A customer's Sales Orders, every status, for the S.O count link.
     */
    public function orders(int $customerId, ?string $search = null): Collection
    {
        return SalesOrder::query()
            ->with('customer')
            ->where('customer_id', $customerId)
            ->where('is_draft', false)
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->where('so_no', 'like', "%{$search}%")
                ->orWhere('po_no', 'like', "%{$search}%")))
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get();
    }

    /** ARCHIVED / COMPLETED / CANCELLED / OPEN — the same buckets as the summary. */
    public static function statusLabel(SalesOrder $order): string
    {
        return match (true) {
            $order->archived_at !== null => 'ARCHIVED',
            $order->status === 'cancelled' => 'CANCELLED',
            $order->status === 'completed' => 'COMPLETED',
            default => 'OPEN',
        };
    }

    /**
     * The item lines behind the Undelivered / Qty Ordered links, as customer -> items -> Sales Order lines.
     * Undelivered: lines of Open Sales Orders that still have a balance. Ordered: every line of the
     * Sales Orders that are neither cancelled nor archived. Each line carries the SO price and the
     * on-hand stock split by location — Warehouse (where deliveries come from) and POS — per Sir.
     *
     * @return Collection<int,array>
     */
    public function items(string $scope, ?int $customerId = null, ?string $search = null, ?string $from = null, ?string $to = null): Collection
    {
        $lines = SalesOrderItem::query()
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
            ->join('customers', 'customers.id', '=', 'sales_orders.customer_id')
            ->leftJoin('generic_names', 'generic_names.id', '=', 'sales_order_items.generic_name_id')
            ->leftJoin('products', 'products.id', '=', 'sales_order_items.product_id')
            ->where('sales_orders.is_draft', false)
            ->whereNull('sales_orders.deleted_at')
            ->whereNull('sales_orders.archived_at')
            ->where('sales_orders.status', '!=', 'cancelled')
            ->when($scope === self::SCOPE_UNDELIVERED, fn ($q) => $q
                ->whereIn('sales_orders.status', self::OPEN_STATUSES)
                ->whereColumn('sales_order_items.qty', '>', 'sales_order_items.delivered_qty'))
            ->when($customerId, fn ($q) => $q->where('sales_orders.customer_id', $customerId))
            ->when($from, fn ($q) => $q->whereDate('sales_orders.order_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('sales_orders.order_date', '<=', $to))
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->where('sales_orders.po_no', 'like', "%{$search}%")
                ->orWhere('sales_orders.so_no', 'like', "%{$search}%")
                ->orWhere('generic_names.generic_name', 'like', "%{$search}%")
                ->orWhere('products.item_name', 'like', "%{$search}%")
                ->orWhere('products.description', 'like', "%{$search}%")
                ->orWhere('products.brand_name', 'like', "%{$search}%")))
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
                'sales_order_items.id as so_item_id',
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

        [$onHandByProduct, $onHandByGeneric] = $this->onHand();
        $noStock = ['warehouse' => 0, 'pos' => 0];

        return $lines->groupBy('customer_id')->map(function (Collection $customerLines) use ($onHandByProduct, $onHandByGeneric, $noStock) {
            $items = $customerLines->groupBy('generic_name_id')->map(function (Collection $itemLines) use ($onHandByProduct, $onHandByGeneric, $noStock) {
                $first = $itemLines->first();
                $ordered = (int) $itemLines->sum('qty');
                $delivered = (int) $itemLines->sum('delivered_qty');

                return [
                    'generic_name_id' => $first->generic_name_id,
                    'generic_label' => trim(($first->generic_name ?? 'Unknown item') . ($first->unit ? " ({$first->unit})" : '')),
                    'ordered' => $ordered,
                    'delivered' => $delivered,
                    'balance' => $ordered - $delivered,
                    'on_hand' => $onHandByGeneric[$first->generic_name_id] ?? $noStock,
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
                        // Warehouse + POS, per Sir, so it's clear how much is sellable versus in bulk.
                        'on_hand' => $line->product_id
                            ? ($onHandByProduct[$line->product_id] ?? $noStock)
                            : ($onHandByGeneric[$line->generic_name_id] ?? $noStock),
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
                'total_ordered' => (int) $items->sum('ordered'),
                'total_delivered' => (int) $items->sum('delivered'),
                'total_balance' => (int) $items->sum('balance'),
                'items' => $items->all(),
            ];
        })->values();
    }

    /**
     * Delivery Receipt lines for tracing: what was delivered against a Sales Order line, a Sales
     * Order, or — across all of a customer's POs — one item. Per Sir, this also picks up Delivery
     * Receipts that never had a Sales Order behind them (Advance Order / Walk-In DRs) — those have
     * no `sales_order_item_id`, so the Sales Order side is left-joined and the customer and generic
     * item come from the DR/product side instead. Drafts and cancelled Delivery Receipts did not
     * deliver anything, so they are left out.
     */
    public function deliveries(?int $customerId = null, ?int $salesOrderId = null, ?int $genericNameId = null, ?string $search = null): Collection
    {
        return DeliveryReceiptItem::query()
            ->join('delivery_receipts', 'delivery_receipts.id', '=', 'delivery_receipt_items.delivery_receipt_id')
            ->join('customers', 'customers.id', '=', 'delivery_receipts.customer_id')
            ->leftJoin('sales_order_items', 'sales_order_items.id', '=', 'delivery_receipt_items.sales_order_item_id')
            ->leftJoin('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
            ->leftJoin('product_batches', 'product_batches.id', '=', 'delivery_receipt_items.product_batch_id')
            ->leftJoin('products', 'products.id', '=', 'product_batches.product_id')
            ->leftJoin('generic_names', 'generic_names.id', '=', 'products.generic_name_id')
            ->where('delivery_receipts.is_draft', false)
            ->where('delivery_receipts.status', '!=', 'cancelled')
            ->when($customerId, fn ($q) => $q->where('delivery_receipts.customer_id', $customerId))
            ->when($salesOrderId, fn ($q) => $q->where('sales_orders.id', $salesOrderId))
            ->when($genericNameId, fn ($q) => $q->where('products.generic_name_id', $genericNameId))
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->where('delivery_receipts.dr_no', 'like', "%{$search}%")
                ->orWhere('customers.customer_name', 'like', "%{$search}%")
                ->orWhere('sales_orders.po_no', 'like', "%{$search}%")
                ->orWhere('sales_orders.so_no', 'like', "%{$search}%")
                ->orWhere('generic_names.generic_name', 'like', "%{$search}%")))
            ->orderByDesc('delivery_receipts.receipt_date')
            ->orderByDesc('delivery_receipts.id')
            ->get([
                'delivery_receipts.id as dr_id',
                'delivery_receipts.dr_no',
                'delivery_receipts.receipt_date',
                'delivery_receipts.transaction_type',
                'sales_orders.id as so_id',
                'sales_orders.so_no',
                'sales_orders.po_no',
                'customers.customer_name',
                'generic_names.generic_name',
                'generic_names.unit',
                'products.description as product_description',
                'products.brand_name as product_brand',
                'products.item_name as product_name',
                'delivery_receipt_items.qty',
            ]);
    }

    /**
     * Every Generic Item's Sales Order lines, across ALL customers, grouped by Generic Item —
     * Sir's "S.O Summary by Item" tab. On-Hand is Warehouse only (Sir confirmed — that's where
     * Delivery Receipt draws from). Reserved has two different scopes, both per Sir's own
     * clarification on his mockup: at the Generic Item level, ALL draft Delivery Receipt
     * quantities of that item, even ones with no Sales Order behind them (Advance Order/Walk-In
     * drafts) — "para ma-maximize ang gamit ng DR Draft" as a general reserve mechanism; at the
     * line level (one Sales Order item), only the draft DR quantity tied to THAT specific line.
     * Available = On-Hand − Reserved (the Generic Item-level Reserved).
     *
     * @return Collection<int,array>
     */
    public function byItem(?int $categoryId = null, bool $hideZeroBalance = false, bool $showZeroOnHand = false, ?string $search = null): Collection
    {
        $lines = SalesOrderItem::query()
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
            ->join('customers', 'customers.id', '=', 'sales_orders.customer_id')
            ->leftJoin('generic_names', 'generic_names.id', '=', 'sales_order_items.generic_name_id')
            ->leftJoin('categories', 'categories.id', '=', 'generic_names.category_id')
            ->leftJoin('products', 'products.id', '=', 'sales_order_items.product_id')
            ->where('sales_orders.is_draft', false)
            ->whereNull('sales_orders.deleted_at')
            ->whereNull('sales_orders.archived_at')
            ->where('sales_orders.status', '!=', 'cancelled')
            ->when($categoryId, fn ($q) => $q->where('generic_names.category_id', $categoryId))
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->where('sales_orders.po_no', 'like', "%{$search}%")
                ->orWhere('sales_orders.so_no', 'like', "%{$search}%")
                ->orWhere('customers.customer_name', 'like', "%{$search}%")
                ->orWhere('generic_names.generic_name', 'like', "%{$search}%")
                ->orWhere('products.item_name', 'like', "%{$search}%")
                ->orWhere('products.description', 'like', "%{$search}%")
                ->orWhere('products.brand_name', 'like', "%{$search}%")))
            ->orderBy('generic_names.generic_name')
            ->orderBy('generic_names.unit')
            ->orderBy('sales_orders.order_date')
            ->orderBy('sales_orders.id')
            ->get([
                'sales_order_items.id as so_item_id',
                'sales_orders.id as so_id',
                'sales_orders.so_no',
                'sales_orders.po_no',
                'sales_orders.order_date',
                'sales_orders.status as so_status',
                'customers.customer_name',
                'sales_order_items.generic_name_id',
                'generic_names.generic_name',
                'generic_names.unit',
                'categories.category_name',
                'sales_order_items.product_id',
                'products.item_name as product_name',
                'products.description as product_description',
                'products.brand_name as product_brand',
                'sales_order_items.qty',
                'sales_order_items.delivered_qty',
                'sales_order_items.remarks',
            ]);

        [, $onHandByGeneric] = $this->onHand();
        $reservedAllByGeneric = $this->reservedByGeneric();
        $reservedBySoItem = $this->reservedBySoItem();

        $grouped = $lines->groupBy('generic_name_id')->map(function (Collection $genericLines) use ($onHandByGeneric, $reservedAllByGeneric, $reservedBySoItem) {
            $first = $genericLines->first();
            $totalOrder = (int) $genericLines->sum('qty');
            $delivered = (int) $genericLines->sum('delivered_qty');
            $undelivered = (int) $genericLines
                ->filter(fn ($l) => in_array($l->so_status, self::OPEN_STATUSES, true) && $l->qty > $l->delivered_qty)
                ->sum(fn ($l) => $l->qty - $l->delivered_qty);
            $onHand = $onHandByGeneric[$first->generic_name_id]['warehouse'] ?? 0;
            $reserved = $reservedAllByGeneric[$first->generic_name_id] ?? 0;

            return [
                'generic_name_id' => $first->generic_name_id,
                'generic_label' => trim(($first->generic_name ?? 'Unknown item') . ($first->unit ? " ({$first->unit})" : '')),
                'category_name' => $first->category_name,
                'unit' => $first->unit,
                'total_order' => $totalOrder,
                'undelivered' => $undelivered,
                'on_hand' => $onHand,
                'reserved' => $reserved,
                'available' => $onHand - $reserved,
                'balance' => $totalOrder - $delivered,
                'orders' => $genericLines->map(fn ($line) => [
                    'so_item_id' => $line->so_item_id,
                    'so_id' => $line->so_id,
                    'so_no' => $line->so_no,
                    'po_no' => $line->po_no,
                    'order_date' => $line->order_date,
                    'customer_name' => $line->customer_name,
                    'product' => $line->product_id ? ($line->product_description ?: ($line->product_brand ?: $line->product_name)) : null,
                    'ordered' => (int) $line->qty,
                    'delivered' => (int) $line->delivered_qty,
                    'balance' => (int) $line->qty - (int) $line->delivered_qty,
                    'reserved' => $reservedBySoItem[$line->so_item_id] ?? 0,
                    'remarks' => $line->remarks,
                ])->values()->all(),
            ];
        })->values();

        if ($hideZeroBalance) {
            $grouped = $grouped->filter(fn ($g) => $g['balance'] > 0)->values();
        }

        // Per Sir's literal wording — the default hides an item with nothing in the Warehouse at
        // all (usually not useful on a still-owed work list), and this reveals it.
        if (! $showZeroOnHand) {
            $grouped = $grouped->filter(fn ($g) => $g['on_hand'] > 0)->values();
        }

        return $grouped;
    }

    /**
     * ALL draft Delivery Receipt quantities per Generic Item, whether or not tied to a Sales
     * Order — Sir's broader "Reserved" scope for the Generic Item-level total.
     *
     * @return array<int,int>
     */
    protected function reservedByGeneric(): array
    {
        return DeliveryReceiptItem::query()
            ->join('delivery_receipts', 'delivery_receipts.id', '=', 'delivery_receipt_items.delivery_receipt_id')
            ->join('product_batches', 'product_batches.id', '=', 'delivery_receipt_items.product_batch_id')
            ->join('products', 'products.id', '=', 'product_batches.product_id')
            ->where('delivery_receipts.is_draft', true)
            ->where('delivery_receipts.status', '!=', 'cancelled')
            ->groupBy('products.generic_name_id')
            ->get(['products.generic_name_id', DB::raw('SUM(delivery_receipt_items.qty) as qty')])
            ->pluck('qty', 'generic_name_id')
            ->map(fn ($qty) => (int) $qty)
            ->all();
    }

    /**
     * Draft Delivery Receipt quantities tied to one specific Sales Order item — Sir's narrower
     * "Reserved" scope for the per-line drill-down.
     *
     * @return array<int,int>
     */
    protected function reservedBySoItem(): array
    {
        return DeliveryReceiptItem::query()
            ->join('delivery_receipts', 'delivery_receipts.id', '=', 'delivery_receipt_items.delivery_receipt_id')
            ->where('delivery_receipts.is_draft', true)
            ->where('delivery_receipts.status', '!=', 'cancelled')
            ->whereNotNull('delivery_receipt_items.sales_order_item_id')
            ->groupBy('delivery_receipt_items.sales_order_item_id')
            ->get(['delivery_receipt_items.sales_order_item_id', DB::raw('SUM(delivery_receipt_items.qty) as qty')])
            ->pluck('qty', 'sales_order_item_id')
            ->map(fn ($qty) => (int) $qty)
            ->all();
    }

    /**
     * On-hand stock, summed per product and per generic item, split Warehouse vs POS — per Sir,
     * two separate columns instead of one Warehouse-only figure.
     *
     * @return array{0: array<int,array{warehouse:int,pos:int}>, 1: array<int,array{warehouse:int,pos:int}>}
     */
    protected function onHand(): array
    {
        $warehouseId = Location::warehouse()->id;
        $posId = Location::pos()->id;

        $rows = LocationStock::query()
            ->join('product_batches', 'product_batches.id', '=', 'location_stocks.product_batch_id')
            ->join('products', 'products.id', '=', 'product_batches.product_id')
            ->whereIn('location_stocks.location_id', [$warehouseId, $posId])
            ->groupBy('products.id', 'products.generic_name_id', 'location_stocks.location_id')
            ->get([
                'products.id as product_id',
                'products.generic_name_id',
                'location_stocks.location_id',
                DB::raw('SUM(location_stocks.qty) as qty'),
            ]);

        $split = function (Collection $rows, string $key) use ($warehouseId, $posId) {
            $out = [];
            foreach ($rows->groupBy($key) as $id => $group) {
                $out[$id] = [
                    'warehouse' => (int) $group->where('location_id', $warehouseId)->sum('qty'),
                    'pos' => (int) $group->where('location_id', $posId)->sum('qty'),
                ];
            }

            return $out;
        };

        return [$split($rows, 'product_id'), $split($rows, 'generic_name_id')];
    }
}
