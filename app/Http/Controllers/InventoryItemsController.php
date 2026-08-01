<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\GenericName;
use App\Models\Location;
use App\Models\LocationStock;
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
        $warehouseId = Location::warehouse()->id;
        $posId = Location::pos()->id;

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
                $genericName->on_hand_qty = (int) LocationStock::query()
                    ->join('product_batches', 'product_batches.id', '=', 'location_stocks.product_batch_id')
                    ->join('products', 'products.id', '=', 'product_batches.product_id')
                    ->where('products.generic_name_id', $genericName->id)
                    ->sum('location_stocks.qty');
            }

            $nextGenericCode = str_pad((string) (GenericName::max('id') + 1), 5, '0', STR_PAD_LEFT);
        }

        if ($tab === 'products') {
            $products = Product::with(['genericName', 'category'])
                ->withSum(['locationStocks as warehouse_qty' => fn ($q) => $q->where('location_id', $warehouseId)], 'qty')
                ->withSum(['locationStocks as pos_qty' => fn ($q) => $q->where('location_id', $posId)], 'qty')
                ->withSum('locationStocks as total_qty', 'qty')
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

        $showZero = false;

        if ($tab === 'batches') {
            $showZero = $request->boolean('show_zero');

            // Batches fully depleted (disposed of, sold out, etc.) are
            // hidden by default to keep this tab focused on active stock —
            // the toggle exposes the full history including zero-qty rows.
            $batchQuery = fn () => ProductBatch::query()
                ->whereHas('product', function ($q) use ($search) {
                    if ($search) {
                        $q->where('item_name', 'like', "%{$search}%")
                            ->orWhere('brand_name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    }
                })
                ->when(! $showZero, function ($q) {
                    $q->whereHas('locationStocks', fn ($q2) => $q2->where('qty', '>', 0));
                });

            $batches = $batchQuery()
                ->with(['product.category', 'locationStocks'])
                ->orderBy('product_id')
                ->orderBy('expiration_date')
                ->paginate(15)
                ->withQueryString();

            // Grand totals across every matching batch, not just the current
            // page, so the footer row stays accurate under pagination.
            $matchingBatchIds = $batchQuery()->pluck('product_batches.id');
            $batchTotals = (object) [
                'warehouse_qty' => (int) LocationStock::whereIn('product_batch_id', $matchingBatchIds)->where('location_id', $warehouseId)->sum('qty'),
                'pos_qty' => (int) LocationStock::whereIn('product_batch_id', $matchingBatchIds)->where('location_id', $posId)->sum('qty'),
                'total_qty' => (int) LocationStock::whereIn('product_batch_id', $matchingBatchIds)->sum('qty'),
            ];
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
            'nextGenericCode', 'nextProductCode', 'warehouseId', 'posId', 'showZero'
        ));
    }
}
