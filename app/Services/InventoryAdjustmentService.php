<?php

namespace App\Services;

use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentLine;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            $adjustment = InventoryAdjustment::create([
                'adjustment_no' => $this->generateAdjustmentNo(),
                'adjustment_date' => $data['adjustment_date'],
                'adjustment_type' => $data['adjustment_type'],
                'description' => $data['description'] ?? null,
                'note' => $data['note'] ?? null,
                'prepared_by' => $data['prepared_by'] ?? null,
                'status' => 'posted',
            ]);

            $this->applyLines($adjustment, $data['lines'], $userId);

            return $adjustment;
        });
    }

    /**
     * Save (or re-save) a draft — no stock movement at all, so the encoder
     * can leave lines blank/incomplete and resume later. Only the header and
     * lines are persisted; nothing here ever touches location_stocks.
     *
     * @param array $data ['adjustment_date', 'adjustment_type', 'description', 'note', 'prepared_by',
     *                     'lines' => [['product_id','product_batch_id','batch_no','expiration_date','qty','remarks'], ...]]
     */
    public function saveDraft(array $data, ?int $userId = null, ?InventoryAdjustment $existing = null): InventoryAdjustment
    {
        return DB::transaction(function () use ($data, $userId, $existing) {
            $adjustment = $existing ?? new InventoryAdjustment([
                'adjustment_no' => $this->generateAdjustmentNo(),
            ]);

            $adjustment->fill([
                'adjustment_date' => $data['adjustment_date'] ?? now()->toDateString(),
                'adjustment_type' => $data['adjustment_type'] ?? null,
                'description' => $data['description'] ?? null,
                'note' => $data['note'] ?? null,
                'prepared_by' => $data['prepared_by'] ?? $userId,
                'status' => 'draft',
            ]);
            $adjustment->save();

            // Replace whatever lines existed before — safe, since a draft's
            // lines have never been read by anything that moves stock.
            $adjustment->lines()->delete();
            foreach ($data['lines'] ?? [] as $line) {
                if (empty($line['product_id'])) {
                    continue;
                }

                $adjustment->lines()->create([
                    'product_id' => $line['product_id'],
                    'product_batch_id' => $line['product_batch_id'] ?? null,
                    'location_id' => $line['location_id'] ?? null,
                    'batch_no' => $line['batch_no'] ?? null,
                    'expiration_date' => $line['expiration_date'] ?? null,
                    'qty' => $line['qty'] ?? null,
                    'remarks' => $line['remarks'] ?? null,
                ]);
            }

            return $adjustment;
        });
    }

    /**
     * Turn a draft into a real, posted Inventory Adjustment — the one moment
     * its stock actually moves. Replaces the draft's lines with the final
     * values, then runs the same stock-moving logic createAdjustment() uses.
     */
    public function finalizeDraft(InventoryAdjustment $draft, array $data, ?int $userId = null): InventoryAdjustment
    {
        return DB::transaction(function () use ($draft, $data, $userId) {
            $draft->fill([
                'adjustment_date' => $data['adjustment_date'],
                'adjustment_type' => $data['adjustment_type'],
                'description' => $data['description'] ?? null,
                'note' => $data['note'] ?? null,
                'prepared_by' => $data['prepared_by'] ?? null,
                'status' => 'posted',
            ]);
            $draft->save();

            $draft->lines()->delete();
            $this->applyLines($draft, $data['lines'], $userId);

            return $draft;
        });
    }

    /**
     * Resolve each line's batch, move its stock (restock or deduct,
     * depending on the adjustment's direction), and record the line —
     * shared by createAdjustment() and finalizeDraft() so this logic exists
     * in exactly one place.
     */
    protected function applyLines(InventoryAdjustment $adjustment, array $lines, ?int $userId): void
    {
        $direction = $adjustment->direction();

        foreach ($lines as $line) {
            $product = Product::findOrFail($line['product_id']);
            $qty = (int) $line['qty'];
            $location = ! empty($line['location_id']) ? Location::findOrFail($line['location_id']) : Location::warehouse();

            $batch = $this->resolveBatch($product, $line);

            if ($direction === 'in') {
                $this->stockService->restock($batch, $qty, $location, $line['remarks'] ?? "Inventory Adjustment {$adjustment->adjustment_no}", $userId, $adjustment);
            } else {
                $this->stockService->deduct($batch, $qty, $location, $line['remarks'] ?? "Inventory Adjustment {$adjustment->adjustment_no}", $userId, $adjustment);
            }

            $adjustment->lines()->create([
                'product_id' => $product->id,
                'product_batch_id' => $batch->id,
                'location_id' => $location->id,
                'batch_no' => $batch->batch_no,
                'expiration_date' => $batch->expiration_date,
                'qty' => $qty,
                'remarks' => $line['remarks'] ?? null,
            ]);
        }
    }

    /**
     * Create a reversal Inventory Adjustment that exactly undoes $original's
     * lines (same product/batch/location/qty, opposite direction) — a safe
     * "write-off and re-encode" shortcut that never mutates the original
     * record, only adds a new one. Reuses createAdjustment() for the actual
     * stock movement, so insufficient-stock protection (StockService::deduct)
     * applies automatically if the original's stock was already consumed
     * elsewhere before the write-off is attempted.
     */
    public function writeOff(InventoryAdjustment $original, ?int $userId = null): InventoryAdjustment
    {
        if ($original->reversedBy) {
            throw ValidationException::withMessages([
                'adjustment' => "This adjustment has already been written off by {$original->reversedBy->adjustment_no}.",
            ]);
        }

        $reversalType = $original->direction() === 'in' ? 'correction_decrease' : 'correction_increase';

        $data = [
            'adjustment_date' => now()->toDateString(),
            'adjustment_type' => $reversalType,
            'description' => "Write-off of {$original->adjustment_no}",
            'prepared_by' => $userId,
            'lines' => $original->lines->map(fn (InventoryAdjustmentLine $line) => [
                'product_id' => $line->product_id,
                'product_batch_id' => $line->product_batch_id,
                'location_id' => $line->location_id,
                'qty' => $line->qty,
                'remarks' => "Write-off of {$original->adjustment_no}",
            ])->all(),
        ];

        return DB::transaction(function () use ($data, $userId, $original) {
            $reversal = $this->createAdjustment($data, $userId);
            $reversal->update(['reverses_adjustment_id' => $original->id]);

            return $reversal;
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
