<?php

namespace App\Services;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Builder;

class ExpenseReportService
{
    /**
     * Base query with the date-range and category filters applied. Rebuilt
     * fresh on every call so the totals/breakdown below don't share mutated
     * query state.
     */
    protected function baseQuery(?string $startDate, ?string $endDate, ?int $categoryId): Builder
    {
        $query = Expense::query();

        if ($startDate && $endDate) {
            $query->whereBetween('expense_date', [$startDate, $endDate]);
        }

        if ($categoryId) {
            $query->where('expense_category_id', $categoryId);
        }

        return $query;
    }

    /**
     * Aggregate expense summary over a date range, optionally filtered by category.
     *
     * @return array{total_amount: float, total_transactions: int, by_category: \Illuminate\Support\Collection}
     */
    public function getExpenseSummary(?string $startDate = null, ?string $endDate = null, ?int $categoryId = null): array
    {
        $totals = $this->baseQuery($startDate, $endDate, $categoryId)
            ->selectRaw('SUM(amount) as total_amount, COUNT(*) as total_transactions')
            ->first();

        $byCategory = $this->baseQuery($startDate, $endDate, $categoryId)
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->selectRaw('expense_categories.id, expense_categories.name, SUM(expenses.amount) as amount, COUNT(*) as transactions')
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->orderByDesc('amount')
            ->get();

        return [
            'total_amount' => (float) ($totals->total_amount ?? 0),
            'total_transactions' => (int) ($totals->total_transactions ?? 0),
            'by_category' => $byCategory,
        ];
    }
}
