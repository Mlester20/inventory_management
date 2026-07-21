<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Supplier;
use App\Services\ProductExpirationReportService;
use Illuminate\Http\Request;

class ProductExpirationReportController extends Controller
{
    public function __construct(protected ProductExpirationReportService $expirationReportService) {}

    /**
     * Display the product expiration report.
     * Accepts optional query params: days, category_id, supplier_id
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'days' => 'nullable|integer|in:7,30,60,90',
            'category_id' => 'nullable|exists:categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
        ]);

        $days = $validated['days'] ?? 30;
        $categoryId = $validated['category_id'] ?? null;
        $supplierId = $validated['supplier_id'] ?? null;

        $items = $this->expirationReportService->getExpiringItems($days, $categoryId, $supplierId);
        $summary = $this->expirationReportService->getExpirationSummary($categoryId, $supplierId);

        return view('admin.reports.expiration', [
            'items' => $items,
            'summary' => $summary,
            'categories' => Category::orderBy('category_name')->get(),
            'suppliers' => Supplier::orderBy('supplier_name')->get(),
            'days' => $days,
            'categoryId' => $categoryId,
            'supplierId' => $supplierId,
        ]);
    }
}
