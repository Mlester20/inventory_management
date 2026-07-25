<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Services\ExpenseReportService;
use Illuminate\Http\Request;

class ExpenseReportController extends Controller
{
    public function __construct(protected ExpenseReportService $expenseReportService) {}

    /**
     * Display the expense summary report.
     * Accepts optional query params: start_date, end_date, category_id
     */
    public function summary(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'category_id' => 'nullable|exists:expense_categories,id',
        ]);

        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;
        $categoryId = $validated['category_id'] ?? null;

        $summary = $this->expenseReportService->getExpenseSummary($startDate, $endDate, $categoryId);

        return view('admin.reports.expense-summary', array_merge($summary, [
            'expenseCategories' => ExpenseCategory::orderBy('name')->get(),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'categoryId' => $categoryId,
        ]));
    }
}
