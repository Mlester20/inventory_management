<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\InventoryReportService;
use Illuminate\Http\Request;

class InventoryReportController extends Controller
{
    public function __construct(protected InventoryReportService $inventoryReportService) {}

    /**
     * Display the inventory summary report.
     * Accepts optional query params: category_id, supplier_id, low_stock_only
     */
    public function summary(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'low_stock_only' => 'nullable|boolean',
        ]);

        $categoryId = $validated['category_id'] ?? null;
        $supplierId = $validated['supplier_id'] ?? null;
        $lowStockOnly = $request->boolean('low_stock_only');

        $items = $this->inventoryReportService->getInventorySummary($categoryId, $supplierId, $lowStockOnly);
        $grandTotal = $items->sum('total_value');

        return view('admin.reports.inventory-summary', [
            'items' => $items,
            'grandTotal' => $grandTotal,
            'categories' => Category::orderBy('category_name')->get(),
            'suppliers' => Supplier::orderBy('supplier_name')->get(),
            'categoryId' => $categoryId,
            'supplierId' => $supplierId,
            'lowStockOnly' => $lowStockOnly,
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
