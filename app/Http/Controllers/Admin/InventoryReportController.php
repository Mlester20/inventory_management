<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\InventoryReportService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class InventoryReportController extends Controller
{
    public function __construct(protected InventoryReportService $inventoryReportService) {}

    /**
     * Display the inventory summary report.
     * Accepts optional query params: category_id, supplier_id, low_stock_only, location_id
     */
    public function summary(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'low_stock_only' => 'nullable|boolean',
            'location_id' => 'nullable|exists:locations,id',
        ]);

        $categoryId = $validated['category_id'] ?? null;
        $supplierId = $validated['supplier_id'] ?? null;
        $lowStockOnly = $request->boolean('low_stock_only');
        // Optional: scopes qty/low-stock to one location instead of the
        // combined total — used when linking here from a location-scoped
        // alert (e.g. the Admin dashboard's Warehouse-only banner) so the
        // report shows the same items the alert claimed, not a combined
        // total that can silently disagree with it.
        $locationId = $validated['location_id'] ?? null;

        $allItems = $this->inventoryReportService->getInventorySummary($categoryId, $supplierId, $lowStockOnly, $locationId);
        $grandTotal = $allItems->sum('total_value');
        $lowStockCount = $allItems->where('is_low_stock', true)->count();

        // Low-stock filtering and the grand total both need the full result
        // set, so pagination is applied afterward, over the already-computed
        // collection, rather than at the query level.
        $perPage = 15;
        $page = (int) $request->query('page', 1);
        $items = new LengthAwarePaginator(
            $allItems->forPage($page, $perPage),
            $allItems->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('admin.reports.inventory-summary', [
            'items' => $items,
            'grandTotal' => $grandTotal,
            'lowStockCount' => $lowStockCount,
            'categories' => Category::orderBy('category_name')->get(),
            'suppliers' => Supplier::orderBy('supplier_name')->get(),
            'categoryId' => $categoryId,
            'supplierId' => $supplierId,
            'lowStockOnly' => $lowStockOnly,
            'locationId' => $locationId,
            'locationName' => $locationId ? Location::find($locationId)?->name : null,
        ]);
    }

    /**
     * Display the product history (movement ledger) report.
     * Accepts optional query params: item_id (product id), start_date, end_date
     *
     * This report has been superseded by the "Product History" tab on the
     * Products & Inventory module (see InventoryItemsController), which
     * rolls movements up across every batch of a product. This route stays
     * only as a redirect so old links/bookmarks keep working.
     */
    public function productHistory(Request $request)
    {
        return redirect()->route('inventory-items.index', [
            'tab' => 'history',
            'product_id' => $request->query('item_id'),
        ]);
    }
}
