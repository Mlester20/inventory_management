<?php

namespace App\Services;

use App\Models\Item;
use App\Models\StockMovement;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;

class StockService
{
    /**
     * Restock an item by increasing its quantity.
     *
     * @param Item $item
     * @param int $quantity
     * @param string|null $remarks
     * @param int|null $userId
     * @return StockMovement The created stock movement record
     * @throws ValidationException
     */
    public function restock(Item $item, int $quantity, ?string $remarks = null, ?int $userId = null): StockMovement
    {
        // Validate quantity
        $this->validateQuantity($quantity, 'positive');

        // Use authenticated user if userId not provided
        if ($userId === null && Auth::check()) {
            $userId = Auth::id();
        }

        // Use database transaction for atomicity
        return \DB::transaction(function () use ($item, $quantity, $remarks, $userId) {
            // Update item quantity
            $item->increment('quantity', $quantity);

            // Create stock movement record
            $movement = StockMovement::create([
                'item_id' => $item->id,
                'user_id' => $userId,
                'quantity' => $quantity,
                'type' => 'in',
                'remarks' => $remarks,
            ]);

            return $movement;
        });
    }

    /**
     * Deduct stock from an item.
     *
     * @param Item $item
     * @param int $quantity
     * @param string|null $remarks
     * @param int|null $userId
     * @return StockMovement The created stock movement record
     * @throws ValidationException
     */
    public function deduct(Item $item, int $quantity, ?string $remarks = null, ?int $userId = null): StockMovement
    {
        // Validate quantity
        $this->validateQuantity($quantity, 'positive');

        // Check if sufficient stock available
        if ($item->quantity < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => "Insufficient stock. Available: {$item->quantity}, Requested: {$quantity}",
            ]);
        }

        // Use authenticated user if userId not provided
        if ($userId === null && Auth::check()) {
            $userId = Auth::id();
        }

        // Use database transaction for atomicity
        return \DB::transaction(function () use ($item, $quantity, $remarks, $userId) {
            // Update item quantity
            $item->decrement('quantity', $quantity);

            // Create stock movement record with negative quantity
            $movement = StockMovement::create([
                'item_id' => $item->id,
                'user_id' => $userId,
                'quantity' => -$quantity, // Negative value for deduction
                'type' => 'out',
                'remarks' => $remarks,
            ]);

            return $movement;
        });
    }

    /**
     * Adjust stock to a specific level.
     *
     * @param Item $item
     * @param int $newQuantity
     * @param string|null $remarks
     * @param int|null $userId
     * @return StockMovement|null The created stock movement record (null if no change)
     * @throws ValidationException
     */
    public function adjust(Item $item, int $newQuantity, ?string $remarks = null, ?int $userId = null): ?StockMovement
    {
        // Validate quantity is not negative
        if ($newQuantity < 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Stock quantity cannot be negative.',
            ]);
        }

        // Calculate difference
        $difference = $newQuantity - $item->quantity;

        // If no change needed
        if ($difference === 0) {
            return null;
        }

        // Use authenticated user if userId not provided
        if ($userId === null && Auth::check()) {
            $userId = Auth::id();
        }

        // Determine if this is a restock or deduction
        $type = $difference > 0 ? 'in' : 'out';
        $quantity = abs($difference);
        $displayQuantity = $type === 'in' ? $quantity : -$quantity;

        // Use database transaction for atomicity
        return \DB::transaction(function () use ($item, $newQuantity, $type, $displayQuantity, $remarks, $userId) {
            // Update item to new quantity
            $item->update(['quantity' => $newQuantity]);

            // Create stock movement record
            $movement = StockMovement::create([
                'item_id' => $item->id,
                'user_id' => $userId,
                'quantity' => $displayQuantity,
                'type' => $type,
                'remarks' => $remarks ?? "Stock adjustment to {$newQuantity}",
            ]);

            return $movement;
        });
    }

    /**
     * Get stock movement history for an item.
     *
     * @param Item $item
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getMovementHistory(Item $item, int $limit = 50)
    {
        return $item->stockMovements()
            ->with('user')
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get low stock items.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLowStockItems()
    {
        return Item::lowStock()
            ->with(['category', 'supplier'])
            ->get();
    }

    /**
     * Get out of stock items.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getOutOfStockItems()
    {
        return Item::outOfStock()
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
