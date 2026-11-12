<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\Taxes;
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
        // A closed order is no longer expecting anything.
        if ($purchaseOrder->status === 'closed') {
            return new Collection();
        }

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
                'supplier_id' => $data['supplier_id'] ?? null,
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
     * Create a posted Purchase Invoice from selected Goods Receipt lines.
     *
     * @param array<int,int|string> $lineIds
     * @param array<int|string,int|string> $qtyToInvoice keyed by Goods Receipt item id
     */
    public function createPurchaseInvoiceFromLines(
        GoodsReceipt $goodsReceipt,
        array $lineIds,
        array $qtyToInvoice,
        ?int $userId = null
    ): PurchaseInvoice {
        return DB::transaction(function () use ($goodsReceipt, $lineIds, $qtyToInvoice, $userId) {
            $gr = GoodsReceipt::lockForUpdate()->findOrFail($goodsReceipt->id);
            if ($gr->isDraft()) {
                throw ValidationException::withMessages([
                    'line_ids' => 'A draft Goods Receipt cannot be invoiced.',
                ]);
            }

            $selectedIds = array_map('intval', $lineIds);
            if ($selectedIds === [] || count($selectedIds) !== count(array_unique($selectedIds))) {
                throw ValidationException::withMessages([
                    'line_ids' => 'Select one or more distinct Goods Receipt lines to invoice.',
                ]);
            }

            $lines = GoodsReceiptItem::query()
                ->where('goods_receipt_id', $gr->id)
                ->whereIn('id', $selectedIds)
                ->with(['supplier', 'purchaseInvoiceItems', 'productBatch.product', 'product'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($lines->count() !== count($selectedIds)) {
                throw ValidationException::withMessages([
                    'line_ids' => 'One or more selected lines do not belong to this Goods Receipt.',
                ]);
            }

            $supplierIds = [];
            $invoiceLines = [];
            $amount = 0;
            $vatAmount = 0;
            $vatRate = Taxes::activeRate();

            foreach ($selectedIds as $lineId) {
                /** @var GoodsReceiptItem $line */
                $line = $lines->get($lineId);
                $qty = (int) ($qtyToInvoice[$lineId] ?? 0);
                $remainingQty = (int) $line->qty - (int) $line->invoiced_qty;

                if ($qty <= 0 || $qty > $remainingQty) {
                    throw ValidationException::withMessages([
                        'qty' => "Invalid invoice quantity for line {$lineId}: {$qty} (only {$remainingQty} remaining to invoice).",
                    ]);
                }

                if (! $line->supplier_not_determined) {
                    if ($line->supplier_id === null) {
                        throw ValidationException::withMessages([
                            'line_ids' => "Assign a supplier or mark it Supplier Not Determined for line {$lineId}.",
                        ]);
                    }

                    $supplierIds[] = (int) $line->supplier_id;
                }

                $product = $line->product ?? $line->productBatch?->product;
                if (! $product) {
                    throw ValidationException::withMessages([
                        'line_ids' => "Product information is missing for Goods Receipt line {$lineId}.",
                    ]);
                }

                $lineAmount = $qty * (float) $line->unit_cost;
                $amount += $lineAmount;

                if ($product->taxClassification() === 'vatable') {
                    $lineNet = round($lineAmount / (1 + $vatRate / 100), 2);
                    $vatAmount += round($lineAmount - $lineNet, 2);
                }

                $invoiceLines[] = ['line' => $line, 'qty' => $qty];
            }

            $supplierIds = array_values(array_unique($supplierIds));
            if (count($supplierIds) > 1) {
                throw ValidationException::withMessages([
                    'line_ids' => 'Selected lines must have the same supplier. Lines marked Supplier Not Determined may be included.',
                ]);
            }

            $purchaseInvoice = PurchaseInvoice::create([
                'supplier_id' => $supplierIds[0] ?? null,
                'goods_receipt_id' => $gr->id,
                'invoice_date' => now()->toDateString(),
                'amount' => round($amount, 2),
                'vat_amount' => round($vatAmount, 2),
                'prepared_by' => $userId,
                'status' => 'draft',
            ]);

            foreach ($invoiceLines as ['line' => $line, 'qty' => $qty]) {
                PurchaseInvoiceItem::create([
                    'purchase_invoice_id' => $purchaseInvoice->id,
                    'goods_receipt_item_id' => $line->id,
                    'qty' => $qty,
                ]);
                $line->increment('invoiced_qty', $qty);
            }

            return $purchaseInvoice;
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
                    'supplier_id' => ! empty($line['supplier_not_determined']) ? null : ($line['supplier_id'] ?? null),
                    'supplier_not_determined' => (bool) ($line['supplier_not_determined'] ?? false),
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
            // Re-fetch under a row lock instead of trusting the route-bound
            // $draft's already-loaded status: two near-simultaneous "Post"
            // submissions of the same draft (e.g. a double-click) would
            // otherwise both see status="draft" before either commits, and
            // both restock — silently, since the second finalize's
            // items()->delete() replaces the first's items, leaving no
            // duplicate record behind even though stock was added twice.
            $draft = GoodsReceipt::lockForUpdate()->findOrFail($draft->id);

            if (! $draft->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'This Goods Receipt has already been posted.',
                ]);
            }

            $draft->fill([
                'supplier_id' => $data['supplier_id'] ?? null,
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
                $purchaseOrderItem = PurchaseOrderItem::with('purchaseOrder')->findOrFail($line['purchase_order_item_id']);

                if ($purchaseOrderItem->purchaseOrder->status === 'closed') {
                    throw ValidationException::withMessages([
                        'items' => "Purchase Order {$purchaseOrderItem->purchaseOrder->po_no} is closed — reopen it before receiving against it.",
                    ]);
                }

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
                'supplier_id' => ! empty($line['supplier_not_determined']) ? null : ($line['supplier_id'] ?? null),
                'supplier_not_determined' => (bool) ($line['supplier_not_determined'] ?? false),
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
        return $product->findOrCreateBatch($line['batch_no'] ?? null, $line['expiration_date'] ?? null);
    }

    /**
     * Recompute a Purchase Order's status from its line items' received quantities.
     */
    protected function refreshPurchaseOrderStatus(PurchaseOrder $purchaseOrder): void
    {
        // Closing is a deliberate decision, not derived from quantities — never overwrite it.
        if ($purchaseOrder->status === 'closed') {
            return;
        }

        $items = $purchaseOrder->items;

        $status = match (true) {
            $items->every(fn (PurchaseOrderItem $item) => $item->received_qty >= $item->qty) => 'completed',
            $items->contains(fn (PurchaseOrderItem $item) => $item->received_qty > 0) => 'partially_received',
            default => 'open',
        };

        $purchaseOrder->update(['status' => $status]);
    }

    /**
     * Correct a posted Goods Receipt: quantity, unit cost, lot/batch, expiry, remarks on each line, and the date,
     * Prepared By. Supplier is stored per receipt line. The Warehouse stock follows what changed, and every move
     * is a Stock Movement of this receipt, so Product History shows it.
     *
     * - A quantity change adds or takes the difference. Taking stock out is refused when the Warehouse no longer has
     *   it (it was delivered, sold, transferred or adjusted since), so stock can never go negative.
     * - A different lot moves the whole line to that lot (same refusal if the old lot's stock is no longer there).
     *   The same lot with a different expiry corrects that lot's expiry.
     * - On a line against a Purchase Order the order's received quantity follows, and a raise cannot pass what the
     *   order still expects.
     * - Lines cannot be added or removed, and the Purchase Order of a receipt cannot be changed.
     *
     * @param array{receipt_date?:mixed,prepared_by?:mixed} $header
     * @param array<int,array{qty?:mixed,unit_cost?:mixed,batch_no?:mixed,expiration_date?:mixed,remarks?:mixed,supplier_id?:mixed,supplier_not_determined?:mixed}> $lines goods receipt item id => new values
     * @return array<int,string> what changed, one sentence each
     */
    public function reviseGoodsReceipt(GoodsReceipt $goodsReceipt, array $header, array $lines, ?int $userId = null): array
    {
        return DB::transaction(function () use ($goodsReceipt, $header, $lines, $userId) {
            $gr = GoodsReceipt::lockForUpdate()->findOrFail($goodsReceipt->id);

            if ($gr->isDraft()) {
                throw ValidationException::withMessages(['revise' => 'A draft is edited with Continue Editing, not Revise.']);
            }

            $warehouse = Location::warehouse();
            $changes = [];
            $affectedOrders = [];
            $hasPurchaseInvoice = $gr->purchaseInvoices()->exists();
            foreach ($gr->items()->with(['product', 'productBatch', 'purchaseOrderItem.purchaseOrder'])->orderBy('id')->lockForUpdate()->get() as $item) {
                if (! array_key_exists($item->id, $lines)) {
                    continue;
                }

                $in = $lines[$item->id];
                $name = $item->product?->display_description ?? $item->productBatch?->product?->display_description ?? 'item';
                $oldBatch = $item->productBatch;
                $oldQty = (int) $item->qty;
                $newQty = isset($in['qty']) && $in['qty'] !== '' ? (int) $in['qty'] : $oldQty;
                $newCost = isset($in['unit_cost']) && $in['unit_cost'] !== '' ? round((float) $in['unit_cost'], 2) : $item->unit_cost;
                $newLot = array_key_exists('batch_no', $in) ? trim((string) $in['batch_no']) : trim((string) $item->batch_no);
                $newExpiry = array_key_exists('expiration_date', $in) ? ($in['expiration_date'] ?: null) : $item->expiration_date?->toDateString();
                $newRemarks = array_key_exists('remarks', $in) ? ($in['remarks'] !== '' ? $in['remarks'] : null) : $item->remarks;
                $newSupplierId = array_key_exists('supplier_id', $in) && $in['supplier_id'] !== ''
                    ? (int) $in['supplier_id']
                    : null;
                $oldSupplierId = $item->supplier_id !== null ? (int) $item->supplier_id : null;
                $newSupplierNotDetermined = (bool) ($in['supplier_not_determined'] ?? false);
                $oldSupplierNotDetermined = (bool) $item->supplier_not_determined;
                if ($newSupplierNotDetermined) {
                    $newSupplierId = null;
                }
                $note = "Goods Receipt {$gr->gr_no} edited";
                $lineChanges = [];

                if ($newSupplierId !== $oldSupplierId && $hasPurchaseInvoice) {
                    throw ValidationException::withMessages([
                        'revise' => 'A line supplier cannot be changed because a Purchase Invoice is linked to this Goods Receipt.',
                    ]);
                }

                if ($newQty < 1) {
                    throw ValidationException::withMessages(['revise' => "{$name}: the quantity must be at least 1. A line cannot be removed from a posted receipt."]);
                }

                $sameLot = $oldBatch && $newLot === trim((string) $oldBatch->batch_no);
                $newBatch = $sameLot ? $oldBatch : $item->product->findOrCreateBatch($newLot, $newExpiry);
                $qtyChanged = $newQty !== $oldQty;
                $lotChanged = $newBatch->id !== $oldBatch?->id;
                $currentExpiry = $oldBatch?->expiration_date?->toDateString();
                $expiryChanged = $newExpiry !== $currentExpiry;
                $costChanged = round((float) $newCost, 2) !== round((float) $item->unit_cost, 2);

                if (($qtyChanged || $lotChanged || $expiryChanged) && $oldBatch) {
                    $this->guardNoLaterStockMovement($gr, $oldBatch);
                }

                if ($costChanged) {
                    if ($hasPurchaseInvoice) {
                        throw ValidationException::withMessages([
                            'revise' => 'Unit cost cannot be changed because a Purchase Invoice is linked to this Goods Receipt.',
                        ]);
                    }

                    if ($oldBatch) {
                        $this->guardNoLaterStockMovement($gr, $oldBatch, 'Unit cost cannot be changed because this batch has later stock movements.');
                    }
                }

                if ($newBatch->id === $oldBatch?->id) {
                    if ($newExpiry !== $currentExpiry) {
                        $oldBatch->update(['expiration_date' => $newExpiry]);
                        $lineChanges[] = 'expiry ' . ($currentExpiry ?? '—') . ' → ' . ($newExpiry ?? '—');
                    }

                    $delta = $newQty - $oldQty;
                    if ($delta > 0) {
                        $this->stockService->restock($newBatch, $delta, $warehouse, "{$note} (qty {$oldQty} → {$newQty})", $userId, $gr);
                    } elseif ($delta < 0) {
                        $this->guardWarehouseHas($newBatch, -$delta, $warehouse, $name);
                        $this->stockService->deduct($newBatch, -$delta, $warehouse, "{$note} (qty {$oldQty} → {$newQty})", $userId, $gr);
                    }
                } else {
                    $this->guardWarehouseHas($oldBatch, $oldQty, $warehouse, $name);
                    $this->stockService->deduct($oldBatch, $oldQty, $warehouse, "{$note} (lot " . ($oldBatch->batch_no ?: '—') . ' → ' . ($newBatch->batch_no ?: '—') . ')', $userId, $gr);
                    $this->stockService->restock($newBatch, $newQty, $warehouse, "{$note} (lot " . ($oldBatch->batch_no ?: '—') . ' → ' . ($newBatch->batch_no ?: '—') . ')', $userId, $gr);
                    $lineChanges[] = 'lot ' . ($oldBatch->batch_no ?: '—') . ' → ' . ($newBatch->batch_no ?: '—');
                }

                if ($qtyChanged) {
                    $lineChanges[] = "qty {$oldQty} → {$newQty}";

                    if ($item->purchaseOrderItem) {
                        $this->moveReceivedQty($item->purchaseOrderItem, $newQty - $oldQty, $name);
                        $affectedOrders[$item->purchaseOrderItem->purchase_order_id] = true;
                    }
                }

                if ($costChanged) {
                    $lineChanges[] = 'cost ' . number_format((float) $item->unit_cost, 2) . ' → ' . number_format((float) $newCost, 2);

                    // The product's cost is the latest receipt's; correcting an older receipt must not overwrite it.
                    $latestId = $item->product ? \App\Models\GoodsReceiptItem::where('product_id', $item->product_id)->max('id') : null;
                    if ($latestId === $item->id) {
                        $item->product->update(['unit_cost' => $newCost]);
                    }
                }

                if ($newRemarks !== $item->remarks) {
                    $lineChanges[] = 'remarks';
                }
                if ($newSupplierId !== $oldSupplierId) {
                    $lineChanges[] = 'supplier changed';
                }
                if ($newSupplierNotDetermined !== $oldSupplierNotDetermined) {
                    $lineChanges[] = 'supplier determination changed';
                }

                $item->update([
                    'supplier_id' => $newSupplierId,
                    'supplier_not_determined' => $newSupplierNotDetermined,
                    'product_batch_id' => $newBatch->id,
                    'qty' => $newQty,
                    'unit_cost' => $newCost,
                    'batch_no' => $newBatch->batch_no,
                    'expiration_date' => $newBatch->expiration_date,
                    'remarks' => $newRemarks,
                ]);

                if ($lineChanges) {
                    $changes[] = "{$name}: " . implode(', ', $lineChanges);
                }
            }

            $headerUpdate = [];
            if (! empty($header['receipt_date']) && $gr->receipt_date?->toDateString() !== \Illuminate\Support\Carbon::parse($header['receipt_date'])->toDateString()) {
                $headerUpdate['receipt_date'] = $header['receipt_date'];
                $changes[] = 'date ' . ($gr->receipt_date?->toDateString() ?? '—') . ' → ' . \Illuminate\Support\Carbon::parse($header['receipt_date'])->toDateString();
            }
            if (array_key_exists('prepared_by', $header) && (int) $header['prepared_by'] !== (int) $gr->prepared_by && ! empty($header['prepared_by'])) {
                $headerUpdate['prepared_by'] = $header['prepared_by'];
                $changes[] = 'prepared by changed';
            }
            if ($headerUpdate) {
                $gr->update($headerUpdate);
            }

            if (empty($changes)) {
                throw ValidationException::withMessages(['revise' => 'Nothing to change.']);
            }

            foreach (array_keys($affectedOrders) as $purchaseOrderId) {
                $this->refreshPurchaseOrderStatus(PurchaseOrder::findOrFail($purchaseOrderId));
            }

            return $changes;
        });
    }

    protected function guardWarehouseHas(ProductBatch $batch, int $qty, Location $warehouse, string $name): void
    {
        $available = $batch->qtyAtLocation($warehouse->id);

        if ($available < $qty) {
            throw ValidationException::withMessages(['revise' => "{$name}: only {$available} of this lot is still in the Warehouse (it was delivered, sold, transferred or adjusted since), so {$qty} cannot be taken out. Use an Inventory Adjustment instead."]);
        }
    }

    protected function guardNoLaterStockMovement(GoodsReceipt $goodsReceipt, ProductBatch $batch, ?string $message = null): void
    {
        $lastReceiptMovementId = StockMovement::query()
            ->where('source_type', $goodsReceipt->getMorphClass())
            ->where('source_id', $goodsReceipt->id)
            ->where('product_batch_id', $batch->id)
            ->max('id');

        $hasLaterMovement = $lastReceiptMovementId === null
            || StockMovement::query()
                ->where('product_batch_id', $batch->id)
                ->where('id', '>', $lastReceiptMovementId)
                ->exists();

        if ($hasLaterMovement) {
            throw ValidationException::withMessages([
                'revise' => $message ?? 'This batch has later stock movements and cannot be corrected.',
            ]);
        }
    }

    /** Move a Purchase Order line's received quantity by the difference an edit made (a raise cannot pass what is still expected). */
    protected function moveReceivedQty(PurchaseOrderItem $purchaseOrderItem, int $delta, string $name): void
    {
        $purchaseOrderItem = PurchaseOrderItem::with('purchaseOrder')->lockForUpdate()->findOrFail($purchaseOrderItem->id);

        if ($delta > 0) {
            if ($purchaseOrderItem->purchaseOrder->status === 'closed') {
                throw ValidationException::withMessages(['revise' => "Purchase Order {$purchaseOrderItem->purchaseOrder->po_no} is closed — reopen it before receiving more against it."]);
            }
            if ($delta > $purchaseOrderItem->remaining_qty) {
                throw ValidationException::withMessages(['revise' => "{$name}: only {$purchaseOrderItem->remaining_qty} more can be received on the Purchase Order."]);
            }
        }

        $purchaseOrderItem->update(['received_qty' => max(0, (int) $purchaseOrderItem->received_qty + $delta)]);
    }

    /**
     * Generate the next sequential Goods Receipt number for the current
     * year, e.g. GR-2026-00001.
     */
    public function generateGrNo(): string
    {
        $year = now()->year;
        $prefix = "GR-{$year}-";

        // lockForUpdate() blocks a concurrent caller until this transaction
        // commits, preventing two requests from generating the same number.
        $lastGrNo = GoodsReceipt::where('gr_no', 'like', "{$prefix}%")
            ->orderByDesc('gr_no')
            ->lockForUpdate()
            ->value('gr_no');

        $nextSequence = 1;
        if ($lastGrNo) {
            $nextSequence = (int) substr($lastGrNo, strlen($prefix)) + 1;
        }

        return $prefix . str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
    }
}
