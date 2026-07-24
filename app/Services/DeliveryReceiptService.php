<?php

namespace App\Services;

use App\Models\DeliveryReceipt;
use App\Models\GenericName;
use App\Models\ProductBatch;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryReceiptService
{
    public function __construct(protected StockService $stockService) {}

    /**
     * In-stock batches (qty > 0), across every brand/product, under a given
     * Generic Name. Powers the "Available Product?" batch picker.
     */
    public function getAvailableItemsForGenericName(GenericName $genericName): Collection
    {
        return ProductBatch::query()
            ->where('qty', '>', 0)
            ->whereHas('product', fn ($q) => $q->where('generic_name_id', $genericName->id))
            ->with(['product.supplier'])
            ->get();
    }

    /**
     * Sales Order lines that still have a remaining (undelivered) balance.
     */
    public function getRemainingItems(SalesOrder $salesOrder): Collection
    {
        return $salesOrder->items()
            ->with('genericName')
            ->get()
            ->filter(fn (SalesOrderItem $item) => $item->remaining_qty > 0)
            ->values();
    }

    /**
     * Create a Delivery Receipt with its line items, deducting stock for
     * each delivered batch and updating any linked Sales Order line balances.
     *
     * @param array $data ['customer_id', 'sales_order_id', 'transaction_type', 'receipt_date', 'prepared_by',
     *                     'items' => [['product_batch_id','qty','sales_order_item_id'], ...]]
     */
    public function createDeliveryReceipt(array $data, ?int $userId = null): DeliveryReceipt
    {
        return DB::transaction(function () use ($data, $userId) {
            $drNo = $this->generateDrNo();

            $deliveryReceipt = DeliveryReceipt::create([
                'customer_id' => $data['customer_id'],
                'sales_order_id' => $data['sales_order_id'] ?? null,
                'dr_no' => $drNo,
                'transaction_type' => $data['transaction_type'],
                'receipt_date' => $data['receipt_date'],
                'prepared_by' => $data['prepared_by'] ?? null,
            ]);

            $affectedSalesOrders = [];

            foreach ($data['items'] as $line) {
                $batch = ProductBatch::with('product')->findOrFail($line['product_batch_id']);
                $qty = (int) $line['qty'];

                if ($batch->qty < $qty) {
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock for {$batch->product->item_name}. Available: {$batch->qty}",
                    ]);
                }

                $salesOrderItem = null;
                if (! empty($line['sales_order_item_id'])) {
                    $salesOrderItem = SalesOrderItem::findOrFail($line['sales_order_item_id']);

                    if ($qty > $salesOrderItem->remaining_qty) {
                        throw ValidationException::withMessages([
                            'items' => "Delivered qty for {$batch->product->item_name} exceeds the remaining balance on the Sales Order ({$salesOrderItem->remaining_qty}).",
                        ]);
                    }
                }

                $this->stockService->deduct($batch, $qty, "Delivery Receipt {$drNo}", $userId, $deliveryReceipt);

                $deliveryReceipt->items()->create([
                    'sales_order_item_id' => $salesOrderItem?->id,
                    'product_batch_id' => $batch->id,
                    'qty' => $qty,
                    'batch_no' => $batch->batch_no,
                    'expiration_date' => $batch->expiration_date,
                ]);

                if ($salesOrderItem) {
                    $salesOrderItem->increment('delivered_qty', $qty);
                    $affectedSalesOrders[$salesOrderItem->sales_order_id] = true;
                }
            }

            foreach (array_keys($affectedSalesOrders) as $salesOrderId) {
                $this->refreshSalesOrderStatus(SalesOrder::findOrFail($salesOrderId));
            }

            return $deliveryReceipt;
        });
    }

    /**
     * Recompute a Sales Order's status from its line items' delivered quantities.
     */
    protected function refreshSalesOrderStatus(SalesOrder $salesOrder): void
    {
        $items = $salesOrder->items;

        $status = match (true) {
            $items->every(fn (SalesOrderItem $item) => $item->delivered_qty >= $item->qty) => 'completed',
            $items->contains(fn (SalesOrderItem $item) => $item->delivered_qty > 0) => 'partially_delivered',
            default => 'open',
        };

        $salesOrder->update(['status' => $status]);
    }

    /**
     * Generate the next sequential Delivery Receipt number for the current year,
     * e.g. DR-2026-00001.
     */
    public function generateDrNo(): string
    {
        $year = now()->year;
        $prefix = "DR-{$year}-";

        $lastDrNo = DeliveryReceipt::where('dr_no', 'like', "{$prefix}%")
            ->orderByDesc('dr_no')
            ->value('dr_no');

        $nextSequence = 1;
        if ($lastDrNo) {
            $nextSequence = (int) substr($lastDrNo, strlen($prefix)) + 1;
        }

        return $prefix . str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
    }
}
