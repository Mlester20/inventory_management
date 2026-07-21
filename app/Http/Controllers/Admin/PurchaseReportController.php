<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\PurchaseReportService;
use Illuminate\Http\Request;

class PurchaseReportController extends Controller
{
    public function __construct(protected PurchaseReportService $purchaseReportService) {}

    /**
     * Display the purchase (POS checkout) summary report.
     * Accepts optional query params: start_date, end_date, category_id
     */
    public function summary(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;
        $categoryId = $validated['category_id'] ?? null;

        $summary = $this->purchaseReportService->getPurchaseSummary($startDate, $endDate, $categoryId);

        return view('admin.reports.purchase-summary', array_merge($summary, [
            'categories' => Category::orderBy('category_name')->get(),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'categoryId' => $categoryId,
        ]));
    }

    /**
     * Display the purchases-per-supplier report.
     * Accepts optional query params: start_date, end_date
     */
    public function perSupplier(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;

        return view('admin.reports.purchases-per-supplier', [
            'suppliers' => $this->purchaseReportService->getPurchasesPerSupplier($startDate, $endDate),
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }
}
