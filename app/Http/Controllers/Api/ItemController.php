<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    /**
     * Get all items with available stock for POS
     */
    public function index()
    {
        $items = Item::with('category', 'supplier')
            ->where('quantity', '>', 0)
            ->orderBy('item_name')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'item_name' => $item->item_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
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
     * Get a specific item
     */
    public function show(Item $item)
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
