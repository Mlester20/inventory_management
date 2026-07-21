<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
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
     * Accepts optional query params: item_id, start_date, end_date
     */
    public function productHistory(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'nullable|exists:items,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $itemId = $validated['item_id'] ?? null;
        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;

        $movements = $itemId
            ? $this->inventoryReportService->getProductHistory($itemId, $startDate, $endDate)
            : collect();

        return view('admin.reports.product-history', [
            'movements' => $movements,
            'items' => Item::orderBy('item_name')->get(),
            'itemId' => $itemId,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }
}
