<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Product;

class ItemController extends Controller
{
    /**
     * Get all products with available stock at the POS location. POS only
     * ever sees what's been transferred there — the Warehouse total (or
     * any other location's stock) is never sellable from here.
     */
    public function index()
    {
        $posLocationId = Location::pos()->id;

        $items = Product::with('category', 'supplier')
            ->withSum(['locationStocks as pos_qty' => fn ($q) => $q->where('location_id', $posLocationId)], 'qty')
            ->get()
            ->filter(fn (Product $item) => ($item->pos_qty ?? 0) > 0)
            ->values()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'item_name' => $item->item_name,
                    'quantity' => (int) ($item->pos_qty ?? 0),
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
     * to confirm the product exists and has any stock at the POS location.
     */
    public function findByBarcode(string $barcode)
    {
        $posLocationId = Location::pos()->id;

        $item = Product::with('category', 'supplier')
            ->withSum(['locationStocks as pos_qty' => fn ($q) => $q->where('location_id', $posLocationId)], 'qty')
            ->where('barcode', $barcode)
            ->first();

        if (! $item) {
            return response()->json(['message' => 'Barcode not recognized'], 404);
        }

        $quantity = (int) ($item->pos_qty ?? 0);

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
     * Get a specific product's POS-location stock.
     */
    public function show(Product $item)
    {
        return response()->json([
            'id' => $item->id,
            'item_name' => $item->item_name,
            'quantity' => $item->quantityAtLocation(Location::pos()->id),
            'unit_price' => $item->unit_price,
            'low_stock_threshold' => $item->low_stock_threshold,
            'category' => $item->category,
            'supplier' => $item->supplier,
        ]);
    }
}
