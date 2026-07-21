<?php

namespace App\Services;

use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

class SalesOrderService
{
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
                'status' => 'open',
                'order_date' => $data['order_date'],
                'prepared_by' => $data['prepared_by'] ?? null,
            ]);

            foreach ($data['items'] as $line) {
                $salesOrder->items()->create([
                    'generic_name_id' => $line['generic_name_id'],
                    'qty' => $line['qty'],
                    'price' => $line['price'],
                    'advance_order_qty' => $line['advance_order_qty'] ?? 0,
                ]);
            }

            return $salesOrder;
        });
    }

    /**
     * Generate the next sequential Sales Order number for the current year,
     * e.g. SO-2026-00001.
     */
    public function generateSoNo(): string
    {
        $year = now()->year;
        $prefix = "SO-{$year}-";

        $lastSoNo = SalesOrder::where('so_no', 'like', "{$prefix}%")
            ->orderByDesc('so_no')
            ->value('so_no');

        $nextSequence = 1;
        if ($lastSoNo) {
            $nextSequence = (int) substr($lastSoNo, strlen($prefix)) + 1;
        }

        return $prefix . str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
    }
}
