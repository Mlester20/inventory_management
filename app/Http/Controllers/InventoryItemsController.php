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
    protected const PER_PAGE = 10;

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
        // Flat lookup array for the Products tab's Generic Description
        // search box (New/Edit/Clone item modal) — built once here rather
        // than inline in the Blade @json() directive, since a closure
        // with array literals inside @json's parens trips Blade's
        // directive-argument parser.
        $genericNamesForJs = $genericNames->map(fn ($g) => [
            'id' => $g->id,
            'generic_name' => $g->generic_name,
            'unit' => $g->unit,
            'category_name' => $g->category->category_name,
        ]);
        $suppliers = Supplier::orderBy('supplier_name')->get();
        $warehouseId = Location::warehouse()->id;
        $posId = Location::pos()->id;

        $assignedTaxIds = Product::whereNotNull('tax_id')->pluck('tax_id')->unique();
        // Zero-Rated (0%) stays selectable even though only the current VAT rate is "active".
        $taxes = Taxes::where('is_active', true)
            ->orWhere('rate', 0)
            ->orWhereIn('id', $assignedTaxIds)
            ->orderBy('name')
            ->get();

        $generalItems = null;
        $products = null;
        $batches = null;
        $batchTotals = null;
        $historyProduct = null;
        $history = null;
        $historyProductsForJs = null;
        $nextGenericCode = null;
        $nextProductCode = null;

        $showTrashed = $request->boolean('show_trashed');
        $showArchived = $request->boolean('show_archived');
        // 'hide_zero' | 'only_zero' | null (all) — same filter, offered on both the General Item
        // and Products views, per Sir.
        $qtyFilter = $request->query('qty_filter');

        if ($tab === 'general') {
            // Correlated subquery rather than a per-row loop after pagination, so "Hide 0 Qty" /
            // "Show only 0 Qty" can filter (and paginate) correctly instead of just hiding rows
            // on whatever page happened to load.
            $onHandSubquery = LocationStock::query()
                ->join('product_batches', 'product_batches.id', '=', 'location_stocks.product_batch_id')
                ->join('products', 'products.id', '=', 'product_batches.product_id')
                ->whereColumn('products.generic_name_id', 'generic_names.id')
                ->selectRaw('COALESCE(SUM(location_stocks.qty), 0)');

            $generalItems = GenericName::with('category')
                ->withCount('products')
                // Not re-selecting generic_names.* here — withCount() already left the implicit
                // `select *` in place, and doing it again causes "Duplicate column name 'id'" once
                // paginate() wraps this in a COUNT() subquery.
                ->selectRaw('(' . $onHandSubquery->toSql() . ') as on_hand_qty')
                ->mergeBindings($onHandSubquery->getQuery())
                ->when($showTrashed, fn ($q) => $q->onlyTrashed())
                ->when(! $showTrashed && $showArchived, fn ($q) => $q->whereNotNull('archived_at'))
                ->when(! $showTrashed && ! $showArchived, fn ($q) => $q->whereNull('archived_at'))
                ->when($search, fn ($q) => $q->where('generic_name', 'like', "%{$search}%"))
                ->when($qtyFilter === 'hide_zero', fn ($q) => $q->havingRaw('on_hand_qty > 0'))
                ->when($qtyFilter === 'only_zero', fn ($q) => $q->havingRaw('on_hand_qty = 0'))
                ->orderBy('generic_name')
                ->paginate(self::PER_PAGE)
                ->withQueryString();

            $nextGenericCode = str_pad((string) (GenericName::max('id') + 1), 5, '0', STR_PAD_LEFT);
        }

        if ($tab === 'products') {
            $products = Product::with(['genericName', 'category', 'supplier', 'tax'])
                ->when($showTrashed, fn ($q) => $q->onlyTrashed())
                ->when(! $showTrashed && $showArchived, fn ($q) => $q->whereNotNull('archived_at'))
                ->when(! $showTrashed && ! $showArchived, fn ($q) => $q->whereNull('archived_at'))
                ->withSum(['locationStocks as warehouse_qty' => fn ($q) => $q->where('location_id', $warehouseId)], 'qty')
                ->withSum(['locationStocks as pos_qty' => fn ($q) => $q->where('location_id', $posId)], 'qty')
                ->withSum('locationStocks as total_qty', 'qty')
                ->when($search, function ($q) use ($search) {
                    $q->where('item_name', 'like', "%{$search}%")
                        ->orWhere('brand_name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                })
                ->when($qtyFilter === 'hide_zero', fn ($q) => $q->havingRaw('COALESCE(total_qty, 0) > 0'))
                ->when($qtyFilter === 'only_zero', fn ($q) => $q->havingRaw('COALESCE(total_qty, 0) = 0'))
                ->orderBy('item_name')
                ->paginate(self::PER_PAGE)
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
                ->paginate(self::PER_PAGE)
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
            // For the search box's autocomplete — Sir reported it wasn't offering suggestions;
            // there was no datalist wired up for this tab at all, so nothing could ever suggest.
            // The option's value is the plain item_name, matching exactly what the LIKE search
            // below already looks for — the code is shown only as the option's secondary hint
            // text, never submitted, so picking a suggestion still finds the same product.
            $historyProductsForJs = Product::whereNull('archived_at')
                ->orderBy('item_name')
                ->get(['id', 'item_name', 'code']);

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
                $perPage = self::PER_PAGE;

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
            'tab', 'search', 'categories', 'genericNames', 'genericNamesForJs', 'suppliers', 'taxes',
            'generalItems', 'products', 'batches', 'batchTotals', 'historyProduct', 'history',
            'nextGenericCode', 'nextProductCode', 'warehouseId', 'posId', 'showZero', 'showTrashed', 'showArchived', 'qtyFilter',
            'historyProductsForJs'
        ));
    }
}
