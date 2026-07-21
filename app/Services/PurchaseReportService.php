<?php

namespace App\Services;

use App\Models\Purchase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PurchaseReportService
{
    /**
     * Base query joining purchase (POS checkout) lines to their item, with
     * the date-range filter applied. Rebuilt fresh on every call so the
     * totals/breakdowns below don't share mutated query state.
     *
     * Despite the table's name, `purchases` records POS checkout/sale
     * transactions (see PurchaseController/CogsService) — not money paid to
     * suppliers. There is no supplier-side procurement tracking in this
     * schema, so this report is scoped to that existing table as-is.
     */
    protected function baseQuery(?string $startDate, ?string $endDate): Builder
    {
        $query = Purchase::query()
            ->join('items', 'items.id', '=', 'purchases.item_id')
            ->dateRange($startDate, $endDate);

        return $query;
    }

    /**
     * Aggregate purchase (POS checkout) summary over a date range,
     * optionally filtered by category.
     *
     * @return array{total_amount: float, total_qty: int, total_transactions: int, by_item: Collection, by_category: Collection}
     */
    public function getPurchaseSummary(?string $startDate = null, ?string $endDate = null, ?int $categoryId = null): array
    {
        $filtered = function () use ($startDate, $endDate, $categoryId) {
            $query = $this->baseQuery($startDate, $endDate);

            if ($categoryId) {
                $query->where('items.category_id', $categoryId);
            }

            return $query;
        };

        $totals = $filtered()
            ->selectRaw('SUM(purchases.total_price) as total_amount, SUM(purchases.quantity_sold) as total_qty, COUNT(*) as total_transactions')
            ->first();

        $byItem = $filtered()
            ->selectRaw('items.id, items.item_name, SUM(purchases.quantity_sold) as qty, SUM(purchases.total_price) as amount')
            ->groupBy('items.id', 'items.item_name')
            ->orderByDesc('amount')
            ->get();

        $byCategory = $filtered()
            ->join('categories', 'categories.id', '=', 'items.category_id')
            ->selectRaw('categories.id, categories.category_name, SUM(purchases.quantity_sold) as qty, SUM(purchases.total_price) as amount')
            ->groupBy('categories.id', 'categories.category_name')
            ->orderByDesc('amount')
            ->get();

        return [
            'total_amount' => (float) ($totals->total_amount ?? 0),
            'total_qty' => (int) ($totals->total_qty ?? 0),
            'total_transactions' => (int) ($totals->total_transactions ?? 0),
            'by_item' => $byItem,
            'by_category' => $byCategory,
        ];
    }

    /**
     * Purchase (POS checkout) transactions grouped by supplier, inferred
     * via items.supplier_id since `purchases` has no direct supplier_id
     * column of its own.
     */
    public function getPurchasesPerSupplier(?string $startDate = null, ?string $endDate = null): Collection
    {
        return $this->baseQuery($startDate, $endDate)
            ->join('suppliers', 'suppliers.id', '=', 'items.supplier_id')
            ->selectRaw('
                suppliers.id,
                suppliers.supplier_name,
                SUM(purchases.total_price) as total_amount,
                COUNT(*) as transactions,
                MAX(purchases.purchase_date) as last_purchase_at
            ')
            ->groupBy('suppliers.id', 'suppliers.supplier_name')
            ->orderByDesc('total_amount')
            ->get();
    }
}
