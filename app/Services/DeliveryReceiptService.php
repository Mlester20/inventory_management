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
        // withTrashed() here: a Sales Order line still needs to be delivered
        // even if its Generic Name has since been trashed — losing the label
        // shouldn't silently drop it from the "what's left to deliver" list.
        return $salesOrder->items()
            ->with(['genericName' => fn ($q) => $q->withTrashed(), 'product'])
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
            // Re-fetch under a row lock instead of trusting the route-bound
            // $draft's already-loaded is_draft flag: two near-simultaneous
            // "Post" submissions of the same draft (e.g. a double-click)
            // would otherwise both see is_draft=true before either commits,
            // and both deduct stock — silently, since the second finalize's
            // items()->delete() replaces the first's items, leaving no
            // duplicate record behind even though stock was deducted twice.
            $draft = DeliveryReceipt::lockForUpdate()->findOrFail($draft->id);

            if (! $draft->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'This Delivery Receipt has already been posted.',
                ]);
            }

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
        $hasAnyLine = collect($items)->contains(
            fn ($line) => ! empty($line['product_batch_id']) && ! empty($line['qty']) && (int) $line['qty'] > 0
        );
        if (! $hasAnyLine) {
            throw ValidationException::withMessages([
                'items' => 'Select a batch and enter a quantity for at least one item.',
            ]);
        }

        $warehouse = Location::warehouse();
        $affectedSalesOrders = [];

        foreach ($items as $line) {
            // A partial fulfillment intentionally leaves some pending Sales
            // Order lines blank (not yet available to deliver) — skip them
            // rather than treating a blank batch/qty as 0 units delivered.
            if (empty($line['product_batch_id']) || empty($line['qty']) || (int) $line['qty'] <= 0) {
                continue;
            }

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
    public function createInvoiceFromLines(DeliveryReceipt $deliveryReceipt, array $deliveryReceiptItemIds, ?int $userId = null, array $qtyOverrides = [], ?string $poNo = null, float $lessWt = 0, bool $personalUse = false, ?int $preparedBy = null): Invoice
    {
        return DB::transaction(function () use ($deliveryReceipt, $deliveryReceiptItemIds, $userId, $qtyOverrides, $poNo, $lessWt, $personalUse, $preparedBy) {
            $customer = $deliveryReceipt->customer;
            $activeVatRate = Taxes::activeRate();

            // lockForUpdate() closes the same race as the other fixes in
            // this file: two near-simultaneous "Create Invoice" clicks on
            // the same line(s) would otherwise both read the pre-increment
            // invoiced_qty under their own transaction snapshot and both
            // invoice the full remaining qty, double-billing the customer
            // for stock that was only delivered once.
            $lines = $deliveryReceipt->items()
                ->with(['productBatch.product.tax', 'salesOrderItem'])
                ->whereIn('id', $deliveryReceiptItemIds)
                ->lockForUpdate()
                ->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'line_ids' => 'Select at least one line to invoice.',
                ]);
            }

            $vatSales = 0;
            $vatexSales = 0;
            $zeroSales = 0;
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

                // Partial invoicing: an entered qty overrides the default
                // (invoice everything still remaining on this line), but
                // can never exceed what's actually left — a stale/tampered
                // value from the form is rejected here, not just capped by
                // the input's client-side max.
                $qty = $qtyOverrides[$line->id] ?? $line->remaining_invoiceable_qty;
                if ($qty <= 0 || $qty > $line->remaining_invoiceable_qty) {
                    throw ValidationException::withMessages([
                        'line_ids' => "Invalid quantity for {$product->item_name}: {$qty} (only {$line->remaining_invoiceable_qty} remaining to invoice).",
                    ]);
                }

                $price = $line->salesOrderItem
                    ? (float) $line->salesOrderItem->price
                    : (float) ($product->{$customer->priceColumn()} ?? $product->unit_price);

                $lineAmount = $qty * $price;
                $classification = $product->taxClassification();

                // Per Sir: Price is the VAT-inclusive amount the customer
                // actually pays, not a net-of-VAT base price — so a VATable
                // line's own amount already has VAT inside it. VAT is
                // extracted back out of it here, not added on top (adding on
                // top double-charges VAT — same fix as InvoiceController::
                // store() for a direct Invoice).
                $lineVat = 0;
                if ($classification === 'vatable') {
                    $lineNet = round($lineAmount / (1 + $activeVatRate / 100), 2);
                    $lineVat = round($lineAmount - $lineNet, 2);
                    $vatSales += $lineNet;
                    $vatAmount += $lineVat;
                } elseif ($classification === 'zero') {
                    $zeroSales += $lineAmount;
                } else {
                    $vatexSales += $lineAmount;
                }

                $saleRows[] = [
                    'delivery_receipt_item_id' => $line->id,
                    'product_batch_id' => $line->product_batch_id,
                    'desc' => $product->display_description,
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

            $totalSales = $vatSales + $vatexSales + $zeroSales + $vatAmount;

            // Withholding tax is typed in by hand (Sir: an editable field, so a
            // correction can always be encoded) — it's subtracted from what the
            // customer owes, and can't exceed the invoice total.
            if ($lessWt > round($totalSales, 2)) {
                throw ValidationException::withMessages([
                    'less_wt' => 'Withholding Tax cannot be more than the invoice total (' . number_format($totalSales, 2) . ').',
                ]);
            }

            // Personal-use medicine is not covered by withholding tax (Sir).
            if ($personalUse && $lessWt > 0) {
                throw ValidationException::withMessages([
                    'less_wt' => 'A personal-use invoice has no withholding tax. Set it to 0, or untick Personal use.',
                ]);
            }

            $invoice = Invoice::create([
                'customer_name' => $customer->customer_name,
                'customer_id' => $customer->id,
                'po_no' => $poNo,
                'sales_no' => $this->generateSalesNo(),
                'prepared_by' => $preparedBy ?? $deliveryReceipt->prepared_by ?? $userId,
                'vat_sales' => round($vatSales, 2),
                'vatex_sales' => round($vatexSales, 2),
                'zero_sales' => round($zeroSales, 2),
                'vat_amount' => round($vatAmount, 2),
                'total_sales' => round($totalSales, 2),
                'less_vat' => 0,
                'amount_net' => round($totalSales, 2),
                'less_sc' => 0,
                'less_wt' => round($lessWt, 2),
                'is_personal_use' => $personalUse,
                'amount_due' => round($totalSales - $lessWt, 2),
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
        return Invoice::nextSalesNo();
    }

    /**
     * Correct a posted Delivery Receipt that was saved before the goods were actually picked
     * (an item turned out to be missing, or short): lower a line's quantity, or take the line off
     * (new qty 0). What comes off goes straight back to the Warehouse batch it was deducted from,
     * and — where the line fulfils a Sales Order line — the Sales Order's delivered qty goes down by
     * the same amount, so the order is open for the shortfall again.
     *
     * Only a receipt that is still For Delivery can be revised (anything already delivered is a
     * Return Item), a line can never go below what is already invoiced, and the receipt must keep at
     * least one line (use Cancel/Void for the whole thing).
     *
     * @param array<int,int|string|null> $newQtys DR item id => new qty (blank = unchanged)
     * @return array<int,array{item:string,batch:?string,from:int,to:int}> the lines that changed
     */
    public function reviseDeliveryReceipt(DeliveryReceipt $deliveryReceipt, array $newQtys, ?int $userId = null): array
    {
        return DB::transaction(function () use ($deliveryReceipt, $newQtys, $userId) {
            $dr = DeliveryReceipt::lockForUpdate()->findOrFail($deliveryReceipt->id);

            if ($dr->isDraft() || $dr->isCancelled()) {
                throw ValidationException::withMessages(['revise' => 'Only a posted Delivery Receipt can be revised.']);
            }

            if ($dr->status !== 'for_delivery') {
                throw ValidationException::withMessages(['revise' => 'Only a Delivery Receipt that is still For Delivery can be revised. Goods already delivered go through Return Items.']);
            }

            $lines = $dr->items()->with('productBatch.product')->lockForUpdate()->get();
            $warehouse = Location::warehouse();
            $changes = [];
            $salesOrderDecrements = [];
            $keepsALine = false;

            foreach ($lines as $line) {
                $raw = $newQtys[$line->id] ?? null;
                $new = ($raw === null || $raw === '') ? (int) $line->qty : (int) $raw;
                $name = $line->productBatch->product->display_description;

                if ($new < 0 || $new > $line->qty) {
                    throw ValidationException::withMessages(['revise' => "{$name}: the new quantity must be between 0 and {$line->qty}."]);
                }

                if ($new < $line->invoiced_qty) {
                    throw ValidationException::withMessages(['revise' => "{$name}: {$line->invoiced_qty} is already invoiced, so it can't go below that."]);
                }

                if ($new > 0) {
                    $keepsALine = true;
                }

                if ($new === (int) $line->qty) {
                    continue;
                }

                $removed = (int) $line->qty - $new;
                $changes[] = ['item' => $name, 'batch' => $line->batch_no, 'from' => (int) $line->qty, 'to' => $new];

                $this->stockService->restock($line->productBatch, $removed, $warehouse, "Delivery Receipt {$dr->dr_no} revised", $userId, $dr);

                if ($line->sales_order_item_id) {
                    $salesOrderDecrements[$line->sales_order_item_id] = ($salesOrderDecrements[$line->sales_order_item_id] ?? 0) + $removed;
                }

                if ($new === 0) {
                    $line->delete();
                } else {
                    $line->update(['qty' => $new]);
                }
            }

            if (empty($changes)) {
                throw ValidationException::withMessages(['revise' => 'Nothing to revise — change at least one quantity.']);
            }

            if (! $keepsALine) {
                throw ValidationException::withMessages(['revise' => 'A Delivery Receipt has to keep at least one line. To void the whole receipt, use Cancel/Void.']);
            }

            $affectedOrders = [];
            foreach ($salesOrderDecrements as $soItemId => $removed) {
                $soItem = SalesOrderItem::lockForUpdate()->find($soItemId);
                if ($soItem) {
                    $soItem->update(['delivered_qty' => max(0, (int) $soItem->delivered_qty - $removed)]);
                    $affectedOrders[$soItem->sales_order_id] = true;
                }
            }
            foreach (array_keys($affectedOrders) as $salesOrderId) {
                $this->refreshSalesOrderStatus(SalesOrder::findOrFail($salesOrderId));
            }

            return $changes;
        });
    }

    /**
     * Attach an already-posted Advance Order Delivery Receipt to a Sales Order
     * of the same customer — the DR was created without one (e.g. as a workaround
     * when a Sales Order line couldn't be split across batches). Stock was already
     * deducted when the DR was posted, so this moves no stock; it only records
     * which Sales Order line each DR line fulfils, raises those lines'
     * delivered_qty, and refreshes the Sales Order's status. The DR becomes a
     * Purchase Order DR, so invoicing it afterwards uses the Sales Order's price.
     *
     * Not every line has to be linked, and a line can be linked for less than its
     * full qty (e.g. 5 of 20 pcs belong to the order). When anything is left
     * over, the linked part moves to a NEW Purchase Order DR (same date, same
     * batches, so stock and the total delivered are unchanged) and the rest stays
     * on this Advance Order DR, still free to be linked to another order. When
     * everything is linked, this DR itself simply becomes the Purchase Order DR.
     * Returns whichever DR now carries the link.
     *
     * @param array<int,int|string|null> $lineMap DR item id => Sales Order item id (blank = leave unlinked)
     * @param array<int,int|string|null> $qtyMap  DR item id => qty to link (blank = the line's full qty)
     */
    public function linkToSalesOrder(DeliveryReceipt $deliveryReceipt, SalesOrder $salesOrder, array $lineMap, ?int $userId = null, array $qtyMap = []): DeliveryReceipt
    {
        return DB::transaction(function () use ($deliveryReceipt, $salesOrder, $lineMap, $qtyMap) {
            $deliveryReceipt = DeliveryReceipt::lockForUpdate()->findOrFail($deliveryReceipt->id);

            if ($deliveryReceipt->isDraft() || $deliveryReceipt->isCancelled()
                || $deliveryReceipt->transaction_type !== 'advance_order' || $deliveryReceipt->sales_order_id) {
                throw ValidationException::withMessages(['link' => 'Only a posted Advance Order Delivery Receipt with no Sales Order can be linked.']);
            }

            if ($salesOrder->isDraft() || $salesOrder->isArchived() || (int) $salesOrder->customer_id !== (int) $deliveryReceipt->customer_id) {
                throw ValidationException::withMessages(['link' => 'Pick an active Sales Order of the same customer as this Delivery Receipt.']);
            }

            $salesOrderItems = $salesOrder->items()->lockForUpdate()->get()->keyBy('id');
            $lines = $deliveryReceipt->items()->with('productBatch.product')->lockForUpdate()->get();
            $linkedQty = [];
            $links = [];

            foreach ($lines as $line) {
                $soItemId = (int) ($lineMap[$line->id] ?? 0);
                if (! $soItemId) {
                    continue;
                }

                $name = $line->productBatch->product->item_name;
                $soItem = $salesOrderItems->get($soItemId);
                if (! $soItem || (int) $soItem->generic_name_id !== (int) $line->productBatch->product->generic_name_id) {
                    throw ValidationException::withMessages(['link' => "{$name} can only be linked to a Sales Order line of the same generic item."]);
                }

                $qty = (int) ($qtyMap[$line->id] ?? 0) ?: (int) $line->qty;
                if ($qty < 1 || $qty > $line->qty) {
                    throw ValidationException::withMessages(['link' => "Qty to link for {$name} must be between 1 and {$line->qty}."]);
                }

                // The part of a line that is already invoiced has to stay with the
                // untouched remainder — an invoice already points at this line.
                if ($qty < $line->qty && $line->qty - $qty < $line->invoiced_qty) {
                    $max = $line->qty - $line->invoiced_qty;
                    throw ValidationException::withMessages(['link' => "{$name} already has {$line->invoiced_qty} invoiced, so at most {$max} can be linked separately."]);
                }

                $linkedQty[$soItemId] = ($linkedQty[$soItemId] ?? 0) + $qty;
                if ($linkedQty[$soItemId] > $soItem->remaining_qty) {
                    throw ValidationException::withMessages(['link' => "Linked qty for {$name} ({$linkedQty[$soItemId]}) exceeds the remaining balance on the Sales Order ({$soItem->remaining_qty})."]);
                }

                $links[$line->id] = [$soItemId, $qty];
            }

            if (empty($links)) {
                throw ValidationException::withMessages(['link' => 'Link at least one Delivery Receipt line to a Sales Order line.']);
            }

            $hasLeftover = $lines->contains(fn ($line) => ! isset($links[$line->id]) || $links[$line->id][1] < $line->qty);

            $target = $deliveryReceipt;
            if ($hasLeftover) {
                $target = DeliveryReceipt::create([
                    'customer_id' => $deliveryReceipt->customer_id,
                    'sales_order_id' => $salesOrder->id,
                    'split_from_delivery_receipt_id' => $deliveryReceipt->id,
                    'dr_no' => $this->generateDrNo(),
                    'transaction_type' => 'purchase_order',
                    'status' => $deliveryReceipt->status,
                    'is_draft' => false,
                    'description' => $deliveryReceipt->description,
                    'receipt_date' => $deliveryReceipt->receipt_date,
                    'prepared_by' => $deliveryReceipt->prepared_by,
                ]);
            }

            foreach ($links as $lineId => [$soItemId, $qty]) {
                $line = $lines->firstWhere('id', $lineId);

                if ($qty < $line->qty) {
                    $linkedPart = $line->replicate();
                    $linkedPart->delivery_receipt_id = $target->id;
                    $linkedPart->qty = $qty;
                    $linkedPart->invoiced_qty = 0;
                    $linkedPart->sales_order_item_id = $soItemId;
                    $linkedPart->save();
                    $line->update(['qty' => $line->qty - $qty]);
                } else {
                    $line->update(['delivery_receipt_id' => $target->id, 'sales_order_item_id' => $soItemId]);
                }
            }
            foreach ($linkedQty as $soItemId => $qty) {
                $salesOrderItems->get($soItemId)->increment('delivered_qty', $qty);
            }

            if (! $hasLeftover) {
                $deliveryReceipt->update(['sales_order_id' => $salesOrder->id, 'transaction_type' => 'purchase_order']);
            }
            $this->refreshSalesOrderStatus($salesOrder->fresh());

            return $target;
        });
    }

    /**
     * Recompute a Sales Order's status from its line items' delivered quantities.
     */
    protected function refreshSalesOrderStatus(SalesOrder $salesOrder): void
    {
        $items = $salesOrder->items;

        $status = match (true) {
            $items->every(fn (SalesOrderItem $item) => $item->remaining_qty <= 0) => 'completed',
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

        // lockForUpdate() blocks a concurrent caller until this transaction
        // commits, preventing two requests from generating the same number.
        // withTrashed() is required: DeliveryReceipt is soft-deletable but
        // dr_no stays unique at the DB level even for trashed rows.
        $lastDrNo = DeliveryReceipt::withTrashed()
            ->where('dr_no', 'like', "{$prefix}%")
            ->orderByDesc('dr_no')
            ->lockForUpdate()
            ->value('dr_no');

        $nextSequence = 1;
        if ($lastDrNo) {
            $nextSequence = (int) substr($lastDrNo, strlen($prefix)) + 1;
        }

        return $prefix . str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
    }
}
