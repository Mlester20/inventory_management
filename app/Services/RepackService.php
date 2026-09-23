<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Repack;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Converts stock of one product into stock of another at the same location
 * (e.g. breaking a BX into loose PC) — see docs/repacking-unit-conversion-plan.md.
 * Deducts the source lot and restocks the destination lot through
 * StockService, exactly like Stock Transfer does between locations, so both
 * movements land in the same Product History ledger with no second logging
 * path. v1 posts immediately (no draft status) — see the plan doc's open
 * questions before extending this (void support, wastage, destination
 * auto-creation).
 */
class RepackService
{
    public function __construct(protected StockService $stockService) {}

    /**
     * @param array $data ['date', 'location_id', 'prepared_by', 'remarks',
     *   'lines' => [['source_batch_id', 'source_qty', 'destination_product_id',
     *                'destination_qty', 'destination_batch_no'?, 'destination_expiration_date'?], ...]]
     */
    public function createRepack(array $data, ?int $userId = null): Repack
    {
        return DB::transaction(function () use ($data, $userId) {
            $location = Location::findOrFail($data['location_id']);

            if (empty($data['lines'])) {
                throw ValidationException::withMessages([
                    'lines' => 'Add at least one line to repack.',
                ]);
            }

            $repack = Repack::create([
                'reference' => $this->generateReference(),
                'date' => $data['date'],
                'location_id' => $location->id,
                'prepared_by' => $data['prepared_by'] ?? $userId,
                'status' => 'posted',
                'remarks' => $data['remarks'] ?? null,
            ]);

            foreach ($data['lines'] as $line) {
                $this->applyLine($repack, $location, $line, $userId);
            }

            return $repack;
        });
    }

    protected function applyLine(Repack $repack, Location $location, array $line, ?int $userId): void
    {
        $sourceBatch = ProductBatch::with('product')->findOrFail($line['source_batch_id']);
        $sourceQty = (int) $line['source_qty'];
        $destinationQty = (int) $line['destination_qty'];
        $destinationProduct = Product::findOrFail($line['destination_product_id']);

        if ($sourceQty <= 0 || $destinationQty <= 0) {
            throw ValidationException::withMessages([
                'lines' => 'Repack quantities must be greater than 0.',
            ]);
        }

        if ($destinationProduct->id === $sourceBatch->product_id) {
            throw ValidationException::withMessages([
                'lines' => "Repack destination must be a different product than the source ({$sourceBatch->product->item_name}).",
            ]);
        }

        $available = $sourceBatch->qtyAtLocation($location->id);
        if ($available < $sourceQty) {
            throw ValidationException::withMessages([
                'lines' => "Repack qty for {$sourceBatch->product->item_name} exceeds what's available at {$location->name} ({$available}).",
            ]);
        }

        // The destination lot carries the source lot's own Batch No/Expiry by
        // default — it's still the same physical stock, just a different
        // Unit — with an explicit override accepted for the rare case that's
        // wrong. Reuses the existing lot if the product already has one with
        // this Batch No (same rule Inventory Adjustment/Opening Inventory use),
        // so repacking into the same destination lot twice adds rather than
        // duplicates it.
        $destinationBatchNo = $line['destination_batch_no'] ?? $sourceBatch->batch_no;
        $destinationExpiration = $line['destination_expiration_date'] ?? $sourceBatch->expiration_date;

        $destinationBatch = $destinationProduct->batches()->where('batch_no', $destinationBatchNo)->first()
            ?? $destinationProduct->batches()->create([
                'batch_no' => $destinationBatchNo,
                'expiration_date' => $destinationExpiration,
            ]);

        $this->stockService->deduct($sourceBatch, $sourceQty, $location, "Repack {$repack->reference}", $userId, $repack);
        $this->stockService->restock($destinationBatch, $destinationQty, $location, "Repack {$repack->reference}", $userId, $repack);

        $repack->lines()->create([
            'source_batch_id' => $sourceBatch->id,
            'source_qty' => $sourceQty,
            'destination_product_id' => $destinationProduct->id,
            'destination_batch_id' => $destinationBatch->id,
            'destination_qty' => $destinationQty,
        ]);
    }

    /**
     * Generate the next sequential Repack reference for the current year,
     * e.g. RPK-2026-00001.
     */
    public function generateReference(): string
    {
        $year = now()->year;
        $prefix = "RPK-{$year}-";

        $lastReference = Repack::where('reference', 'like', "{$prefix}%")
            ->orderByDesc('reference')
            ->lockForUpdate()
            ->value('reference');

        $nextSequence = 1;
        if ($lastReference) {
            $nextSequence = (int) substr($lastReference, strlen($prefix)) + 1;
        }

        return $prefix . str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
    }
}
