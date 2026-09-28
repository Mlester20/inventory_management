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
 * questions before extending this (wastage, destination auto-creation).
 *
 * Per Sir, each line can also set the destination product's Price/Cost —
 * the source's, divided evenly by how many destination units the line
 * produces (₱100/BX into 100 tabs = ₱1/tab) — pre-filled on the form but
 * editable, and only actually written to the product when the encoder
 * leaves "apply" checked.
 */
class RepackService
{
    public function __construct(protected StockService $stockService) {}

    /**
     * @param array $data ['date', 'location_id', 'prepared_by', 'remarks',
     *   'lines' => [['source_batch_id', 'source_qty', 'destination_product_id',
     *                'destination_qty', 'destination_batch_no'?, 'destination_expiration_date'?,
     *                'destination_price'?, 'destination_cost'?, 'apply_price'?], ...]]
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

    /**
     * Void a Repack — allowed only while it is still complete: every piece it
     * produced must still be at the location. Once some are sold or moved the
     * void is refused (nothing is changed) and the difference has to be
     * corrected with an Inventory Adjustment instead, as Sir asked.
     *
     * All-or-nothing: every destination lot is checked before anything moves.
     * Two lines producing into the same destination lot are added together,
     * so a lot that can cover each line alone but not both is still refused.
     * Voiding takes the produced pieces back out and puts the source quantity
     * back into its original lot, both through StockService so they appear in
     * Product History like every other movement. The row is never deleted.
     */
    public function voidRepack(Repack $repack, ?int $userId = null, ?string $reason = null): Repack
    {
        return DB::transaction(function () use ($repack, $userId, $reason) {
            // Re-fetch under a row lock rather than trusting the route-bound
            // model's flag: two near-simultaneous void clicks would otherwise
            // both pass the check and take the stock back twice.
            $repack = Repack::lockForUpdate()->findOrFail($repack->id);

            if ($repack->isVoided()) {
                throw ValidationException::withMessages([
                    'void' => 'This Repack has already been voided.',
                ]);
            }

            $repack->load('location', 'lines.sourceBatch.product', 'lines.destinationBatch.product');
            $location = $repack->location;

            $producedByLot = [];
            foreach ($repack->lines as $line) {
                $producedByLot[$line->destination_batch_id] = ($producedByLot[$line->destination_batch_id] ?? 0) + $line->destination_qty;
            }

            $short = [];
            foreach ($producedByLot as $batchId => $produced) {
                $batch = $repack->lines->firstWhere('destination_batch_id', $batchId)->destinationBatch;
                $left = $batch->qtyAtLocation($location->id);

                if ($left < $produced) {
                    $short[] = "{$batch->product->item_name}: {$produced} were produced but only {$left} are left at {$location->name}";
                }
            }

            if ($short !== []) {
                throw ValidationException::withMessages([
                    'void' => 'Cannot void: some of the repacked pieces are already gone (' . implode('; ', $short)
                        . '). Use an Inventory Adjustment to correct the quantity instead.',
                ]);
            }

            $remarks = "Void Repack {$repack->reference}";
            foreach ($repack->lines as $line) {
                $this->stockService->deduct($line->destinationBatch, $line->destination_qty, $location, $remarks, $userId, $repack);
                $this->stockService->restock($line->sourceBatch, $line->source_qty, $location, $remarks, $userId, $repack);
            }

            $repack->update([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => $userId,
                'void_reason' => $reason,
            ]);

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

        // Per Sir: the destination's Price/Cost is the source's, divided evenly across
        // however many destination units this line produces (₱100/BX into 100 tabs = ₱1/tab).
        // The encoder can override the suggested figures before posting, or uncheck
        // "apply" to leave the destination product's own Price/Cost untouched. Either way
        // the line keeps what was suggested/entered, as a record independent of the
        // product's Price/Cost, which can change later.
        $destinationPrice = isset($line['destination_price']) && $line['destination_price'] !== ''
            ? round((float) $line['destination_price'], 2) : null;
        $destinationCost = isset($line['destination_cost']) && $line['destination_cost'] !== ''
            ? round((float) $line['destination_cost'], 2) : null;
        $applyPrice = ! empty($line['apply_price']) && ($destinationPrice !== null || $destinationCost !== null);

        if ($applyPrice) {
            $destinationProduct->update(array_filter([
                'unit_price' => $destinationPrice,
                'unit_cost' => $destinationCost,
            ], fn ($value) => $value !== null));
        }

        $repack->lines()->create([
            'source_batch_id' => $sourceBatch->id,
            'source_qty' => $sourceQty,
            'destination_product_id' => $destinationProduct->id,
            'destination_batch_id' => $destinationBatch->id,
            'destination_qty' => $destinationQty,
            'destination_price' => $destinationPrice,
            'destination_cost' => $destinationCost,
            'price_applied' => $applyPrice,
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
