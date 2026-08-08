<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Collection;

class InventoryReportService
{
    /**
     * Current stock per product, with computed total value and low-stock
     * flag. Default (no $locationId) sums across every location — this is
     * the "whole business" view used for e.g. total inventory value, where
     * which location holds the stock doesn't matter. Pass $locationId to
     * scope both the qty and the low-stock flag to one location instead —
     * needed so a link from a location-scoped alert (e.g. the Admin
     * dashboard's Warehouse-only low-stock banner, see
     * DashboardService::getLowStockAlert()) shows the same items here that
     * the alert claimed, rather than silently falling back to a combined
     * total that can disagree with it (an item out at Warehouse but fine at
     * POS is "low stock" on the alert but not on a combined-total view).
     */
    public function getInventorySummary(?int $categoryId = null, ?int $supplierId = null, bool $lowStockOnly = false, ?int $locationId = null): Collection
    {
        $query = Product::with(['category', 'supplier']);

        if ($locationId) {
            $query->withSum(['locationStocks as location_stocks_sum_qty' => fn ($q) => $q->where('location_id', $locationId)], 'qty');
        } else {
            $query->withSum('locationStocks', 'qty');
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        $products = $query->orderBy('item_name')->get();

        $products->each(function (Product $product) {
            $qty = (int) ($product->location_stocks_sum_qty ?? 0);
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
    public function getGrandTotal(?int $categoryId = null, ?int $supplierId = null, bool $lowStockOnly = false, ?int $locationId = null): float
    {
        return (float) $this->getInventorySummary($categoryId, $supplierId, $lowStockOnly, $locationId)->sum('total_value');
    }

    /**
     * Movement ledger for a single product, rolled up across every one of
     * its batches and every location, most recent first, with a running
     * balance. The balance is the product's total across all locations —
     * a Stock Transfer's paired out/in rows net to zero on this total, but
     * each row's own Location column shows exactly which location it
     * affected. The running balance is always computed from the product's
     * full movement history (so it stays anchored to the product's current
     * total quantity) even when the displayed rows are limited to a date
     * range.
     */
    public function getProductHistory(int $productId, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $product = Product::findOrFail($productId);
        $batchIds = $product->batches()->pluck('id');

        $movements = StockMovement::with(['user', 'productBatch', 'source', 'location'])
            ->whereIn('product_batch_id', $batchIds)
            ->orderBy('created_at')
            ->get();

        $currentTotal = (int) $product->locationStocks()->sum('qty');
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
