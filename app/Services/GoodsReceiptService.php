<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\Item;
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
            ->with('item')
            ->get()
            ->filter(fn (PurchaseOrderItem $item) => $item->remaining_qty > 0)
            ->values();
    }

    /**
     * Create a Goods Receipt with its line items, restocking each item
     * (via StockService), overwriting its cost/batch/expiry with the
     * received values, and updating any linked Purchase Order line balances.
     *
     * @param array $data ['supplier_id', 'purchase_order_id', 'receipt_date', 'prepared_by',
     *                     'items' => [['item_id','qty','unit_cost','batch_no','expiration_date','purchase_order_item_id'], ...]]
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
                $item = Item::findOrFail($line['item_id']);
                $qty = (int) $line['qty'];

                $purchaseOrderItem = null;
                if (! empty($line['purchase_order_item_id'])) {
                    $purchaseOrderItem = PurchaseOrderItem::findOrFail($line['purchase_order_item_id']);

                    if ($qty > $purchaseOrderItem->remaining_qty) {
                        throw ValidationException::withMessages([
                            'items' => "Received qty for {$item->item_name} exceeds the remaining balance on the Purchase Order ({$purchaseOrderItem->remaining_qty}).",
                        ]);
                    }
                }

                $this->stockService->restock($item, $qty, "Goods Receipt {$grNo}", $userId);

                $item->update([
                    'unit_cost' => $line['unit_cost'],
                    'batch_no' => $line['batch_no'] ?? null,
                    'expiration_date' => $line['expiration_date'] ?? null,
                ]);

                $goodsReceipt->items()->create([
                    'purchase_order_item_id' => $purchaseOrderItem?->id,
                    'item_id' => $item->id,
                    'qty' => $qty,
                    'unit_cost' => $line['unit_cost'],
                    'batch_no' => $line['batch_no'] ?? null,
                    'expiration_date' => $line['expiration_date'] ?? null,
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
