<?php

namespace App\Services;

use App\Models\GenericName;
use App\Models\Location;
use App\Models\LocationStock;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class StockService
{
    /**
     * In-stock batches at a specific location (qty > 0), across every
     * brand/product, under a given Generic Name. Powers the Delivery
     * Receipt "Available Product?" picker and the Stock Transfer line
     * picker — both need "what can I move/deliver from location X right
     * now," just for different source locations.
     */
    public function getAvailableBatchesForGenericName(GenericName $genericName, Location $location)
    {
        return ProductBatch::query()
            ->whereHas('locationStocks', function ($q) use ($location) {
                $q->where('location_id', $location->id)->where('qty', '>', 0);
            })
            ->with(['product.supplier', 'locationStocks' => function ($q) use ($location) {
                $q->where('location_id', $location->id);
            }])
            ->whereHas('product', fn ($q) => $q->where('generic_name_id', $genericName->id))
            ->get();
    }

    /**
     * Restock a batch at a specific location by increasing its quantity.
     *
     * @param ProductBatch $batch
     * @param int $quantity
     * @param Location $location
     * @param string|null $remarks
     * @param int|null $userId
     * @param Model|null $source The transaction that caused this movement (GoodsReceipt, InventoryAdjustment, StockTransfer, ...)
     * @return StockMovement The created stock movement record
     * @throws ValidationException
     */
    public function restock(ProductBatch $batch, int $quantity, Location $location, ?string $remarks = null, ?int $userId = null, ?Model $source = null): StockMovement
    {
        $this->validateQuantity($quantity, 'positive');

        if ($userId === null && Auth::check()) {
            $userId = Auth::id();
        }

        return DB::transaction(function () use ($batch, $quantity, $location, $remarks, $userId, $source) {
            $locationStock = $this->getOrCreateLocationStock($batch, $location);
            $locationStock->increment('qty', $quantity);

            return StockMovement::create([
                'product_batch_id' => $batch->id,
                'location_id' => $location->id,
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
     * Deduct stock from a batch at a specific location.
     *
     * @param ProductBatch $batch
     * @param int $quantity
     * @param Location $location
     * @param string|null $remarks
     * @param int|null $userId
     * @param Model|null $source
     * @return StockMovement The created stock movement record
     * @throws ValidationException
     */
    public function deduct(ProductBatch $batch, int $quantity, Location $location, ?string $remarks = null, ?int $userId = null, ?Model $source = null): StockMovement
    {
        $this->validateQuantity($quantity, 'positive');

        if ($userId === null && Auth::check()) {
            $userId = Auth::id();
        }

        return DB::transaction(function () use ($batch, $quantity, $location, $remarks, $userId, $source) {
            $locationStock = $this->getOrCreateLocationStock($batch, $location);

            if ($locationStock->qty < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Insufficient stock at {$location->name}. Available: {$locationStock->qty}, Requested: {$quantity}",
                ]);
            }

            $locationStock->decrement('qty', $quantity);

            return StockMovement::create([
                'product_batch_id' => $batch->id,
                'location_id' => $location->id,
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
     * Deduct stock for a Product across its batches at a specific location,
     * oldest-expiring first (FEFO), auto-selecting batches when the caller
     * has no batch picker UI (POS Quick-Sale, Invoice checkout). Splits the
     * deduction across multiple batches if a single batch can't cover the
     * full quantity.
     *
     * @return StockMovement[] One movement per batch touched.
     * @throws ValidationException
     */
    public function deductFefo(Product $product, int $quantity, Location $location, ?string $remarks = null, ?int $userId = null, ?Model $source = null): array
    {
        $this->validateQuantity($quantity, 'positive');

        $batches = $product->batches()->availableFefo($location->id)->get();
        $available = (int) $batches->sum(fn (ProductBatch $b) => $b->qtyAtLocation($location->id));

        if ($available < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => "Insufficient stock at {$location->name}. Available: {$available}, Requested: {$quantity}",
            ]);
        }

        return DB::transaction(function () use ($batches, $quantity, $location, $remarks, $userId, $source) {
            $remaining = $quantity;
            $movements = [];

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min($remaining, $batch->qtyAtLocation($location->id));

                if ($take <= 0) {
                    continue;
                }

                $movements[] = $this->deduct($batch, $take, $location, $remarks, $userId, $source);
                $remaining -= $take;
            }

            return $movements;
        });
    }

    /**
     * Adjust a batch's stock at a specific location to an absolute level.
     *
     * @param ProductBatch $batch
     * @param int $newQuantity
     * @param Location $location
     * @param string|null $remarks
     * @param int|null $userId
     * @param Model|null $source
     * @return StockMovement|null The created stock movement record (null if no change)
     * @throws ValidationException
     */
    public function adjust(ProductBatch $batch, int $newQuantity, Location $location, ?string $remarks = null, ?int $userId = null, ?Model $source = null): ?StockMovement
    {
        if ($newQuantity < 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Stock quantity cannot be negative.',
            ]);
        }

        if ($userId === null && Auth::check()) {
            $userId = Auth::id();
        }

        return DB::transaction(function () use ($batch, $newQuantity, $location, $remarks, $userId, $source) {
            $locationStock = $this->getOrCreateLocationStock($batch, $location);
            $difference = $newQuantity - $locationStock->qty;

            if ($difference === 0) {
                return null;
            }

            $type = $difference > 0 ? 'in' : 'out';
            $quantity = abs($difference);
            $displayQuantity = $type === 'in' ? $quantity : -$quantity;

            $locationStock->update(['qty' => $newQuantity]);

            return StockMovement::create([
                'product_batch_id' => $batch->id,
                'location_id' => $location->id,
                'user_id' => $userId,
                'quantity' => $displayQuantity,
                'type' => $type,
                'remarks' => $remarks ?? "Stock adjustment to {$newQuantity} at {$location->name}",
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
            ]);
        });
    }

    /**
     * Move a batch's qty from one location to another — deducts at the
     * source and adds at the destination, both logged as separate movements
     * (same source, different location/type) so the transfer is fully
     * traceable per location in the Product History ledger. Doesn't create
     * or destroy stock, only moves it.
     *
     * @return StockMovement[] [$outMovement, $inMovement]
     * @throws ValidationException
     */
    public function transfer(ProductBatch $batch, int $quantity, Location $from, Location $to, ?string $remarks = null, ?int $userId = null, ?Model $source = null): array
    {
        $this->validateQuantity($quantity, 'positive');

        return DB::transaction(function () use ($batch, $quantity, $from, $to, $remarks, $userId, $source) {
            $out = $this->deduct($batch, $quantity, $from, $remarks, $userId, $source);
            $in = $this->restock($batch, $quantity, $to, $remarks, $userId, $source);

            return [$out, $in];
        });
    }

    /**
     * The (location, batch) stock row, creating an empty one on first touch.
     */
    private function getOrCreateLocationStock(ProductBatch $batch, Location $location): LocationStock
    {
        return LocationStock::firstOrCreate(
            ['product_batch_id' => $batch->id, 'location_id' => $location->id],
            ['qty' => 0, 'reserved_qty' => 0]
        );
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
            ->with('user', 'location')
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get low stock products (evaluated against total on-hand qty across
     * all locations).
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
     * Get out of stock products (total on-hand qty across all locations).
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
