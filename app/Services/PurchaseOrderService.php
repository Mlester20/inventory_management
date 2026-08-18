<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
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

        $lastPoNo = PurchaseOrder::where('po_no', 'like', "{$prefix}%")
            ->orderByDesc('po_no')
            ->value('po_no');

        $nextSequence = 1;
        if ($lastPoNo) {
            $nextSequence = (int) substr($lastPoNo, strlen($prefix)) + 1;
        }

        return $prefix . str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
    }
}
