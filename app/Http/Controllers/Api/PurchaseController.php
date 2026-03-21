<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\Item;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    /**
     * Create a new purchase transaction with stock deduction
     * Note: user_id tracks the cashier/staff member who processed the sale
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
        ]);

        try {
            $purchases = [];
            $transactionId = uniqid('TXN-');
            $totalAmount = 0;

            \DB::transaction(function () use ($validated, &$purchases, $transactionId, &$totalAmount) {
                $stockService = new StockService();
                $userId = auth()->id();

                foreach ($validated['items'] as $cartItem) {
                    $item = Item::findOrFail($cartItem['item_id']);

                    // Check stock availability
                    if ($item->quantity < $cartItem['quantity']) {
                        throw ValidationException::withMessages([
                            'stock' => "Insufficient stock for {$item->item_name}. Available: {$item->quantity}, Requested: {$cartItem['quantity']}",
                        ]);
                    }

                    // Create purchase record
                    $purchase = Purchase::create([
                        'item_id' => $item->id,
                        'user_id' => $userId,
                        'quantity_sold' => $cartItem['quantity'],
                        'unit_price' => $cartItem['unit_price'],
                        'total_price' => $cartItem['total_price'],
                        'purchase_date' => now(),
                    ]);

                    // Deduct stock using StockService
                    $stockService->deduct(
                        $item,
                        $cartItem['quantity'],
                        "POS Purchase (TXN: {$transactionId})",
                        $userId
                    );

                    $purchases[] = $purchase;
                    $totalAmount += $cartItem['total_price'];
                }
            });

            return response()->json([
                'success' => true,
                'transaction_id' => $transactionId,
                'message' => 'Purchase completed successfully',
                'purchase_count' => count($purchases),
                'total_amount' => $totalAmount,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->errors()['stock'][0] ?? 'Validation failed',
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
        
        $purchases = Purchase::with('item.category', 'user')
            ->where('user_id', $userId)
            ->orderBy('purchase_date', 'desc')
            ->paginate(15);

        return response()->json($purchases);
    }
}
