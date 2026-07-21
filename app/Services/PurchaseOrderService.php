<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    /**
     * Create a Purchase Order with its line items.
     *
     * @param array $data ['supplier_id', 'order_date', 'prepared_by', 'items' => [['item_id','qty','unit_cost'], ...]]
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
                    'item_id' => $line['item_id'],
                    'qty' => $line['qty'],
                    'unit_cost' => $line['unit_cost'],
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
