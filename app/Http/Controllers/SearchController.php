<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class SearchController extends Controller
{
    /**
     * Build a MySQL boolean-mode MATCH AGAINST search string with a trailing
     * wildcard per word, so each word is prefix-matched and all words are
     * required (AND semantics) — e.g. "juan dela" -> "+juan* +dela*".
     */
    private function booleanModeTerm(string $query): string
    {
        $words = preg_split('/\s+/', trim($query));
        $words = array_filter($words, fn ($w) => $w !== '');

        return implode(' ', array_map(function ($word) {
            $escaped = str_replace(['+', '-', '*', '"', '(', ')', '~', '<', '>', '@'], ' ', $word);
            return '+' . $escaped . '*';
        }, $words));
    }

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
                        'customers' => [],
                        'suppliers' => [],
                        'categories' => [],
                    ],
                    'total' => 0
                ]);
            }

            $useFullText = strlen($query) >= 3;
            $booleanTerm = $useFullText ? $this->booleanModeTerm($query) : null;

            // Search for products
            $itemsQuery = Product::withSum('locationStocks', 'qty');
            if ($useFullText) {
                $itemsQuery->whereRaw('MATCH(item_name, description) AGAINST(? IN BOOLEAN MODE)', [$booleanTerm])
                    ->orderByRaw('MATCH(item_name, description) AGAINST(? IN BOOLEAN MODE) DESC', [$booleanTerm]);
            } else {
                $itemsQuery->where(function (Builder $q) use ($query) {
                    $q->where('item_name', 'LIKE', "%{$query}%")
                        ->orWhere('description', 'LIKE', "%{$query}%");
                });
            }
            $items = $itemsQuery->limit(5)
                ->get(['id', 'item_name', 'unit_price'])
                ->map(function($item) {
                    return [
                        'type' => 'item',
                        'id' => $item->id,
                        'title' => $item->item_name,
                        'subtitle' => 'Stock: ' . (int) ($item->location_stocks_sum_qty ?? 0) . ' | ₱' . number_format($item->unit_price, 2),
                        'url' => route('admin.items.show', $item->id),
                        'icon' => 'bx-package'
                    ];
                });

            // Search for purchases (Sales) — stays on LIKE; product-name search goes
            // through a relation, so a single-table FULLTEXT index doesn't apply here.
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
                        'url' => route('purchases.index'),
                        'icon' => 'bx-receipt'
                    ];
                });

            // Search for customers
            $customersQuery = Customer::query();
            if ($useFullText) {
                $customersQuery->whereRaw('MATCH(customer_name, contact_person, email, contact_number) AGAINST(? IN BOOLEAN MODE)', [$booleanTerm])
                    ->orderByRaw('MATCH(customer_name, contact_person, email, contact_number) AGAINST(? IN BOOLEAN MODE) DESC', [$booleanTerm]);
            } else {
                $customersQuery->where(function (Builder $q) use ($query) {
                    $q->where('customer_name', 'LIKE', "%{$query}%")
                        ->orWhere('contact_person', 'LIKE', "%{$query}%")
                        ->orWhere('email', 'LIKE', "%{$query}%")
                        ->orWhere('contact_number', 'LIKE', "%{$query}%");
                });
            }
            $customers = $customersQuery->limit(5)
                ->get(['id', 'customer_name', 'contact_person', 'email', 'contact_number'])
                ->map(function($customer) {
                    return [
                        'type' => 'customer',
                        'id' => $customer->id,
                        'title' => $customer->customer_name,
                        'subtitle' => ($customer->contact_person ?? 'N/A') . ' | ' . ($customer->email ?? 'N/A'),
                        'url' => route('customers.index'),
                        'icon' => 'bx-user-circle'
                    ];
                });

            // Search for suppliers
            $suppliersQuery = Supplier::query();
            if ($useFullText) {
                $suppliersQuery->whereRaw('MATCH(supplier_name, contact_person, email, contact_number) AGAINST(? IN BOOLEAN MODE)', [$booleanTerm])
                    ->orderByRaw('MATCH(supplier_name, contact_person, email, contact_number) AGAINST(? IN BOOLEAN MODE) DESC', [$booleanTerm]);
            } else {
                $suppliersQuery->where(function (Builder $q) use ($query) {
                    $q->where('supplier_name', 'LIKE', "%{$query}%")
                        ->orWhere('contact_person', 'LIKE', "%{$query}%")
                        ->orWhere('email', 'LIKE', "%{$query}%")
                        ->orWhere('contact_number', 'LIKE', "%{$query}%");
                });
            }
            $suppliers = $suppliersQuery->limit(5)
                ->get(['id', 'supplier_name', 'contact_person', 'email', 'contact_number'])
                ->map(function($supplier) {
                    return [
                        'type' => 'supplier',
                        'id' => $supplier->id,
                        'title' => $supplier->supplier_name,
                        'subtitle' => ($supplier->contact_person ?? 'N/A') . ' | ' . ($supplier->email ?? 'N/A'),
                        'url' => route('suppliers.index'),
                        'icon' => 'bx-user'
                    ];
                });

            // Search for categories
            $categoriesQuery = Category::query();
            if ($useFullText) {
                $categoriesQuery->whereRaw('MATCH(category_name) AGAINST(? IN BOOLEAN MODE)', [$booleanTerm])
                    ->orderByRaw('MATCH(category_name) AGAINST(? IN BOOLEAN MODE) DESC', [$booleanTerm]);
            } else {
                $categoriesQuery->where('category_name', 'LIKE', "%{$query}%");
            }
            $categories = $categoriesQuery->limit(5)
                ->get(['id', 'category_name'])
                ->map(function($category) {
                    return [
                        'type' => 'category',
                        'id' => $category->id,
                        'title' => $category->category_name,
                        'subtitle' => 'Category',
                        'url' => route('categories.index'),
                        'icon' => 'bx-receipt'
                    ];
                });

            return response()->json([
                'results' => [
                    'items' => $items->toArray(),
                    'purchases' => $purchases->toArray(),
                    'customers' => $customers->toArray(),
                    'suppliers' => $suppliers->toArray(),
                    'categories' => $categories->toArray(),
                ],
                'total' => $items->count() + $purchases->count() + $customers->count() + $suppliers->count() + $categories->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Search failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
