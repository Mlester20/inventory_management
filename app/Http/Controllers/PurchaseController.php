<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Purchase;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class PurchaseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $purchases = Purchase::with('productBatch.product')->latest('purchase_date')->paginate(15);
        $products = Product::all();
        return view('admin.purchases', compact('purchases', 'products'));
    }

    /**
     * Store a newly created resource in storage.
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

            \DB::transaction(function () use ($validated, &$purchaseCount, $transactionId) {
                $stockService = new StockService();
                $userId = auth()->id();

                foreach ($validated['items'] as $cartItem) {
                    $product = Product::findOrFail($cartItem['product_id']);
                    $qty = (int) $cartItem['quantity'];

                    $movements = $stockService->deductFefo(
                        $product,
                        $qty,
                        Location::pos(),
                        "Purchase by user (TXN: {$transactionId})",
                        $userId
                    );

                    foreach ($movements as $movement) {
                        $movementQty = abs($movement->quantity);

                        Purchase::create([
                            'product_batch_id' => $movement->product_batch_id,
                            'user_id' => $userId,
                            'quantity_sold' => $movementQty,
                            'unit_price' => $cartItem['unit_price'],
                            'total_price' => round($cartItem['unit_price'] * $movementQty, 2),
                            'purchase_date' => now(),
                        ]);

                        $purchaseCount++;
                    }
                }
            });

            return response()->json([
                'success' => true,
                'transaction_id' => $transactionId,
                'message' => 'Purchase completed successfully',
                'purchases' => $purchaseCount,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->errors()['quantity'][0] ?? 'Validation failed',
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Purchase Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Purchase failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Purchase $purchase)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Purchase $purchase)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Purchase $purchase)
    {
        $purchase->delete();
        Alert::success('Success', 'Purchase deleted successfully');
        return redirect()->route('purchases.index');
    }
}
