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
                'order_date' => $data['order_date'],
                'prepared_by' => $data['prepared_by'] ?? null,
            ]);

            foreach ($data['items'] as $line) {
                $purchaseOrder->items()->create([
                    'generic_name_id' => $line['generic_name_id'],
                    'product_id' => $line['product_id'] ?? null,
                    'qty' => $line['qty'],
                    'unit' => $line['unit'] ?? null,
                    'unit_cost' => $line['unit_cost'],
                    'remarks' => $line['remarks'] ?? null,
                ]);
            }

            return $purchaseOrder;
        });
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
