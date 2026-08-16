<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoodsReceiptService
{
    public function __construct(protected StockService $stockService) {}

    /**
     * Purchase Order lines that still have a remaining (unreceived) balance.
     */
    public function getPendingPurchaseOrderItems(PurchaseOrder $purchaseOrder): Collection
    {
        return $purchaseOrder->items()
            ->with(['product.batches', 'genericName'])
            ->get()
            ->filter(fn (PurchaseOrderItem $item) => $item->remaining_qty > 0)
            ->values();
    }

    /**
     * Create a Goods Receipt with its line items, restocking each line's
     * batch (via StockService) and updating any linked Purchase Order line
     * balances. A line with a batch_no matching an existing batch under the
     * product tops that batch up; otherwise a new batch is created.
     *
     * @param array $data ['supplier_id', 'purchase_order_id', 'receipt_date', 'prepared_by',
     *                     'items' => [['product_id','qty','unit_cost','batch_no','expiration_date','purchase_order_item_id'], ...]]
     */
    public function createGoodsReceipt(array $data, ?int $userId = null): GoodsReceipt
    {
        return DB::transaction(function () use ($data, $userId) {
            $goodsReceipt = GoodsReceipt::create([
                'supplier_id' => $data['supplier_id'],
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'gr_no' => $this->generateGrNo(),
                'receipt_date' => $data['receipt_date'],
                'prepared_by' => $data['prepared_by'] ?? null,
                'status' => 'posted',
            ]);

            $this->applyItems($goodsReceipt, $data['items'], $userId);

            return $goodsReceipt;
        });
    }

    /**
     * Save (or re-save) a draft — no stock movement, no Purchase Order
     * balance changes, so the encoder can leave supplier/items blank/
     * incomplete and resume later. Only the header and items are persisted;
     * nothing here ever touches location_stocks or received_qty.
     *
     * @param array $data ['supplier_id', 'purchase_order_id', 'receipt_date', 'prepared_by',
     *                     'items' => [['product_id','qty','unit_cost','batch_no','expiration_date','purchase_order_item_id'], ...]]
     */
    public function saveDraft(array $data, ?int $userId = null, ?GoodsReceipt $existing = null): GoodsReceipt
    {
        return DB::transaction(function () use ($data, $userId, $existing) {
            $goodsReceipt = $existing ?? new GoodsReceipt([
                'gr_no' => $this->generateGrNo(),
            ]);

            $goodsReceipt->fill([
                'supplier_id' => $data['supplier_id'] ?? null,
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'receipt_date' => $data['receipt_date'] ?? now()->toDateString(),
                'prepared_by' => $data['prepared_by'] ?? $userId,
                'status' => 'draft',
            ]);
            $goodsReceipt->save();

            // Replace whatever items existed before — safe, since a draft's
            // items have never been read by anything that moves stock.
            $goodsReceipt->items()->delete();
            foreach ($data['items'] ?? [] as $line) {
                if (empty($line['product_id'])) {
                    continue;
                }

                $goodsReceipt->items()->create([
                    'purchase_order_item_id' => $line['purchase_order_item_id'] ?? null,
                    'product_id' => $line['product_id'],
                    'product_batch_id' => $line['product_batch_id'] ?? null,
                    'qty' => $line['qty'] ?? null,
                    'unit' => $line['unit'] ?? null,
                    'unit_cost' => $line['unit_cost'] ?? null,
                    'batch_no' => $line['batch_no'] ?? null,
                    'expiration_date' => $line['expiration_date'] ?? null,
                    'remarks' => $line['remarks'] ?? null,
                ]);
            }

            return $goodsReceipt;
        });
    }

    /**
     * Turn a draft into a real, posted Goods Receipt — the one moment its
     * stock (and any linked Purchase Order balances) actually move.
     * Replaces the draft's items with the final values, then runs the same
     * stock-moving logic createGoodsReceipt() uses.
     */
    public function finalizeDraft(GoodsReceipt $draft, array $data, ?int $userId = null): GoodsReceipt
    {
        return DB::transaction(function () use ($draft, $data, $userId) {
            $draft->fill([
                'supplier_id' => $data['supplier_id'],
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'receipt_date' => $data['receipt_date'],
                'prepared_by' => $data['prepared_by'] ?? null,
                'status' => 'posted',
            ]);
            $draft->save();

            $draft->items()->delete();
            $this->applyItems($draft, $data['items'], $userId);

            return $draft;
        });
    }

    /**
     * Resolve each line's batch, restock it, and update any linked Purchase
     * Order line balances — shared by createGoodsReceipt() and
     * finalizeDraft() so this logic (especially the PO received_qty
     * increment) exists in exactly one place and never runs for a draft.
     */
    protected function applyItems(GoodsReceipt $goodsReceipt, array $items, ?int $userId): void
    {
        $hasAnyQty = collect($items)->contains(fn ($line) => ! empty($line['qty']) && (int) $line['qty'] > 0);
        if (! $hasAnyQty) {
            throw ValidationException::withMessages([
                'items' => 'Enter a quantity for at least one item.',
            ]);
        }

        $affectedPurchaseOrders = [];

        foreach ($items as $line) {
            // A partial receipt intentionally leaves some pending PO
            // lines blank (not yet delivered) — skip them rather than
            // treating a blank qty as 0 units received.
            if (empty($line['qty']) || (int) $line['qty'] <= 0) {
                continue;
            }

            $product = Product::findOrFail($line['product_id']);
            $qty = (int) $line['qty'];

            $purchaseOrderItem = null;
            if (! empty($line['purchase_order_item_id'])) {
                $purchaseOrderItem = PurchaseOrderItem::findOrFail($line['purchase_order_item_id']);

                if ($qty > $purchaseOrderItem->remaining_qty) {
                    throw ValidationException::withMessages([
                        'items' => "Received qty for {$product->item_name} exceeds the remaining balance on the Purchase Order ({$purchaseOrderItem->remaining_qty}).",
                    ]);
                }
            }

            $batch = $this->resolveBatch($product, $line);

            $this->stockService->restock($batch, $qty, Location::warehouse(), "Goods Receipt {$goodsReceipt->gr_no}", $userId, $goodsReceipt);

            $product->update(['unit_cost' => $line['unit_cost']]);

            $goodsReceipt->items()->create([
                'purchase_order_item_id' => $purchaseOrderItem?->id,
                'product_id' => $product->id,
                'product_batch_id' => $batch->id,
                'qty' => $qty,
                'unit' => $line['unit'] ?? null,
                'unit_cost' => $line['unit_cost'],
                'batch_no' => $batch->batch_no,
                'expiration_date' => $batch->expiration_date,
                'remarks' => $line['remarks'] ?? null,
            ]);

            if ($purchaseOrderItem) {
                $purchaseOrderItem->increment('received_qty', $qty);
                $affectedPurchaseOrders[$purchaseOrderItem->purchase_order_id] = true;
            }
        }

        foreach (array_keys($affectedPurchaseOrders) as $purchaseOrderId) {
            $this->refreshPurchaseOrderStatus(PurchaseOrder::findOrFail($purchaseOrderId));
        }
    }

    /**
     * Resolve which batch a received line tops up: an existing batch under
     * the product matching the typed batch_no, or a brand new one.
     */
    protected function resolveBatch(Product $product, array $line): ProductBatch
    {
        $batchNo = $line['batch_no'] ?? null;

        if ($batchNo) {
            $existing = $product->batches()->where('batch_no', $batchNo)->first();
            if ($existing) {
                return $existing;
            }
        }

        return $product->batches()->create([
            'batch_no' => $batchNo,
            'expiration_date' => $line['expiration_date'] ?? null,
        ]);
    }

    /**
     * Recompute a Purchase Order's status from its line items' received quantities.
     */
    protected function refreshPurchaseOrderStatus(PurchaseOrder $purchaseOrder): void
    {
        $items = $purchaseOrder->items;

        $status = match (true) {
            $items->every(fn (PurchaseOrderItem $item) => $item->received_qty >= $item->qty) => 'completed',
            $items->contains(fn (PurchaseOrderItem $item) => $item->received_qty > 0) => 'partially_received',
            default => 'open',
        };

        $purchaseOrder->update(['status' => $status]);
    }

    /**
     * Generate the next sequential Goods Receipt number for the current
     * year, e.g. GR-2026-00001.
     */
    public function generateGrNo(): string
    {
        $year = now()->year;
        $prefix = "GR-{$year}-";

        $lastGrNo = GoodsReceipt::where('gr_no', 'like', "{$prefix}%")
            ->orderByDesc('gr_no')
            ->value('gr_no');

        $nextSequence = 1;
        if ($lastGrNo) {
            $nextSequence = (int) substr($lastGrNo, strlen($prefix)) + 1;
        }

        return $prefix . str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
    }
}
