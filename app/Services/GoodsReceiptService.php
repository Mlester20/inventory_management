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
            ->with('product.batches')
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
            $grNo = $this->generateGrNo();

            $goodsReceipt = GoodsReceipt::create([
                'supplier_id' => $data['supplier_id'],
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'gr_no' => $grNo,
                'receipt_date' => $data['receipt_date'],
                'prepared_by' => $data['prepared_by'] ?? null,
            ]);

            $affectedPurchaseOrders = [];

            foreach ($data['items'] as $line) {
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

                $this->stockService->restock($batch, $qty, Location::warehouse(), "Goods Receipt {$grNo}", $userId, $goodsReceipt);

                $product->update(['unit_cost' => $line['unit_cost']]);

                $goodsReceipt->items()->create([
                    'purchase_order_item_id' => $purchaseOrderItem?->id,
                    'product_batch_id' => $batch->id,
                    'qty' => $qty,
                    'unit_cost' => $line['unit_cost'],
                    'batch_no' => $batch->batch_no,
                    'expiration_date' => $batch->expiration_date,
                ]);

                if ($purchaseOrderItem) {
                    $purchaseOrderItem->increment('received_qty', $qty);
                    $affectedPurchaseOrders[$purchaseOrderItem->purchase_order_id] = true;
                }
            }

            foreach (array_keys($affectedPurchaseOrders) as $purchaseOrderId) {
                $this->refreshPurchaseOrderStatus(PurchaseOrder::findOrFail($purchaseOrderId));
            }

            return $goodsReceipt;
        });
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
