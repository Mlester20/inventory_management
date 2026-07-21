<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\SalesReportService;
use Illuminate\Http\Request;

class SalesReportController extends Controller
{
    public function __construct(protected SalesReportService $salesReportService) {}

    /**
     * Display the sales summary report (Invoices only).
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

        $summary = $this->salesReportService->getSalesSummary($startDate, $endDate, $categoryId);

        return view('admin.reports.sales-summary', array_merge($summary, [
            'categories' => Category::orderBy('category_name')->get(),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'categoryId' => $categoryId,
        ]));
    }

    /**
     * Display the sales-per-customer report.
     * Accepts optional query params: start_date, end_date
     */
    public function perCustomer(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;

        return view('admin.reports.sales-per-customer', [
            'byInvoiceName' => $this->salesReportService->getSalesPerInvoiceCustomerName($startDate, $endDate),
            'byCustomerRecord' => $this->salesReportService->getSalesPerCustomerRecord($startDate, $endDate),
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }
}
