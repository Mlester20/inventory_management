<?php

namespace App\Services;

use App\Models\GoodsReceiptItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PurchaseReportService
{
    /**
     * Base query joining Goods Receipt lines through their batch to their
     * product, with the date-range filter applied. Rebuilt fresh on every
     * call so the totals/breakdowns below don't share mutated query state.
     *
     * Sourced from `goods_receipt_items`/`goods_receipts` — the actual
     * supplier-side procurement ledger (Purchase Order / Goods Receipt) —
     * not the POS checkout `purchases` table used elsewhere in this app.
     */
    protected function baseQuery(?string $startDate, ?string $endDate): Builder
    {
        $query = GoodsReceiptItem::query()
            ->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_items.goods_receipt_id')
            ->join('product_batches', 'product_batches.id', '=', 'goods_receipt_items.product_batch_id')
            ->join('products', 'products.id', '=', 'product_batches.product_id');

        if ($startDate && $endDate) {
            $query->whereBetween('goods_receipts.receipt_date', [$startDate, $endDate]);
        }

        return $query;
    }

    /**
     * Aggregate Goods Receipt summary over a date range, optionally
     * filtered by category.
     *
     * @return array{total_amount: float, total_qty: int, total_transactions: int, by_item: Collection, by_category: Collection}
     */
    public function getPurchaseSummary(?string $startDate = null, ?string $endDate = null, ?int $categoryId = null): array
    {
        $filtered = function () use ($startDate, $endDate, $categoryId) {
            $query = $this->baseQuery($startDate, $endDate);

            if ($categoryId) {
                $query->where('products.category_id', $categoryId);
            }

            return $query;
        };

        $totals = $filtered()
            ->selectRaw('SUM(goods_receipt_items.qty * goods_receipt_items.unit_cost) as total_amount, SUM(goods_receipt_items.qty) as total_qty, COUNT(DISTINCT goods_receipt_items.goods_receipt_id) as total_transactions')
            ->first();

        $byItem = $filtered()
            ->selectRaw('products.id, products.item_name, SUM(goods_receipt_items.qty) as qty, SUM(goods_receipt_items.qty * goods_receipt_items.unit_cost) as amount')
            ->groupBy('products.id', 'products.item_name')
            ->orderByDesc('amount')
            ->get();

        $byCategory = $filtered()
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->selectRaw('categories.id, categories.category_name, SUM(goods_receipt_items.qty) as qty, SUM(goods_receipt_items.qty * goods_receipt_items.unit_cost) as amount')
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
     * Goods Receipts grouped by supplier, via `goods_receipts.supplier_id` directly.
     */
    public function getPurchasesPerSupplier(?string $startDate = null, ?string $endDate = null): Collection
    {
        return $this->baseQuery($startDate, $endDate)
            ->join('suppliers', 'suppliers.id', '=', 'goods_receipts.supplier_id')
            ->selectRaw('
                suppliers.id,
                suppliers.supplier_name,
                SUM(goods_receipt_items.qty * goods_receipt_items.unit_cost) as total_amount,
                COUNT(DISTINCT goods_receipt_items.goods_receipt_id) as transactions,
                MAX(goods_receipts.receipt_date) as last_purchase_at
            ')
            ->groupBy('suppliers.id', 'suppliers.supplier_name')
            ->orderByDesc('total_amount')
            ->get();
    }
}
