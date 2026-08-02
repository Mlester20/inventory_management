<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Location;
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
            'amount_tendered' => 'required|numeric|min:0',
        ]);

        // The cart's own line totals already sum to what's owed — validate
        // the cash tendered against that before touching stock/DB, same
        // trust boundary the rest of this method already applies to
        // unit_price/total_price coming from the cart.
        $cartTotal = round(collect($validated['items'])->sum('total_price'), 2);
        $amountTendered = round((float) $validated['amount_tendered'], 2);

        if ($amountTendered < $cartTotal) {
            return response()->json([
                'success' => false,
                'message' => "Amount tendered (₱{$amountTendered}) is less than the total due (₱{$cartTotal}).",
            ], 422);
        }

        try {
            $purchaseCount = 0;
            $transactionId = uniqid('TXN-');
            $totalAmount = 0;
            $changeAmount = 0;

            \DB::transaction(function () use ($validated, &$purchaseCount, $transactionId, &$totalAmount, &$changeAmount, $amountTendered) {
                $stockService = new StockService();
                $userId = auth()->id();
                $posLocation = Location::pos();

                foreach ($validated['items'] as $cartItem) {
                    $product = Product::findOrFail($cartItem['product_id']);
                    $qty = (int) $cartItem['quantity'];

                    $movements = $stockService->deductFefo(
                        $product,
                        $qty,
                        $posLocation,
                        "POS Purchase (TXN: {$transactionId})",
                        $userId
                    );

                    foreach ($movements as $movement) {
                        $movementQty = abs($movement->quantity);
                        $lineTotal = round($cartItem['unit_price'] * $movementQty, 2);

                        Purchase::create([
                            'product_batch_id' => $movement->product_batch_id,
                            'user_id' => $userId,
                            'transaction_id' => $transactionId,
                            'quantity_sold' => $movementQty,
                            'unit_price' => $cartItem['unit_price'],
                            'total_price' => $lineTotal,
                            'purchase_date' => now(),
                        ]);

                        $purchaseCount++;
                        $totalAmount += $lineTotal;
                    }
                }

                // Tendered/change apply to the whole checkout, not any single
                // line — stamped onto every row of this transaction once the
                // real (FEFO-split) total is known.
                $changeAmount = round($amountTendered - $totalAmount, 2);
                Purchase::where('transaction_id', $transactionId)->update([
                    'amount_tendered' => $amountTendered,
                    'change_amount' => $changeAmount,
                ]);
            });

            ActivityLog::record(
                module: 'POS',
                action: 'sale_completed',
                description: "Completed POS sale (TXN: {$transactionId}) — {$purchaseCount} line(s), ₱{$totalAmount}",
                metadata: [
                    'transaction_id' => $transactionId,
                    'line_count' => $purchaseCount,
                    'total_amount' => $totalAmount,
                    'amount_tendered' => $amountTendered,
                    'change_amount' => $changeAmount,
                ],
                source: ActivityLog::SOURCE_POS,
            );

            return response()->json([
                'success' => true,
                'transaction_id' => $transactionId,
                'message' => 'Purchase completed successfully',
                'purchase_count' => $purchaseCount,
                'total_amount' => $totalAmount,
                'amount_tendered' => $amountTendered,
                'change_amount' => $changeAmount,
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
