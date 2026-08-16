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
        return DB::transaction(function () use ($data, $userId) {
            $stockTransfer = StockTransfer::create([
                'reference' => $this->generateReference(),
                'date' => $data['date'],
                'prepared_by' => $data['prepared_by'] ?? null,
                'status' => 'posted',
            ]);

            $this->applyLines($stockTransfer, $data, $userId);

            return $stockTransfer;
        });
    }

    /**
     * Save (or re-save) a draft — no stock movement at all, so the encoder
     * can leave locations/lines blank/incomplete and resume later. Only the
     * header and lines are persisted; nothing here ever touches
     * location_stocks.
     *
     * @param array $data ['date', 'from_location_id', 'to_location_id', 'prepared_by',
     *                     'lines' => [['product_batch_id', 'qty'], ...]]
     */
    public function saveDraft(array $data, ?int $userId = null, ?StockTransfer $existing = null): StockTransfer
    {
        return DB::transaction(function () use ($data, $userId, $existing) {
            $stockTransfer = $existing ?? new StockTransfer([
                'reference' => $this->generateReference(),
            ]);

            $stockTransfer->fill([
                'date' => $data['date'] ?? now()->toDateString(),
                'from_location_id' => $data['from_location_id'] ?? null,
                'to_location_id' => $data['to_location_id'] ?? null,
                'prepared_by' => $data['prepared_by'] ?? $userId,
                'status' => 'draft',
            ]);
            $stockTransfer->save();

            // Replace whatever lines existed before — safe, since a draft's
            // lines have never been read by anything that moves stock.
            $stockTransfer->lines()->delete();
            foreach ($data['lines'] ?? [] as $line) {
                if (empty($line['product_batch_id'])) {
                    continue;
                }

                $stockTransfer->lines()->create([
                    'product_batch_id' => $line['product_batch_id'],
                    'qty' => $line['qty'] ?? null,
                ]);
            }

            return $stockTransfer;
        });
    }

    /**
     * Turn a draft into a real, posted Stock Transfer — the one moment its
     * stock actually moves. Replaces the draft's lines with the final
     * values, then runs the same stock-moving logic createStockTransfer()
     * uses.
     */
    public function finalizeDraft(StockTransfer $draft, array $data, ?int $userId = null): StockTransfer
    {
        return DB::transaction(function () use ($draft, $data, $userId) {
            $draft->fill([
                'date' => $data['date'],
                'prepared_by' => $data['prepared_by'] ?? null,
                'status' => 'posted',
            ]);
            $draft->save();

            $draft->lines()->delete();
            $this->applyLines($draft, $data, $userId);

            return $draft;
        });
    }

    /**
     * Resolve From/To locations, move each line's stock between them, and
     * record the lines — shared by createStockTransfer() and
     * finalizeDraft() so this logic exists in exactly one place.
     */
    protected function applyLines(StockTransfer $stockTransfer, array $data, ?int $userId = null): void
    {
        if ((int) $data['from_location_id'] === (int) $data['to_location_id']) {
            throw ValidationException::withMessages([
                'to_location_id' => 'From and To locations must be different.',
            ]);
        }

        $from = Location::findOrFail($data['from_location_id']);
        $to = Location::findOrFail($data['to_location_id']);

        $stockTransfer->update([
            'from_location_id' => $from->id,
            'to_location_id' => $to->id,
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

            $this->stockService->transfer($batch, $qty, $from, $to, "Stock Transfer {$stockTransfer->reference}", $userId, $stockTransfer);

            $stockTransfer->lines()->create([
                'product_batch_id' => $batch->id,
                'qty' => $qty,
            ]);
        }
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
