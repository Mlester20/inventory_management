<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Collection;

class InventoryReportService
{
    /**
     * Current stock per product (summed across its batches), with computed
     * total value and low-stock flag.
     */
    public function getInventorySummary(?int $categoryId = null, ?int $supplierId = null, bool $lowStockOnly = false): Collection
    {
        $query = Product::with(['category', 'supplier'])->withSum('batches', 'qty');

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        $products = $query->orderBy('item_name')->get();

        $products->each(function (Product $product) {
            $qty = (int) ($product->batches_sum_qty ?? 0);
            $product->on_hand_qty = $qty;
            $product->total_value = $qty * $product->unit_price;
            $product->is_low_stock = $qty <= $product->low_stock_threshold;
        });

        if ($lowStockOnly) {
            $products = $products->filter(fn (Product $product) => $product->is_low_stock)->values();
        }

        return $products;
    }

    /**
     * Grand total inventory value for the given filters.
     */
    public function getGrandTotal(?int $categoryId = null, ?int $supplierId = null, bool $lowStockOnly = false): float
    {
        return (float) $this->getInventorySummary($categoryId, $supplierId, $lowStockOnly)->sum('total_value');
    }

    /**
     * Movement ledger for a single product, rolled up across every one of
     * its batches, most recent first, with a running balance. The running
     * balance is always computed from the product's full movement history
     * (so it stays anchored to the product's current total quantity) even
     * when the displayed rows are limited to a date range.
     */
    public function getProductHistory(int $productId, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $product = Product::findOrFail($productId);
        $batchIds = $product->batches()->pluck('id');

        $movements = StockMovement::with(['user', 'productBatch', 'source'])
            ->whereIn('product_batch_id', $batchIds)
            ->orderBy('created_at')
            ->get();

        $currentTotal = (int) $product->batches()->sum('qty');
        $runningBalance = $currentTotal - $movements->sum('quantity');

        foreach ($movements as $movement) {
            $runningBalance += $movement->quantity;
            $movement->running_balance = $runningBalance;
        }

        if ($startDate && $endDate) {
            $movements = $movements->filter(
                fn (StockMovement $movement) => $movement->created_at->between($startDate, "{$endDate} 23:59:59")
            )->values();
        }

        return $movements->reverse()->values();
    }
}
