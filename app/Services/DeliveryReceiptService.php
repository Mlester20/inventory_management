<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\DeliveryReceipt;
use App\Models\DeliveryReceiptItem;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\ProductBatch;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Taxes;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryReceiptService
{
    public function __construct(
        protected StockService $stockService,
        protected CustomerPaymentService $customerPaymentService,
    ) {}

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
            $deliveryReceipt = DeliveryReceipt::create([
                'customer_id' => $data['customer_id'],
                'sales_order_id' => $data['sales_order_id'] ?? null,
                'dr_no' => $this->generateDrNo(),
                'transaction_type' => $data['transaction_type'],
                'description' => $data['description'] ?? null,
                'receipt_date' => $data['receipt_date'],
                'prepared_by' => $data['prepared_by'] ?? null,
                'is_draft' => false,
            ]);

            $this->applyItems($deliveryReceipt, $data['items'], $userId);

            return $deliveryReceipt;
        });
    }

    /**
     * Save (or re-save) a draft — no stock movement, no Sales Order balance
     * changes, so the encoder can leave customer/items blank/incomplete and
     * resume later. Only the header and items are persisted; nothing here
     * ever touches location_stocks or delivered_qty.
     *
     * @param array $data ['customer_id', 'sales_order_id', 'transaction_type', 'receipt_date', 'prepared_by',
     *                     'items' => [['product_batch_id','qty','sales_order_item_id'], ...]]
     */
    public function saveDraft(array $data, ?int $userId = null, ?DeliveryReceipt $existing = null): DeliveryReceipt
    {
        return DB::transaction(function () use ($data, $userId, $existing) {
            $deliveryReceipt = $existing ?? new DeliveryReceipt([
                'dr_no' => $this->generateDrNo(),
            ]);

            $deliveryReceipt->fill([
                'customer_id' => $data['customer_id'] ?? null,
                'sales_order_id' => $data['sales_order_id'] ?? null,
                'transaction_type' => $data['transaction_type'] ?? null,
                'description' => $data['description'] ?? null,
                'receipt_date' => $data['receipt_date'] ?? now()->toDateString(),
                'prepared_by' => $data['prepared_by'] ?? $userId,
                'is_draft' => true,
            ]);
            $deliveryReceipt->save();

            // Replace whatever items existed before — safe, since a draft's
            // items have never been read by anything that moves stock.
            $deliveryReceipt->items()->delete();
            foreach ($data['items'] ?? [] as $line) {
                if (empty($line['product_batch_id'])) {
                    continue;
                }

                $batch = ProductBatch::find($line['product_batch_id']);

                $deliveryReceipt->items()->create([
                    'sales_order_item_id' => $line['sales_order_item_id'] ?? null,
                    'product_batch_id' => $line['product_batch_id'],
                    'qty' => $line['qty'] ?? null,
                    'remarks' => $line['remarks'] ?? null,
                    'batch_no' => $batch?->batch_no,
                    'expiration_date' => $batch?->expiration_date,
                ]);
            }

            return $deliveryReceipt;
        });
    }

    /**
     * Turn a draft into a real, posted Delivery Receipt — the one moment
     * its stock (and any linked Sales Order balances) actually move.
     * Replaces the draft's items with the final values, then runs the same
     * stock-moving logic createDeliveryReceipt() uses.
     */
    public function finalizeDraft(DeliveryReceipt $draft, array $data, ?int $userId = null): DeliveryReceipt
    {
        return DB::transaction(function () use ($draft, $data, $userId) {
            $draft->fill([
                'customer_id' => $data['customer_id'],
                'sales_order_id' => $data['sales_order_id'] ?? null,
                'transaction_type' => $data['transaction_type'],
                'description' => $data['description'] ?? null,
                'receipt_date' => $data['receipt_date'],
                'prepared_by' => $data['prepared_by'] ?? null,
                'is_draft' => false,
            ]);
            $draft->save();

            $draft->items()->delete();
            $this->applyItems($draft, $data['items'], $userId);

            return $draft;
        });
    }

    /**
     * Deduct stock for each line's batch and update any linked Sales Order
     * line balances — shared by createDeliveryReceipt() and finalizeDraft()
     * so this logic (especially the SO delivered_qty increment) exists in
     * exactly one place and never runs for a draft.
     */
    protected function applyItems(DeliveryReceipt $deliveryReceipt, array $items, ?int $userId): void
    {
        $warehouse = Location::warehouse();
        $affectedSalesOrders = [];

        foreach ($items as $line) {
            $batch = ProductBatch::with('product')->findOrFail($line['product_batch_id']);
            $qty = (int) $line['qty'];
            $availableAtWarehouse = $batch->qtyAtLocation($warehouse->id);

            if ($availableAtWarehouse < $qty) {
                throw ValidationException::withMessages([
                    'items' => "Insufficient stock for {$batch->product->item_name}. Available: {$availableAtWarehouse}",
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

            $this->stockService->deduct($batch, $qty, $warehouse, "Delivery Receipt {$deliveryReceipt->dr_no}", $userId, $deliveryReceipt);

            $deliveryReceipt->items()->create([
                'sales_order_item_id' => $salesOrderItem?->id,
                'product_batch_id' => $batch->id,
                'qty' => $qty,
                'remarks' => $line['remarks'] ?? null,
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
    }

    /**
     * Create a Sales Invoice covering the given (fully-delivered, not-yet-
     * fully-invoiced) lines of a Delivery Receipt, per the mockup's
     * checkbox-select "Create Invoice" action. Each checked line is
     * invoiced for its full remaining (uninvoiced) qty in one shot — this
     * does not touch stock, since it was already deducted when the
     * Delivery Receipt itself was created.
     *
     * Price is resolved from the linked Sales Order line's agreed price
     * when available (transaction_type = purchase_order); otherwise from
     * the product's price tier matching the customer's price_level, the
     * same resolution Sales Order already uses — a Delivery Receipt line
     * carries no price of its own.
     */
    public function createInvoiceFromLines(DeliveryReceipt $deliveryReceipt, array $deliveryReceiptItemIds, ?int $userId = null): Invoice
    {
        return DB::transaction(function () use ($deliveryReceipt, $deliveryReceiptItemIds, $userId) {
            $customer = $deliveryReceipt->customer;
            $activeVatRate = (float) (Taxes::where('is_active', true)->value('rate') ?? 0);

            $lines = $deliveryReceipt->items()
                ->with(['productBatch.product.tax', 'salesOrderItem'])
                ->whereIn('id', $deliveryReceiptItemIds)
                ->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'line_ids' => 'Select at least one line to invoice.',
                ]);
            }

            $vatSales = 0;
            $vatexSales = 0;
            $vatAmount = 0;
            $saleRows = [];

            foreach ($lines as $line) {
                /** @var DeliveryReceiptItem $line */
                if ($line->remaining_invoiceable_qty <= 0) {
                    throw ValidationException::withMessages([
                        'line_ids' => "{$line->productBatch->product->item_name} is already fully invoiced.",
                    ]);
                }

                $product = $line->productBatch->product;
                $qty = $line->remaining_invoiceable_qty;

                $price = $line->salesOrderItem
                    ? (float) $line->salesOrderItem->price
                    : (float) ($product->{$customer->priceColumn()} ?? $product->unit_price);

                $lineAmount = $qty * $price;
                $classification = $product->tax_id ? 'vatable' : 'vatex';

                $lineVat = 0;
                if ($classification === 'vatable') {
                    $vatSales += $lineAmount;
                    $lineVat = round($lineAmount * ($activeVatRate / 100), 2);
                    $vatAmount += $lineVat;
                } else {
                    $vatexSales += $lineAmount;
                }

                $saleRows[] = [
                    'delivery_receipt_item_id' => $line->id,
                    'product_batch_id' => $line->product_batch_id,
                    'desc' => $product->item_name,
                    'qty' => $qty,
                    'unit' => $product->genericName->unit ?? null,
                    'batch_no' => $line->batch_no,
                    'exp' => $line->expiration_date,
                    'price' => $price,
                    'vat' => $lineVat,
                    'dis' => 0,
                    'amount' => $lineAmount,
                ];

                $line->increment('invoiced_qty', $qty);
            }

            $totalSales = $vatSales + $vatexSales + $vatAmount;

            $invoice = Invoice::create([
                'customer_name' => $customer->customer_name,
                'customer_id' => $customer->id,
                'sales_no' => $this->generateSalesNo(),
                'prepared_by' => $userId,
                'vat_sales' => round($vatSales, 2),
                'vatex_sales' => round($vatexSales, 2),
                'zero_sales' => 0,
                'vat_amount' => round($vatAmount, 2),
                'total_sales' => round($totalSales, 2),
                'less_vat' => 0,
                'amount_net' => round($totalSales, 2),
                'less_sc' => 0,
                'less_wt' => 0,
                'amount_due' => round($totalSales, 2),
                'add_vat' => 0,
            ]);

            foreach ($saleRows as $row) {
                $invoice->sales()->create($row);
            }

            $this->customerPaymentService->applyAvailableCredit($invoice);

            return $invoice;
        });
    }

    /**
     * Generate the next sequential sales number for the current year,
     * e.g. INV-2026-00001, matching InvoiceController's own scheme.
     */
    protected function generateSalesNo(): string
    {
        $year = now()->year;
        $prefix = "INV-{$year}-";

        $lastSalesNo = Invoice::where('sales_no', 'like', "{$prefix}%")
            ->orderByDesc('sales_no')
            ->value('sales_no');

        $nextSequence = 1;
        if ($lastSalesNo) {
            $nextSequence = (int) substr($lastSalesNo, strlen($prefix)) + 1;
        }

        return $prefix . str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
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
