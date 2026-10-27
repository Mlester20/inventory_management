<?php

namespace App\Services;

use App\Models\DeliveryReceiptItem;
use App\Models\GenericName;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesOrderService
{
    /** Statuses an issued Sales Order can still be edited in (not cancelled, not closed, not a draft). */
    public const EDITABLE_STATUSES = ['open', 'partially_delivered', 'completed'];

    /**
     * Whether a line has been (or is being) delivered: delivered quantity, or any Delivery Receipt line —
     * even a draft one — that points at it. Such a line can only be lowered, never removed or swapped.
     */
    public function lineHasDeliveries(SalesOrderItem $item): bool
    {
        return $item->delivered_qty > 0
            || DeliveryReceiptItem::where('sales_order_item_id', $item->id)->exists();
    }

    /**
     * Correct an issued Sales Order (Sir's rules): a line that already has deliveries can only have its quantity
     * lowered — never below what was delivered plus the advance quantity — and its remarks changed; its item,
     * price and tax stay, and it cannot be removed (edit the Delivery Receipt first). A line with no deliveries is
     * free: change the item, quantity, price, description or advance quantity, remove it, or add new lines.
     * Lines are matched by id, so the delivered quantities and the Delivery Receipts that point at them survive.
     *
     * @param array $data ['po_no','customer_po_date','order_date','prepared_by','notes','items' => [['id'?,'generic_name_id','product_id','qty','price','tax_classification','advance_order_qty','remarks'], ...]]
     * @return array<int,string> what changed, for the audit trail
     */
    public function updateIssuedOrder(SalesOrder $salesOrder, array $data): array
    {
        return DB::transaction(function () use ($salesOrder, $data) {
            $order = SalesOrder::lockForUpdate()->findOrFail($salesOrder->id);

            if ($order->isDraft() || ! in_array($order->status, self::EDITABLE_STATUSES, true)) {
                throw ValidationException::withMessages(['items' => 'Only an open, partially delivered or completed Sales Order can be edited.']);
            }

            $existing = $order->items()->with('genericName')->get()->keyBy('id');
            $changes = [];
            $kept = [];

            foreach ($data['items'] as $line) {
                $qty = (int) $line['qty'];
                $item = ! empty($line['id']) ? $existing->get((int) $line['id']) : null;

                if (! empty($line['id']) && ! $item) {
                    throw ValidationException::withMessages(['items' => 'A line of this order could not be found — reload the page and try again.']);
                }

                if (! $item) {
                    if ((int) ($line['advance_order_qty'] ?? 0) > $qty) {
                        throw ValidationException::withMessages(['items' => 'A new line\'s Advance Qty cannot be more than its quantity.']);
                    }

                    $order->items()->create($this->lineAttributes($line));
                    $changes[] = 'added ' . (GenericName::find($line['generic_name_id'])?->generic_name ?? 'an item') . " ({$qty})";

                    continue;
                }

                $kept[] = $item->id;
                $name = $item->genericName?->generic_name ?? 'Item';
                $minimum = (int) $item->delivered_qty + (int) $item->advance_order_qty;

                if ($this->lineHasDeliveries($item)) {
                    if ($qty < $minimum) {
                        throw ValidationException::withMessages(['items' => "{$name}: {$item->delivered_qty} already delivered" . ($item->advance_order_qty ? " and {$item->advance_order_qty} advance" : '') . ", so the quantity cannot go below {$minimum}. Edit the Delivery Receipt first to go lower."]);
                    }

                    if ($qty !== (int) $item->qty) {
                        $changes[] = "{$name} qty {$item->qty} -> {$qty}";
                    }
                    $item->update(['qty' => $qty, 'remarks' => $line['remarks'] ?? null]);

                    continue;
                }

                if ($qty < $minimum || (int) ($line['advance_order_qty'] ?? 0) > $qty) {
                    throw ValidationException::withMessages(['items' => "{$name}: the quantity cannot be less than its Advance Qty."]);
                }

                $attributes = $this->lineAttributes($line);
                if ((int) $item->generic_name_id !== (int) $attributes['generic_name_id']) {
                    $changes[] = "{$name} replaced by " . (GenericName::find($attributes['generic_name_id'])?->generic_name ?? 'another item');
                } elseif ($qty !== (int) $item->qty) {
                    $changes[] = "{$name} qty {$item->qty} -> {$qty}";
                } elseif ((int) $item->product_id !== (int) ($attributes['product_id'] ?? 0)) {
                    $changes[] = "{$name} item description changed";
                }
                $item->update($attributes);
            }

            foreach ($existing as $item) {
                if (in_array($item->id, $kept, true)) {
                    continue;
                }

                if ($this->lineHasDeliveries($item)) {
                    throw ValidationException::withMessages(['items' => ($item->genericName?->generic_name ?? 'A line') . ' already has deliveries, so it cannot be removed. Edit the Delivery Receipt first.']);
                }

                $changes[] = 'removed ' . ($item->genericName?->generic_name ?? 'an item');
                $item->delete();
            }

            if ($order->items()->count() === 0) {
                throw ValidationException::withMessages(['items' => 'A Sales Order needs at least one line.']);
            }

            $order->update([
                'po_no' => $data['po_no'] ?? null,
                'customer_po_date' => $data['customer_po_date'] ?? null,
                'order_date' => $data['order_date'],
                'prepared_by' => $data['prepared_by'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Lowering or raising quantities can complete the order or reopen it.
            $order->update(['status' => $order->deliveryStatus()]);

            return $changes;
        });
    }

    protected function lineAttributes(array $line): array
    {
        return [
            'generic_name_id' => $line['generic_name_id'],
            'product_id' => $line['product_id'] ?? null,
            'qty' => $line['qty'],
            'price' => $line['price'],
            'tax_classification' => $line['tax_classification'] ?? null,
            'advance_order_qty' => $line['advance_order_qty'] ?? 0,
            'remarks' => $line['remarks'] ?? null,
        ];
    }

    /**
     * Close an issued Sales Order whose remaining items will never be delivered. Nothing is
     * deleted or reversed — the order just becomes CLOSED, so it drops out of "open" and no
     * Delivery Receipt can be made against it. Returns the lines that were still undelivered,
     * for the audit trail.
     *
     * @return array<int,array{item:string,ordered:int,advance:int,delivered:int,undelivered:int}>
     */
    public function closeOrder(SalesOrder $salesOrder): array
    {
        return DB::transaction(function () use ($salesOrder) {
            $order = SalesOrder::with(['items.product', 'items.genericName'])->lockForUpdate()->findOrFail($salesOrder->id);

            if ($order->isDraft() || ! in_array($order->status, ['open', 'partially_delivered'], true)) {
                throw ValidationException::withMessages(['close' => 'Only an open or partially delivered Sales Order can be closed.']);
            }

            $undelivered = $order->items
                ->filter(fn (SalesOrderItem $item) => $item->remaining_qty > 0)
                ->map(fn (SalesOrderItem $item) => [
                    'item' => $item->product->item_name ?? $item->genericName->generic_name ?? '—',
                    'ordered' => (int) $item->qty,
                    'advance' => (int) $item->advance_order_qty,
                    'delivered' => (int) $item->delivered_qty,
                    'undelivered' => (int) $item->remaining_qty,
                ])
                ->values()
                ->all();

            $order->update(['status' => 'closed']);

            return $undelivered;
        });
    }

    /**
     * Undo a Close: the order goes back to whatever its delivered quantities say
     * (open / partially delivered / completed).
     */
    public function reopenOrder(SalesOrder $salesOrder): SalesOrder
    {
        return DB::transaction(function () use ($salesOrder) {
            $order = SalesOrder::lockForUpdate()->findOrFail($salesOrder->id);

            if (! $order->isClosed()) {
                throw ValidationException::withMessages(['close' => 'Only a closed Sales Order can be reopened.']);
            }

            $order->update(['status' => $order->deliveryStatus()]);

            return $order;
        });
    }

    /**
     * Cancel/void a Sales Order — the alternative to delete() when it
     * already has delivery history and can't just be removed. Per Sir's
     * direction: every linked Delivery Receipt gets a note explaining why
     * (the DR's own status is untouched — it still shows what actually
     * happened physically), and any Invoice already created from one of
     * those DRs is auto-cancelled too, since an invoice billing for a now
     * -void order shouldn't stay active.
     *
     * @return array{delivery_receipts: \Illuminate\Support\Collection, invoices: \Illuminate\Support\Collection}
     */
    public function cancel(SalesOrder $salesOrder): array
    {
        return DB::transaction(function () use ($salesOrder) {
            $salesOrder->update(['status' => 'cancelled']);

            $affectedDeliveryReceipts = collect();
            $affectedInvoices = collect();

            foreach ($salesOrder->deliveryReceipts as $deliveryReceipt) {
                $deliveryReceipt->update([
                    'cancellation_note' => "Sales Order {$salesOrder->so_no} linked to this delivery was cancelled/voided on " . now()->format('M d, Y') . '.',
                ]);
                $affectedDeliveryReceipts->push($deliveryReceipt);

                $invoiceIds = $deliveryReceipt->items()
                    ->with('sales')
                    ->get()
                    ->pluck('sales')
                    ->flatten()
                    ->pluck('invoice_id')
                    ->filter()
                    ->unique();

                foreach ($invoiceIds as $invoiceId) {
                    $invoice = Invoice::find($invoiceId);

                    if ($invoice && ! $invoice->isCancelled()) {
                        $invoice->update(['cancelled_at' => now()]);
                        $affectedInvoices->push($invoice);
                    }
                }
            }

            return [
                'delivery_receipts' => $affectedDeliveryReceipts,
                'invoices' => $affectedInvoices->unique('id'),
            ];
        });
    }

    /**
     * Create a Sales Order with its line items.
     *
     * @param array $data ['customer_id', 'po_no', 'order_date', 'prepared_by', 'items' => [['generic_name_id','qty','price','advance_order_qty'], ...]]
     */
    public function createSalesOrder(array $data): SalesOrder
    {
        return DB::transaction(function () use ($data) {
            $salesOrder = SalesOrder::create([
                'customer_id' => $data['customer_id'],
                'so_no' => $this->generateSoNo(),
                'po_no' => $data['po_no'] ?? null,
                'customer_po_date' => $data['customer_po_date'] ?? null,
                'status' => 'open',
                'is_draft' => false,
                'order_date' => $data['order_date'],
                'prepared_by' => $data['prepared_by'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->applyItems($salesOrder, $data['items']);

            return $salesOrder;
        });
    }

    /**
     * Save (or re-save) a draft — nothing here ever touches SO status or
     * delivered_qty tracking, so the encoder can leave customer/items blank/
     * incomplete and resume later.
     *
     * @param array $data ['customer_id', 'po_no', 'order_date', 'prepared_by', 'items' => [['generic_name_id','qty','price','advance_order_qty'], ...]]
     */
    public function saveDraft(array $data, ?SalesOrder $existing = null): SalesOrder
    {
        return DB::transaction(function () use ($data, $existing) {
            $salesOrder = $existing ?? new SalesOrder([
                'so_no' => $this->generateSoNo(),
            ]);

            $salesOrder->fill([
                'customer_id' => $data['customer_id'] ?? null,
                'po_no' => $data['po_no'] ?? null,
                'customer_po_date' => $data['customer_po_date'] ?? null,
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'prepared_by' => $data['prepared_by'] ?? null,
                'is_draft' => true,
                'notes' => $data['notes'] ?? null,
            ]);
            $salesOrder->save();

            // Replace whatever items existed before — safe, since a draft's
            // items have never been delivered against.
            $salesOrder->items()->delete();
            foreach ($data['items'] ?? [] as $line) {
                if (empty($line['generic_name_id'])) {
                    continue;
                }

                $salesOrder->items()->create([
                    'generic_name_id' => $line['generic_name_id'],
                    'product_id' => $line['product_id'] ?? null,
                    'qty' => $line['qty'] ?? null,
                    'price' => $line['price'] ?? null,
                    'tax_classification' => $line['tax_classification'] ?? null,
                    'advance_order_qty' => $line['advance_order_qty'] ?? 0,
                    'remarks' => $line['remarks'] ?? null,
                ]);
            }

            return $salesOrder;
        });
    }

    /**
     * Turn a draft into a real, posted Sales Order. Replaces the draft's
     * items with the final values, then runs the same item-creation logic
     * createSalesOrder() uses.
     */
    public function finalizeDraft(SalesOrder $draft, array $data): SalesOrder
    {
        return DB::transaction(function () use ($draft, $data) {
            $draft->fill([
                'customer_id' => $data['customer_id'],
                'po_no' => $data['po_no'] ?? null,
                'customer_po_date' => $data['customer_po_date'] ?? null,
                'status' => 'open',
                'is_draft' => false,
                'order_date' => $data['order_date'],
                'prepared_by' => $data['prepared_by'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $draft->save();

            $draft->items()->delete();
            $this->applyItems($draft, $data['items']);

            return $draft;
        });
    }

    /**
     * Create each line item — shared by createSalesOrder() and
     * finalizeDraft() so this logic exists in exactly one place.
     */
    protected function applyItems(SalesOrder $salesOrder, array $items): void
    {
        foreach ($items as $line) {
            $salesOrder->items()->create([
                'generic_name_id' => $line['generic_name_id'],
                'product_id' => $line['product_id'] ?? null,
                'qty' => $line['qty'],
                'price' => $line['price'],
                'tax_classification' => $line['tax_classification'] ?? null,
                'advance_order_qty' => $line['advance_order_qty'] ?? 0,
                'remarks' => $line['remarks'] ?? null,
            ]);
        }

        // Every line already delivered in advance — nothing is left for a Delivery Receipt.
        $lines = $salesOrder->items()->get();
        if ($lines->isNotEmpty() && $lines->every(fn ($item) => $item->remaining_qty <= 0)) {
            $salesOrder->update(['status' => 'completed']);
        }
    }

    /**
     * Generate the next sequential Sales Order number for the current year,
     * e.g. SO-2026-00001.
     *
     * lockForUpdate() holds a row/gap lock on this SELECT until the caller's
     * transaction commits, so two concurrent requests (e.g. a double-click
     * submit) can't both read the same "last number" and try to insert the
     * same so_no — the second waits, then reads the freshly-inserted row.
     * Only correct when called inside DB::transaction(), true for every
     * caller of this method.
     *
     * withTrashed() is required: SalesOrder is soft-deletable, but so_no
     * stays unique at the DB level even for trashed rows, so a plain query
     * (which hides trashed rows) would think a taken number is free and
     * collide with it on insert.
     */
    public function generateSoNo(): string
    {
        $year = now()->year;
        $prefix = "SO-{$year}-";

        $lastSoNo = SalesOrder::withTrashed()
            ->where('so_no', 'like', "{$prefix}%")
            ->orderByDesc('so_no')
            ->lockForUpdate()
            ->value('so_no');

        $nextSequence = 1;
        if ($lastSoNo) {
            $nextSequence = (int) substr($lastSoNo, strlen($prefix)) + 1;
        }

        return $prefix . str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
    }
}
