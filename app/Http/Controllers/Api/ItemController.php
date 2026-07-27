<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    /**
     * Get all products with available stock for POS
     */
    public function index()
    {
        $items = Product::with('category', 'supplier')
            ->withSum('batches', 'qty')
            ->get()
            ->filter(fn (Product $item) => ($item->batches_sum_qty ?? 0) > 0)
            ->values()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'item_name' => $item->item_name,
                    'quantity' => (int) ($item->batches_sum_qty ?? 0),
                    'unit_price' => $item->unit_price,
                    'taxable' => $item->tax_id !== null,
                    'category' => [
                        'id' => $item->category->id,
                        'category_name' => $item->category->category_name,
                    ],
                    'supplier' => $item->supplier ? [
                        'id' => $item->supplier->id,
                        'supplier_name' => $item->supplier->supplier_name,
                    ] : null,
                ];
            });

        return response()->json($items);
    }

    /**
     * Look up a product by barcode for the POS Walk-in barcode scanner.
     * Same JSON shape as index() (no cost field) — batch/FEFO selection is
     * deferred to checkout (StockService::deductFefo), so this only needs
     * to confirm the product exists and has any stock at all.
     */
    public function findByBarcode(string $barcode)
    {
        $item = Product::with('category', 'supplier')
            ->withSum('batches', 'qty')
            ->where('barcode', $barcode)
            ->first();

        if (! $item) {
            return response()->json(['message' => 'Barcode not recognized'], 404);
        }

        $quantity = (int) ($item->batches_sum_qty ?? 0);

        if ($quantity <= 0) {
            return response()->json(['message' => "{$item->item_name} is out of stock"], 409);
        }

        return response()->json([
            'id' => $item->id,
            'item_name' => $item->item_name,
            'quantity' => $quantity,
            'unit_price' => $item->unit_price,
            'taxable' => $item->tax_id !== null,
            'category' => [
                'id' => $item->category->id,
                'category_name' => $item->category->category_name,
            ],
            'supplier' => $item->supplier ? [
                'id' => $item->supplier->id,
                'supplier_name' => $item->supplier->supplier_name,
            ] : null,
        ]);
    }

    /**
     * Get a specific product
     */
    public function show(Product $item)
    {
        return response()->json([
            'id' => $item->id,
            'item_name' => $item->item_name,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'low_stock_threshold' => $item->low_stock_threshold,
            'category' => $item->category,
            'supplier' => $item->supplier,
        ]);
    }
}
