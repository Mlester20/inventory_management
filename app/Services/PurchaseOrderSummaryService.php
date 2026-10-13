<?php

namespace App\Services;

use App\Models\GoodsReceiptItem;
use App\Models\Location;
use App\Models\LocationStock;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Purchase Order Summary: what each supplier has been ordered from and what is still owed,
 * consolidated across all of their Purchase Orders. Mirrors Sales Order Summary's shape (see
 * SalesOrderSummaryService) but adapted to how Purchase Order actually works: a Purchase Order
 * has only three statuses — open / partially_received / completed, no cancelled/archived bucket
 * and no soft deletes — and a draft Goods Receipt never reserves anything the way a draft
 * Delivery Receipt does, so there is no Reserved/Available pair here, just On-Hand and what is
 * still Pending.
 */
class PurchaseOrderSummaryService
{
    public const SCOPE_PENDING = 'pending';
    public const SCOPE_ORDERED = 'ordered';

    /** Statuses that still owe receipts. */
    public const OPEN_STATUSES = ['open', 'partially_received'];

    /**
     * One row per supplier that has Purchase Orders, for the summary page.
     *
     * @return Collection<int,array{supplier_id:int,supplier_name:string,po_count:int,completed:int,partially_received:int,open:int,qty_ordered:int,pending:int}>
     */
    public function supplierSummary(?string $search = null): Collection
    {
        $counts = PurchaseOrder::query()
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->where('purchase_orders.is_draft', false)
            ->when($search, fn ($q) => $q->where('suppliers.supplier_name', 'like', "%{$search}%"))
            ->groupBy('suppliers.id', 'suppliers.supplier_name')
            ->orderBy('suppliers.supplier_name')
            ->selectRaw("suppliers.id as supplier_id, suppliers.supplier_name,
                COUNT(*) as po_count,
                SUM(purchase_orders.status = 'completed') as completed,
                SUM(purchase_orders.status = 'partially_received') as partially_received")
            ->get();

        $qty = PurchaseOrderItem::query()
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('purchase_orders.is_draft', false)
            ->groupBy('purchase_orders.supplier_id')
            ->selectRaw("purchase_orders.supplier_id,
                SUM(purchase_order_items.qty) as qty_ordered,
                SUM(CASE WHEN purchase_orders.status IN ('open', 'partially_received') AND purchase_order_items.qty > purchase_order_items.received_qty
                         THEN purchase_order_items.qty - purchase_order_items.received_qty ELSE 0 END) as pending")
            ->get()
            ->keyBy('supplier_id');

        return $counts->map(function ($row) use ($qty) {
            $poCount = (int) $row->po_count;
            $completed = (int) $row->completed;
            $partiallyReceived = (int) $row->partially_received;

            return [
                'supplier_id' => (int) $row->supplier_id,
                'supplier_name' => $row->supplier_name,
                'po_count' => $poCount,
                'completed' => $completed,
                'partially_received' => $partiallyReceived,
                // Whatever is left after the other buckets, so the three always add up to the count.
                'open' => $poCount - $completed - $partiallyReceived,
                'qty_ordered' => (int) ($qty[$row->supplier_id]->qty_ordered ?? 0),
                'pending' => (int) ($qty[$row->supplier_id]->pending ?? 0),
            ];
        })->values();
    }

    /**
     * A supplier's Purchase Orders, every status, for the P.O count link.
     */
    public function orders(int $supplierId, ?string $search = null): Collection
    {
        return PurchaseOrder::query()
            ->with('supplier')
            ->where('supplier_id', $supplierId)
            ->where('is_draft', false)
            ->when($search, fn ($q) => $q->where('po_no', 'like', "%{$search}%"))
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get();
    }

    /** OPEN / PARTIALLY RECEIVED / COMPLETED — the same buckets as the summary. */
    public static function statusLabel(PurchaseOrder $order): string
    {
        return match ($order->status) {
            'completed' => 'COMPLETED',
            'partially_received' => 'PARTIALLY RECEIVED',
            default => 'OPEN',
        };
    }

    /**
     * The item lines behind the Pending / Qty Ordered links, as supplier -> items -> Purchase
     * Order lines. Pending: lines of open/partially-received Purchase Orders that still have a
     * balance. Ordered: every line, period (a Purchase Order is never cancelled or archived).
     * Each line carries the PO unit cost and the on-hand stock split by location — Warehouse
     * (where Goods Receipt stocks in) and POS — same as Sales Order Summary.
     *
     * @return Collection<int,array>
     */
    public function items(string $scope, ?int $supplierId = null, ?string $search = null): Collection
    {
        $lines = PurchaseOrderItem::query()
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->leftJoin('generic_names', 'generic_names.id', '=', 'purchase_order_items.generic_name_id')
            ->leftJoin('products', 'products.id', '=', 'purchase_order_items.product_id')
            ->where('purchase_orders.is_draft', false)
            ->when($scope === self::SCOPE_PENDING, fn ($q) => $q
                ->whereIn('purchase_orders.status', self::OPEN_STATUSES)
                ->whereColumn('purchase_order_items.qty', '>', 'purchase_order_items.received_qty'))
            ->when($supplierId, fn ($q) => $q->where('purchase_orders.supplier_id', $supplierId))
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->where('purchase_orders.po_no', 'like', "%{$search}%")
                ->orWhere('generic_names.generic_name', 'like', "%{$search}%")
                ->orWhere('products.item_name', 'like', "%{$search}%")
                ->orWhere('products.description', 'like', "%{$search}%")
                ->orWhere('products.brand_name', 'like', "%{$search}%")))
            ->orderBy('suppliers.supplier_name')
            ->orderBy('generic_names.generic_name')
            ->orderBy('generic_names.unit')
            ->orderBy('purchase_orders.order_date')
            ->orderBy('purchase_orders.id')
            ->get([
                'suppliers.id as supplier_id',
                'suppliers.supplier_name',
                'suppliers.delivery_address as supplier_address',
                'purchase_orders.id as po_id',
                'purchase_orders.po_no',
                'purchase_orders.order_date',
                'purchase_order_items.id as po_item_id',
                'purchase_order_items.generic_name_id',
                'generic_names.generic_name',
                'generic_names.unit',
                'purchase_order_items.product_id',
                'products.item_name as product_name',
                'products.description as product_description',
                'products.brand_name as product_brand',
                'purchase_order_items.unit_cost',
                'purchase_order_items.qty',
                'purchase_order_items.received_qty',
            ]);

        [$onHandByProduct, $onHandByGeneric] = $this->onHand();
        $noStock = ['warehouse' => 0, 'pos' => 0];

        return $lines->groupBy('supplier_id')->map(function (Collection $supplierLines) use ($onHandByProduct, $onHandByGeneric, $noStock) {
            $items = $supplierLines->groupBy('generic_name_id')->map(function (Collection $itemLines) use ($onHandByProduct, $onHandByGeneric, $noStock) {
                $first = $itemLines->first();
                $ordered = (int) $itemLines->sum('qty');
                $received = (int) $itemLines->sum('received_qty');

                return [
                    'generic_name_id' => $first->generic_name_id,
                    'generic_label' => trim(($first->generic_name ?? 'Unknown item') . ($first->unit ? " ({$first->unit})" : '')),
                    'ordered' => $ordered,
                    'received' => $received,
                    'balance' => $ordered - $received,
                    'on_hand' => $onHandByGeneric[$first->generic_name_id] ?? $noStock,
                    'orders' => $itemLines->map(fn ($line) => [
                        'po_id' => $line->po_id,
                        'po_no' => $line->po_no,
                        'order_date' => $line->order_date,
                        // Same wording rule as the Goods Receipt "Against PO" Brand field.
                        'product' => $line->product_id ? ($line->product_description ?: ($line->product_brand ?: $line->product_name)) : null,
                        'unit_cost' => $line->unit_cost !== null ? (float) $line->unit_cost : null,
                        // A line that names a product is checked against that product's stock;
                        // one that only names the generic item, against all of its products.
                        'on_hand' => $line->product_id
                            ? ($onHandByProduct[$line->product_id] ?? $noStock)
                            : ($onHandByGeneric[$line->generic_name_id] ?? $noStock),
                        'ordered' => (int) $line->qty,
                        'received' => (int) $line->received_qty,
                        'balance' => (int) $line->qty - (int) $line->received_qty,
                    ])->values()->all(),
                ];
            })->values();

            $first = $supplierLines->first();

            return [
                'supplier_id' => $first->supplier_id,
                'supplier_name' => $first->supplier_name,
                'supplier_address' => $first->supplier_address,
                'po_count' => $supplierLines->pluck('po_id')->unique()->count(),
                'item_count' => $items->count(),
                'total_ordered' => (int) $items->sum('ordered'),
                'total_received' => (int) $items->sum('received'),
                'total_balance' => (int) $items->sum('balance'),
                'items' => $items->all(),
            ];
        })->values();
    }

    /**
     * Goods Receipt lines for tracing: what was actually received against a Purchase Order line,
     * a Purchase Order, or — across all of a supplier's POs — one item. Also picks up Goods
     * Receipts that never had a Purchase Order behind them (Direct Receipts) — those have no
     * `purchase_order_item_id`, so the Purchase Order side is left-joined. Draft Goods Receipts
     * never touched stock, so they are left out.
     */
    public function receipts(?int $supplierId = null, ?int $purchaseOrderId = null, ?int $genericNameId = null, ?string $search = null): Collection
    {
        return GoodsReceiptItem::query()
            ->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_items.goods_receipt_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'goods_receipts.supplier_id')
            ->leftJoin('purchase_order_items', 'purchase_order_items.id', '=', 'goods_receipt_items.purchase_order_item_id')
            ->leftJoin('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->leftJoin('products', 'products.id', '=', 'goods_receipt_items.product_id')
            ->leftJoin('generic_names', 'generic_names.id', '=', 'products.generic_name_id')
            ->where('goods_receipts.status', 'posted')
            ->when($supplierId, fn ($q) => $q->where('goods_receipts.supplier_id', $supplierId))
            ->when($purchaseOrderId, fn ($q) => $q->where('purchase_orders.id', $purchaseOrderId))
            ->when($genericNameId, fn ($q) => $q->where('products.generic_name_id', $genericNameId))
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->where('goods_receipts.gr_no', 'like', "%{$search}%")
                ->orWhere('suppliers.supplier_name', 'like', "%{$search}%")
                ->orWhere('purchase_orders.po_no', 'like', "%{$search}%")
                ->orWhere('generic_names.generic_name', 'like', "%{$search}%")))
            ->orderByDesc('goods_receipts.receipt_date')
            ->orderByDesc('goods_receipts.id')
            ->get([
                'goods_receipts.id as gr_id',
                'goods_receipts.gr_no',
                'goods_receipts.receipt_date',
                'purchase_orders.id as po_id',
                'purchase_orders.po_no',
                'suppliers.supplier_name',
                'generic_names.generic_name',
                'generic_names.unit',
                'products.description as product_description',
                'products.brand_name as product_brand',
                'products.item_name as product_name',
                'goods_receipt_items.qty',
            ]);
    }

    /**
     * Every Generic Item's Purchase Order lines, across ALL suppliers — grouped by Generic Item.
     * On-Hand is Warehouse only, same as Sales Order Summary's "By Item" tab (that's where Goods
     * Receipt stocks in). There is no Reserved/Available pair here — a draft Goods Receipt has
     * never touched stock, so it reserves nothing the way a draft Delivery Receipt does.
     *
     * @return Collection<int,array>
     */
    public function byItem(?int $categoryId = null, bool $hideZeroBalance = false, bool $showZeroOnHand = false, ?string $search = null): Collection
    {
        $lines = PurchaseOrderItem::query()
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->leftJoin('generic_names', 'generic_names.id', '=', 'purchase_order_items.generic_name_id')
            ->leftJoin('categories', 'categories.id', '=', 'generic_names.category_id')
            ->leftJoin('products', 'products.id', '=', 'purchase_order_items.product_id')
            ->where('purchase_orders.is_draft', false)
            ->when($categoryId, fn ($q) => $q->where('generic_names.category_id', $categoryId))
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->where('purchase_orders.po_no', 'like', "%{$search}%")
                ->orWhere('suppliers.supplier_name', 'like', "%{$search}%")
                ->orWhere('generic_names.generic_name', 'like', "%{$search}%")
                ->orWhere('products.item_name', 'like', "%{$search}%")
                ->orWhere('products.description', 'like', "%{$search}%")
                ->orWhere('products.brand_name', 'like', "%{$search}%")))
            ->orderBy('generic_names.generic_name')
            ->orderBy('generic_names.unit')
            ->orderBy('purchase_orders.order_date')
            ->orderBy('purchase_orders.id')
            ->get([
                'purchase_order_items.id as po_item_id',
                'purchase_orders.id as po_id',
                'purchase_orders.po_no',
                'purchase_orders.order_date',
                'purchase_orders.status as po_status',
                'suppliers.supplier_name',
                'purchase_order_items.generic_name_id',
                'generic_names.generic_name',
                'generic_names.unit',
                'categories.category_name',
                'purchase_order_items.product_id',
                'products.item_name as product_name',
                'products.description as product_description',
                'products.brand_name as product_brand',
                'purchase_order_items.qty',
                'purchase_order_items.received_qty',
                'purchase_order_items.remarks',
            ]);

        [, $onHandByGeneric] = $this->onHand();

        $grouped = $lines->groupBy('generic_name_id')->map(function (Collection $genericLines) use ($onHandByGeneric) {
            $first = $genericLines->first();
            $totalOrder = (int) $genericLines->sum('qty');
            $received = (int) $genericLines->sum('received_qty');
            $pending = (int) $genericLines
                ->filter(fn ($l) => in_array($l->po_status, self::OPEN_STATUSES, true) && $l->qty > $l->received_qty)
                ->sum(fn ($l) => $l->qty - $l->received_qty);
            $onHand = $onHandByGeneric[$first->generic_name_id]['warehouse'] ?? 0;

            return [
                'generic_name_id' => $first->generic_name_id,
                'generic_label' => trim(($first->generic_name ?? 'Unknown item') . ($first->unit ? " ({$first->unit})" : '')),
                'category_name' => $first->category_name,
                'unit' => $first->unit,
                'total_order' => $totalOrder,
                'pending' => $pending,
                'on_hand' => $onHand,
                'balance' => $totalOrder - $received,
                'orders' => $genericLines->map(fn ($line) => [
                    'po_item_id' => $line->po_item_id,
                    'po_id' => $line->po_id,
                    'po_no' => $line->po_no,
                    'order_date' => $line->order_date,
                    'supplier_name' => $line->supplier_name,
                    'product' => $line->product_id ? ($line->product_description ?: ($line->product_brand ?: $line->product_name)) : null,
                    'ordered' => (int) $line->qty,
                    'received' => (int) $line->received_qty,
                    'balance' => (int) $line->qty - (int) $line->received_qty,
                    'remarks' => $line->remarks,
                ])->values()->all(),
            ];
        })->values();

        if ($hideZeroBalance) {
            $grouped = $grouped->filter(fn ($g) => $g['balance'] > 0)->values();
        }

        if (! $showZeroOnHand) {
            $grouped = $grouped->filter(fn ($g) => $g['on_hand'] > 0)->values();
        }

        return $grouped;
    }

    /**
     * On-hand stock, summed per product and per generic item, split Warehouse vs POS — identical
     * to SalesOrderSummaryService::onHand(), duplicated here rather than shared since the two
     * reports otherwise have nothing else in common.
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
