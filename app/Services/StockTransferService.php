<?php

namespace App\Services;

use App\Models\Location;
use App\Models\ProductBatch;
use App\Models\StockTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    public function __construct(protected StockService $stockService) {}

    /**
     * Create a Stock Transfer with its line items, moving each line's qty
     * from the source location to the destination (via StockService, which
     * writes the paired deduct/restock movements into the same Product
     * History ledger every other transaction type uses).
     *
     * @param array $data ['date', 'from_location_id', 'to_location_id', 'prepared_by',
     *                     'lines' => [['product_batch_id', 'qty'], ...]]
     */
    public function createStockTransfer(array $data, ?int $userId = null): StockTransfer
    {
        if ((int) $data['from_location_id'] === (int) $data['to_location_id']) {
            throw ValidationException::withMessages([
                'to_location_id' => 'From and To locations must be different.',
            ]);
        }

        return DB::transaction(function () use ($data, $userId) {
            $reference = $this->generateReference();

            $from = Location::findOrFail($data['from_location_id']);
            $to = Location::findOrFail($data['to_location_id']);

            $stockTransfer = StockTransfer::create([
                'reference' => $reference,
                'date' => $data['date'],
                'from_location_id' => $from->id,
                'to_location_id' => $to->id,
                'prepared_by' => $data['prepared_by'] ?? null,
            ]);

            foreach ($data['lines'] as $line) {
                $batch = ProductBatch::with('product')->findOrFail($line['product_batch_id']);
                $qty = (int) $line['qty'];
                $availableAtSource = $batch->qtyAtLocation($from->id);

                if ($availableAtSource < $qty) {
                    throw ValidationException::withMessages([
                        'lines' => "Transfer qty for {$batch->product->item_name} exceeds what's available at {$from->name} ({$availableAtSource}).",
                    ]);
                }

                $this->stockService->transfer($batch, $qty, $from, $to, "Stock Transfer {$reference}", $userId, $stockTransfer);

                $stockTransfer->lines()->create([
                    'product_batch_id' => $batch->id,
                    'qty' => $qty,
                ]);
            }

            return $stockTransfer;
        });
    }

    /**
     * Generate the next sequential Stock Transfer reference for the current
     * year, e.g. ST-2026-00001.
     */
    public function generateReference(): string
    {
        $year = now()->year;
        $prefix = "ST-{$year}-";

        $lastReference = StockTransfer::where('reference', 'like', "{$prefix}%")
            ->orderByDesc('reference')
            ->value('reference');

        $nextSequence = 1;
        if ($lastReference) {
            $nextSequence = (int) substr($lastReference, strlen($prefix)) + 1;
        }

        return $prefix . str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
    }
}
