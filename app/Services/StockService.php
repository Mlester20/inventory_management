<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;

class StockService
{
    /**
     * Restock a batch by increasing its quantity.
     *
     * @param ProductBatch $batch
     * @param int $quantity
     * @param string|null $remarks
     * @param int|null $userId
     * @param Model|null $source The transaction that caused this movement (GoodsReceipt, InventoryAdjustment, ...)
     * @return StockMovement The created stock movement record
     * @throws ValidationException
     */
    public function restock(ProductBatch $batch, int $quantity, ?string $remarks = null, ?int $userId = null, ?Model $source = null): StockMovement
    {
        $this->validateQuantity($quantity, 'positive');

        if ($userId === null && Auth::check()) {
            $userId = Auth::id();
        }

        return \DB::transaction(function () use ($batch, $quantity, $remarks, $userId, $source) {
            $batch->increment('qty', $quantity);

            return StockMovement::create([
                'product_batch_id' => $batch->id,
                'user_id' => $userId,
                'quantity' => $quantity,
                'type' => 'in',
                'remarks' => $remarks,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
            ]);
        });
    }

    /**
     * Deduct stock from a batch.
     *
     * @param ProductBatch $batch
     * @param int $quantity
     * @param string|null $remarks
     * @param int|null $userId
     * @param Model|null $source
     * @return StockMovement The created stock movement record
     * @throws ValidationException
     */
    public function deduct(ProductBatch $batch, int $quantity, ?string $remarks = null, ?int $userId = null, ?Model $source = null): StockMovement
    {
        $this->validateQuantity($quantity, 'positive');

        if ($batch->qty < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => "Insufficient stock. Available: {$batch->qty}, Requested: {$quantity}",
            ]);
        }

        if ($userId === null && Auth::check()) {
            $userId = Auth::id();
        }

        return \DB::transaction(function () use ($batch, $quantity, $remarks, $userId, $source) {
            $batch->decrement('qty', $quantity);

            return StockMovement::create([
                'product_batch_id' => $batch->id,
                'user_id' => $userId,
                'quantity' => -$quantity,
                'type' => 'out',
                'remarks' => $remarks,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
            ]);
        });
    }

    /**
     * Deduct stock for a Product across its batches, oldest-expiring first
     * (FEFO), auto-selecting batches when the caller has no batch picker UI
     * (POS Quick-Sale, Invoice checkout). Splits the deduction across
     * multiple batches if a single batch can't cover the full quantity.
     *
     * @return StockMovement[] One movement per batch touched.
     * @throws ValidationException
     */
    public function deductFefo(Product $product, int $quantity, ?string $remarks = null, ?int $userId = null, ?Model $source = null): array
    {
        $this->validateQuantity($quantity, 'positive');

        $batches = $product->batches()->availableFefo()->get();
        $available = (int) $batches->sum('qty');

        if ($available < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => "Insufficient stock. Available: {$available}, Requested: {$quantity}",
            ]);
        }

        return \DB::transaction(function () use ($batches, $quantity, $remarks, $userId, $source) {
            $remaining = $quantity;
            $movements = [];

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min($remaining, $batch->qty);
                $movements[] = $this->deduct($batch, $take, $remarks, $userId, $source);
                $remaining -= $take;
            }

            return $movements;
        });
    }

    /**
     * Adjust a batch's stock to a specific level.
     *
     * @param ProductBatch $batch
     * @param int $newQuantity
     * @param string|null $remarks
     * @param int|null $userId
     * @param Model|null $source
     * @return StockMovement|null The created stock movement record (null if no change)
     * @throws ValidationException
     */
    public function adjust(ProductBatch $batch, int $newQuantity, ?string $remarks = null, ?int $userId = null, ?Model $source = null): ?StockMovement
    {
        if ($newQuantity < 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Stock quantity cannot be negative.',
            ]);
        }

        $difference = $newQuantity - $batch->qty;

        if ($difference === 0) {
            return null;
        }

        if ($userId === null && Auth::check()) {
            $userId = Auth::id();
        }

        $type = $difference > 0 ? 'in' : 'out';
        $quantity = abs($difference);
        $displayQuantity = $type === 'in' ? $quantity : -$quantity;

        return \DB::transaction(function () use ($batch, $newQuantity, $type, $displayQuantity, $remarks, $userId, $source) {
            $batch->update(['qty' => $newQuantity]);

            return StockMovement::create([
                'product_batch_id' => $batch->id,
                'user_id' => $userId,
                'quantity' => $displayQuantity,
                'type' => $type,
                'remarks' => $remarks ?? "Stock adjustment to {$newQuantity}",
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
            ]);
        });
    }

    /**
     * Get stock movement history for a batch.
     *
     * @param ProductBatch $batch
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getMovementHistory(ProductBatch $batch, int $limit = 50)
    {
        return $batch->stockMovements()
            ->with('user')
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get low stock products.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLowStockItems()
    {
        return Product::lowStock()
            ->with(['category', 'supplier'])
            ->get();
    }

    /**
     * Get out of stock products.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getOutOfStockItems()
    {
        return Product::outOfStock()
            ->with(['category', 'supplier'])
            ->get();
    }

    /**
     * Validate quantity input.
     *
     * @param int $quantity
     * @param string $type 'positive' or 'non-negative'
     * @throws ValidationException
     */
    private function validateQuantity(int $quantity, string $type = 'positive'): void
    {
        if ($type === 'positive' && $quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be a positive integer.',
            ]);
        }

        if ($type === 'non-negative' && $quantity < 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity cannot be negative.',
            ]);
        }
    }
}
