<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    /**
     * Create a new purchase transaction with stock deduction (FEFO
     * auto-selects which batch(es) each product's stock comes from, since
     * POS has no batch-picker UI).
     * Note: user_id tracks the cashier/staff member who processed the sale
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
        ]);

        try {
            $purchaseCount = 0;
            $transactionId = uniqid('TXN-');
            $totalAmount = 0;

            \DB::transaction(function () use ($validated, &$purchaseCount, $transactionId, &$totalAmount) {
                $stockService = new StockService();
                $userId = auth()->id();

                foreach ($validated['items'] as $cartItem) {
                    $product = Product::findOrFail($cartItem['product_id']);
                    $qty = (int) $cartItem['quantity'];

                    $movements = $stockService->deductFefo(
                        $product,
                        $qty,
                        "POS Purchase (TXN: {$transactionId})",
                        $userId
                    );

                    foreach ($movements as $movement) {
                        $movementQty = abs($movement->quantity);
                        $lineTotal = round($cartItem['unit_price'] * $movementQty, 2);

                        Purchase::create([
                            'product_batch_id' => $movement->product_batch_id,
                            'user_id' => $userId,
                            'quantity_sold' => $movementQty,
                            'unit_price' => $cartItem['unit_price'],
                            'total_price' => $lineTotal,
                            'purchase_date' => now(),
                        ]);

                        $purchaseCount++;
                        $totalAmount += $lineTotal;
                    }
                }
            });

            return response()->json([
                'success' => true,
                'transaction_id' => $transactionId,
                'message' => 'Purchase completed successfully',
                'purchase_count' => $purchaseCount,
                'total_amount' => $totalAmount,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->errors()['quantity'][0] ?? 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Purchase Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Purchase failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get purchase history for logged-in user
     */
    public function history()
    {
        $userId = auth()->id();

        $purchases = Purchase::with('productBatch.product.category', 'user')
            ->where('user_id', $userId)
            ->orderBy('purchase_date', 'desc')
            ->paginate(15);

        return response()->json($purchases);
    }
}
