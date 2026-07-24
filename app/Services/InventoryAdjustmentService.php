<?php

namespace App\Services;

use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Support\Facades\DB;

class InventoryAdjustmentService
{
    public function __construct(protected StockService $stockService) {}

    /**
     * Create an Inventory Adjustment with its line items, restocking or
     * deducting each line's batch (via StockService) according to the
     * adjustment type's direction. A line with no product_batch_id and a
     * batch_no finds-or-creates that batch under the product.
     *
     * @param array $data ['adjustment_date', 'adjustment_type', 'description', 'note', 'prepared_by',
     *                     'lines' => [['product_id','product_batch_id','batch_no','expiration_date','qty','remarks'], ...]]
     */
    public function createAdjustment(array $data, ?int $userId = null): InventoryAdjustment
    {
        return DB::transaction(function () use ($data, $userId) {
            $adjustmentNo = $this->generateAdjustmentNo();

            $adjustment = InventoryAdjustment::create([
                'adjustment_no' => $adjustmentNo,
                'adjustment_date' => $data['adjustment_date'],
                'adjustment_type' => $data['adjustment_type'],
                'description' => $data['description'] ?? null,
                'note' => $data['note'] ?? null,
                'prepared_by' => $data['prepared_by'] ?? null,
            ]);

            $direction = $adjustment->direction();

            foreach ($data['lines'] as $line) {
                $product = Product::findOrFail($line['product_id']);
                $qty = (int) $line['qty'];

                $batch = $this->resolveBatch($product, $line);

                if ($direction === 'in') {
                    $this->stockService->restock($batch, $qty, $line['remarks'] ?? "Inventory Adjustment {$adjustmentNo}", $userId, $adjustment);
                } else {
                    $this->stockService->deduct($batch, $qty, $line['remarks'] ?? "Inventory Adjustment {$adjustmentNo}", $userId, $adjustment);
                }

                $adjustment->lines()->create([
                    'product_id' => $product->id,
                    'product_batch_id' => $batch->id,
                    'batch_no' => $batch->batch_no,
                    'expiration_date' => $batch->expiration_date,
                    'qty' => $qty,
                    'remarks' => $line['remarks'] ?? null,
                ]);
            }

            return $adjustment;
        });
    }

    /**
     * Resolve which batch a line applies to: the explicit product_batch_id
     * if given, an existing batch matching the typed batch_no, or a brand
     * new batch (e.g. "Stock In - Found" for a lot never formally received).
     */
    protected function resolveBatch(Product $product, array $line): ProductBatch
    {
        if (! empty($line['product_batch_id'])) {
            return ProductBatch::findOrFail($line['product_batch_id']);
        }

        $batchNo = $line['batch_no'] ?? null;

        if ($batchNo) {
            $existing = $product->batches()->where('batch_no', $batchNo)->first();
            if ($existing) {
                return $existing;
            }
        }

        return $product->batches()->create([
            'batch_no' => $batchNo,
            'expiration_date' => $line['expiration_date'] ?? null,
            'qty' => 0,
            'reserved_qty' => 0,
        ]);
    }

    /**
     * Generate the next sequential Inventory Adjustment number for the
     * current year, e.g. ADJ-2026-00001.
     */
    public function generateAdjustmentNo(): string
    {
        $year = now()->year;
        $prefix = "ADJ-{$year}-";

        $lastNo = InventoryAdjustment::where('adjustment_no', 'like', "{$prefix}%")
            ->orderByDesc('adjustment_no')
            ->value('adjustment_no');

        $nextSequence = 1;
        if ($lastNo) {
            $nextSequence = (int) substr($lastNo, strlen($prefix)) + 1;
        }

        return $prefix . str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
    }
}
