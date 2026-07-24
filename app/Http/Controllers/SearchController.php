<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Category;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        try {
            $query = $request->input('q', '');
            $query = trim($query);

            if (strlen($query) < 2) {
                return response()->json([
                    'results' => [
                        'items' => [],
                        'purchases' => [],
                        'suppliers' => [],
                        'categories' => [],
                    ],
                    'total' => 0
                ]);
            }

            // Search for products
            $items = Product::withSum('batches', 'qty')
                ->where('item_name', 'LIKE', "%{$query}%")
                ->orWhere('description', 'LIKE', "%{$query}%")
                ->limit(5)
                ->get(['id', 'item_name', 'unit_price'])
                ->map(function($item) {
                    return [
                        'type' => 'item',
                        'id' => $item->id,
                        'title' => $item->item_name,
                        'subtitle' => 'Stock: ' . (int) ($item->batches_sum_qty ?? 0) . ' | ₱' . number_format($item->unit_price, 2),
                        'url' => route('admin.items.show', $item->id),
                        'icon' => 'bx-package'
                    ];
                });

            // Search for purchases (Sales)
            $purchases = Purchase::whereHas('productBatch.product', function($q) use ($query) {
                $q->where('item_name', 'LIKE', "%{$query}%");
            })
                ->orWhere('id', 'LIKE', "%{$query}%")
                ->limit(5)
                ->with('productBatch.product')
                ->get(['id', 'product_batch_id', 'quantity_sold', 'total_price', 'purchase_date'])
                ->map(function($purchase) {
                    return [
                        'type' => 'purchase',
                        'id' => $purchase->id,
                        'title' => 'Sale #' . $purchase->id . ' - ' . ($purchase->productBatch->product->item_name ?? 'N/A'),
                        'subtitle' => 'Qty: ' . $purchase->quantity_sold . ' | Total: ₱' . number_format($purchase->total_price, 2),
                        'url' => route('admin.purchases.show', $purchase->id),
                        'icon' => 'bx-receipt'
                    ];
                });

            // Search for suppliers
            $suppliers = Supplier::where('supplier_name', 'LIKE', "%{$query}%")
                ->orWhere('contact_person', 'LIKE', "%{$query}%")
                ->orWhere('email', 'LIKE', "%{$query}%")
                ->orWhere('contact_number', 'LIKE', "%{$query}%")
                ->limit(5)
                ->get(['id', 'supplier_name', 'contact_person', 'email', 'contact_number'])
                ->map(function($supplier) {
                    return [
                        'type' => 'supplier',
                        'id' => $supplier->id,
                        'title' => $supplier->supplier_name,
                        'subtitle' => ($supplier->contact_person ?? 'N/A') . ' | ' . ($supplier->email ?? 'N/A'),
                        'url' => route('admin.suppliers.show', $supplier->id),
                        'icon' => 'bx-user'
                    ];
                });

            // Search for categories
            $categories = Category::where('category_name', 'LIKE', "%{$query}%")
                ->limit(5)
                ->get(['id', 'category_name'])
                ->map(function($category) {
                    return [
                        'type' => 'category',
                        'id' => $category->id,
                        'title' => $category->category_name,
                        'subtitle' => 'Category',
                        'url' => route('admin.categories.show', $category->id),
                        'icon' => 'bx-receipt'
                    ];
                });

            return response()->json([
                'results' => [
                    'items' => $items->toArray(),
                    'purchases' => $purchases->toArray(),
                    'suppliers' => $suppliers->toArray(),
                    'categories' => $categories->toArray(),
                ],
                'total' => $items->count() + $purchases->count() + $suppliers->count() + $categories->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Search failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
