<?php

namespace App\Services;

use App\Models\Location;
use App\Models\LocationStock;
use App\Models\ProductBatch;
use App\Models\StockDisposal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockDisposalService
{
    public function __construct(protected StockService $stockService) {}

    /**
     * A batch's current stock broken down by location (qty > 0 only) —
     * powers the disposal create form's pre-fill: a batch sitting in just
     * the Warehouse pre-fills one line, a batch split across Warehouse and
     * POS pre-fills one line per location.
     */
    public function getAvailableLocationsForBatch(ProductBatch $batch): Collection
    {
        return LocationStock::with('location')
            ->where('product_batch_id', $batch->id)
            ->where('qty', '>', 0)
            ->get();
    }

    /**
     * Create a Stock Disposal with its line items, deducting each line's
     * qty from its batch at its location (via StockService — the same
     * deduction mechanism Delivery Receipt/Stock Transfer already use, not
     * a new code path) and writing a corresponding Product History entry.
     *
     * @param array $data ['date', 'reason', 'remarks', 'prepared_by',
     *                     'lines' => [['product_batch_id', 'location_id', 'qty'], ...]]
     */
    public function createStockDisposal(array $data, ?int $userId = null): StockDisposal
    {
        return DB::transaction(function () use ($data, $userId) {
            $reference = $this->generateReference();

            $stockDisposal = StockDisposal::create([
                'reference' => $reference,
                'date' => $data['date'],
                'reason' => $data['reason'] ?? 'Expired',
                'remarks' => $data['remarks'] ?? null,
                'prepared_by' => $data['prepared_by'] ?? null,
            ]);

            foreach ($data['lines'] as $line) {
                $batch = ProductBatch::with('product')->findOrFail($line['product_batch_id']);
                $location = Location::findOrFail($line['location_id']);
                $qty = (int) $line['qty'];
                $availableAtLocation = $batch->qtyAtLocation($location->id);

                if ($availableAtLocation < $qty) {
                    throw ValidationException::withMessages([
                        'lines' => "Disposal qty for {$batch->product->item_name} exceeds what's available at {$location->name} ({$availableAtLocation}).",
                    ]);
                }

                $this->stockService->deduct(
                    $batch,
                    $qty,
                    $location,
                    "Stock Disposal {$reference} ({$stockDisposal->reason})",
                    $userId,
                    $stockDisposal
                );

                $stockDisposal->lines()->create([
                    'product_batch_id' => $batch->id,
                    'location_id' => $location->id,
                    'qty' => $qty,
                ]);
            }

            return $stockDisposal;
        });
    }

    /**
     * Generate the next sequential Stock Disposal reference for the current
     * year, e.g. SD-2026-00001.
     */
    public function generateReference(): string
    {
        $year = now()->year;
        $prefix = "SD-{$year}-";

        $lastReference = StockDisposal::where('reference', 'like', "{$prefix}%")
            ->orderByDesc('reference')
            ->value('reference');

        $nextSequence = 1;
        if ($lastReference) {
            $nextSequence = (int) substr($lastReference, strlen($prefix)) + 1;
        }

        return $prefix . str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
    }
}
