<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class StockController extends Controller
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Display the restock page with all items.
     */
    public function restockPage(): View
    {
        $items = Item::with(['category', 'supplier'])
            ->orderBy('quantity', 'asc')
            ->get();

        return view('admin.stock.restock', compact('items'));
    }

    /**
     * Display stock movement history for an item.
     */
    public function history(Item $item): JsonResponse|View
    {
        $movements = $this->stockService->getMovementHistory($item);

        return response()->json([
            'item' => $item->load(['category', 'supplier']),
            'movements' => $movements,
            'summary' => [
                'current_stock' => $item->quantity,
                'is_low_stock' => $item->isLowOnStock(),
                'low_stock_threshold' => $item->low_stock_threshold,
            ],
        ]);
    }

    /**
     * Restock an item.
     */
    public function restock(Request $request, Item $item)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'remarks' => 'nullable|string|max:255',
        ]);

        try {
            $movement = $this->stockService->restock(
                $item,
                $validated['quantity'],
                $validated['remarks'] ?? null
            );

            // Check if request expects JSON or HTML redirect
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Item restocked successfully. New quantity: {$item->quantity}",
                    'movement' => $movement->load('user'),
                    'item' => $item,
                ], 200);
            }

            // For web forms, redirect back with success message
            return redirect()->route('stock.restock-page')
                ->with('success', "Item restocked successfully! New quantity: {$item->quantity}");
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Deduct stock from an item.
     */
    public function deduct(Request $request, Item $item): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'remarks' => 'nullable|string|max:255',
        ]);

        try {
            $movement = $this->stockService->deduct(
                $item,
                $validated['quantity'],
                $validated['remarks'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => "Stock deducted successfully. New quantity: {$item->quantity}",
                'movement' => $movement->load('user'),
                'item' => $item,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Adjust stock to a specific level.
     */
    public function adjust(Request $request, Item $item): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
            'remarks' => 'nullable|string|max:255',
        ]);

        try {
            $movement = $this->stockService->adjust(
                $item,
                $validated['quantity'],
                $validated['remarks'] ?? null
            );

            if ($movement === null) {
                return response()->json([
                    'success' => true,
                    'message' => 'No stock adjustment needed. Quantity is already at the specified level.',
                    'item' => $item,
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => "Stock adjusted to {$item->quantity}",
                'movement' => $movement->load('user'),
                'item' => $item,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Get low stock items.
     */
    public function lowStockItems(): JsonResponse
    {
        $items = $this->stockService->getLowStockItems();

        return response()->json([
            'count' => $items->count(),
            'items' => $items,
        ], 200);
    }

    /**
     * Get out of stock items.
     */
    public function outOfStockItems(): JsonResponse
    {
        $items = $this->stockService->getOutOfStockItems();

        return response()->json([
            'count' => $items->count(),
            'items' => $items,
        ], 200);
    }

    /**
     * Get detailed stock report for an item.
     */
    public function report(Item $item): JsonResponse
    {
        $movements = $item->stockMovements()
            ->with('user')
            ->get();

        $stockInTotal = $item->restockMovements()->sum('quantity');
        $stockOutTotal = abs($item->deductionMovements()->sum('quantity'));

        return response()->json([
            'item' => $item->load(['category', 'supplier']),
            'summary' => [
                'current_stock' => $item->quantity,
                'total_restocked' => $stockInTotal,
                'total_deducted' => $stockOutTotal,
                'is_low_stock' => $item->isLowOnStock(),
                'low_stock_threshold' => $item->low_stock_threshold,
                'unit_price' => $item->unit_price,
                'total_value' => $item->quantity * $item->unit_price,
            ],
            'movements' => $movements,
        ], 200);
    }
}
