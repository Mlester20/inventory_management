<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\GenericName;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Supplier;
use App\Models\Taxes;
use App\Services\InventoryReportService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class InventoryItemsController extends Controller
{
    public function __construct(protected InventoryReportService $inventoryReportService) {}

    /**
     * The unified Products & Inventory module: General Item / Products /
     * Lot-Serial & Expiry / Product History tabs, matching the client
     * mockup's single tabbed screen.
     */
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'general');
        $search = $request->query('search');

        $categories = Category::orderBy('category_name')->get();
        $genericNames = GenericName::with('category')->orderBy('generic_name')->get();
        $suppliers = Supplier::orderBy('supplier_name')->get();

        $assignedTaxIds = Product::whereNotNull('tax_id')->pluck('tax_id')->unique();
        $taxes = Taxes::where('is_active', true)
            ->orWhereIn('id', $assignedTaxIds)
            ->orderBy('name')
            ->get();

        $generalItems = null;
        $products = null;
        $batches = null;
        $batchTotals = null;
        $historyProduct = null;
        $history = null;
        $nextGenericCode = null;
        $nextProductCode = null;

        if ($tab === 'general') {
            $generalItems = GenericName::with('category')
                ->withCount('products')
                ->when($search, fn ($q) => $q->where('generic_name', 'like', "%{$search}%"))
                ->orderBy('generic_name')
                ->paginate(15)
                ->withQueryString();

            foreach ($generalItems as $genericName) {
                $genericName->on_hand_qty = (int) ProductBatch::query()
                    ->join('products', 'products.id', '=', 'product_batches.product_id')
                    ->where('products.generic_name_id', $genericName->id)
                    ->sum('product_batches.qty');
            }

            $nextGenericCode = str_pad((string) (GenericName::max('id') + 1), 5, '0', STR_PAD_LEFT);
        }

        if ($tab === 'products') {
            $products = Product::with(['genericName', 'category'])
                ->withSum('batches', 'qty')
                ->withSum('batches as reserved_sum', 'reserved_qty')
                ->when($search, function ($q) use ($search) {
                    $q->where('item_name', 'like', "%{$search}%")
                        ->orWhere('brand_name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                })
                ->orderBy('item_name')
                ->paginate(15)
                ->withQueryString();

            $nextProductCode = str_pad((string) (Product::max('id') + 1), 5, '0', STR_PAD_LEFT);
        }

        if ($tab === 'batches') {
            $batchQuery = fn () => ProductBatch::query()
                ->whereHas('product', function ($q) use ($search) {
                    if ($search) {
                        $q->where('item_name', 'like', "%{$search}%")
                            ->orWhere('brand_name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    }
                });

            $batches = $batchQuery()
                ->with('product.category')
                ->orderBy('product_id')
                ->orderBy('expiration_date')
                ->paginate(15)
                ->withQueryString();

            // Grand totals across every matching batch, not just the current
            // page, so the footer row stays accurate under pagination.
            $batchTotals = $batchQuery()->selectRaw('
                COALESCE(SUM(qty), 0) as qty,
                COALESCE(SUM(reserved_qty), 0) as reserved_qty
            ')->first();
        }

        if ($tab === 'history') {
            $productId = $request->query('product_id');

            if ($productId) {
                $historyProduct = Product::find($productId);
            } elseif ($search) {
                $historyProduct = Product::where('item_name', 'like', "%{$search}%")
                    ->orWhere('brand_name', 'like', "%{$search}%")
                    ->first();
            }

            if ($historyProduct) {
                $fullHistory = $this->inventoryReportService->getProductHistory($historyProduct->id);

                // Manual pagination: the running balance must be computed
                // over the full, ordered movement list before slicing, so
                // this can't just be a paginated query like the other tabs.
                $page = (int) $request->query('page', 1);
                $perPage = 15;

                $history = new LengthAwarePaginator(
                    $fullHistory->forPage($page, $perPage),
                    $fullHistory->count(),
                    $perPage,
                    $page,
                    ['path' => $request->url(), 'query' => $request->query()]
                );
            }
        }

        return view('admin.inventory-items.index', compact(
            'tab', 'search', 'categories', 'genericNames', 'suppliers', 'taxes',
            'generalItems', 'products', 'batches', 'batchTotals', 'historyProduct', 'history',
            'nextGenericCode', 'nextProductCode'
        ));
    }
}
