<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    /**
     * Stop expecting whatever is still unreceived on an issued Purchase Order (the
     * supplier will never deliver it). Received quantities, stock and cost are not
     * touched — the order just becomes CLOSED, so it drops out of "pending" and no
     * further Goods Receipt can be made against it. Returns the lines that were
     * still short, for the audit trail.
     *
     * @return array<int,array{item:string,ordered:int,received:int,unreceived:int}>
     */
    public function closeOrder(PurchaseOrder $purchaseOrder): array
    {
        return DB::transaction(function () use ($purchaseOrder) {
            $order = PurchaseOrder::with(['items.product', 'items.genericName'])->lockForUpdate()->findOrFail($purchaseOrder->id);

            if ($order->isDraft() || ! in_array($order->status, ['open', 'partially_received'], true)) {
                throw ValidationException::withMessages(['close' => 'Only an open or partially received Purchase Order can be closed.']);
            }

            $unreceived = $order->items
                ->filter(fn (PurchaseOrderItem $item) => $item->remaining_qty > 0)
                ->map(fn (PurchaseOrderItem $item) => [
                    'item' => $item->product->item_name ?? $item->genericName->generic_name ?? '—',
                    'ordered' => (int) $item->qty,
                    'received' => (int) $item->received_qty,
                    'unreceived' => (int) $item->remaining_qty,
                ])
                ->values()
                ->all();

            $order->update(['status' => 'closed']);

            return $unreceived;
        });
    }

    /**
     * Undo a Close: the order goes back to whatever its received quantities say
     * (open / partially received / completed).
     */
    public function reopenOrder(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder) {
            $order = PurchaseOrder::with('items')->lockForUpdate()->findOrFail($purchaseOrder->id);

            if ($order->status !== 'closed') {
                throw ValidationException::withMessages(['close' => 'Only a closed Purchase Order can be reopened.']);
            }

            $status = match (true) {
                $order->items->every(fn (PurchaseOrderItem $item) => $item->received_qty >= $item->qty) => 'completed',
                $order->items->contains(fn (PurchaseOrderItem $item) => $item->received_qty > 0) => 'partially_received',
                default => 'open',
            };

            $order->update(['status' => $status]);

            return $order;
        });
    }

    /**
     * Create a Purchase Order with its line items. A line orders a Generic
     * Item — the specific brand (product_id) isn't decided until Goods
     * Receipt time, so it's optional here and normally left null.
     *
     * @param array $data ['supplier_id', 'order_date', 'prepared_by',
     *                     'items' => [['generic_name_id','product_id','qty','unit','unit_cost','remarks'], ...]]
     */
    public function createPurchaseOrder(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $purchaseOrder = PurchaseOrder::create([
                'supplier_id' => $data['supplier_id'],
                'po_no' => $this->generatePoNo(),
                'status' => 'open',
                'is_draft' => false,
                'order_date' => $data['order_date'],
                'prepared_by' => $data['prepared_by'] ?? null,
            ]);

            $this->applyItems($purchaseOrder, $data['items']);

            return $purchaseOrder;
        });
    }

    /**
     * Save (or re-save) a draft — nothing here ever touches PO status or
     * received_qty tracking, so the encoder can leave supplier/items blank/
     * incomplete and resume later.
     *
     * @param array $data ['supplier_id', 'order_date', 'prepared_by',
     *                     'items' => [['generic_name_id','product_id','qty','unit','unit_cost','remarks'], ...]]
     */
    public function saveDraft(array $data, ?PurchaseOrder $existing = null): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $existing) {
            $purchaseOrder = $existing ?? new PurchaseOrder([
                'po_no' => $this->generatePoNo(),
            ]);

            $purchaseOrder->fill([
                'supplier_id' => $data['supplier_id'] ?? null,
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'prepared_by' => $data['prepared_by'] ?? null,
                'is_draft' => true,
            ]);
            $purchaseOrder->save();

            // Replace whatever items existed before — safe, since a draft's
            // items have never been received against.
            $purchaseOrder->items()->delete();
            foreach ($data['items'] ?? [] as $line) {
                if (empty($line['generic_name_id'])) {
                    continue;
                }

                $purchaseOrder->items()->create([
                    'generic_name_id' => $line['generic_name_id'],
                    'product_id' => $line['product_id'] ?? null,
                    'qty' => $line['qty'] ?? null,
                    'unit' => $line['unit'] ?? null,
                    'unit_cost' => $line['unit_cost'] ?? null,
                    'remarks' => $line['remarks'] ?? null,
                ]);
            }

            return $purchaseOrder;
        });
    }

    /**
     * Turn a draft into a real, posted Purchase Order. Replaces the draft's
     * items with the final values, then runs the same item-creation logic
     * createPurchaseOrder() uses.
     */
    public function finalizeDraft(PurchaseOrder $draft, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($draft, $data) {
            $draft->fill([
                'supplier_id' => $data['supplier_id'],
                'status' => 'open',
                'is_draft' => false,
                'order_date' => $data['order_date'],
                'prepared_by' => $data['prepared_by'] ?? null,
            ]);
            $draft->save();

            $draft->items()->delete();
            $this->applyItems($draft, $data['items']);

            return $draft;
        });
    }

    /**
     * Create each line item — shared by createPurchaseOrder() and
     * finalizeDraft() so this logic exists in exactly one place.
     */
    protected function applyItems(PurchaseOrder $purchaseOrder, array $items): void
    {
        foreach ($items as $line) {
            $purchaseOrder->items()->create([
                'generic_name_id' => $line['generic_name_id'],
                'product_id' => $line['product_id'] ?? null,
                'qty' => $line['qty'],
                'unit' => $line['unit'] ?? null,
                'unit_cost' => $line['unit_cost'],
                'remarks' => $line['remarks'] ?? null,
            ]);
        }
    }

    /**
     * Generate the next sequential Purchase Order number for the current
     * year, e.g. PO-2026-00001.
     */
    public function generatePoNo(): string
    {
        $year = now()->year;
        $prefix = "PO-{$year}-";

        // lockForUpdate() blocks a concurrent caller until this transaction
        // commits, preventing two requests from generating the same number.
        $lastPoNo = PurchaseOrder::where('po_no', 'like', "{$prefix}%")
            ->orderByDesc('po_no')
            ->lockForUpdate()
            ->value('po_no');

        $nextSequence = 1;
        if ($lastPoNo) {
            $nextSequence = (int) substr($lastPoNo, strlen($prefix)) + 1;
        }

        return $prefix . str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
    }
}
