<?php

namespace App\Services;

use App\Models\Item;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Collection;

class InventoryReportService
{
    /**
     * Current stock per item, with computed total value and low-stock flag.
     */
    public function getInventorySummary(?int $categoryId = null, ?int $supplierId = null, bool $lowStockOnly = false): Collection
    {
        $query = Item::with(['category', 'supplier']);

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($lowStockOnly) {
            $query->whereColumn('quantity', '<=', 'low_stock_threshold');
        }

        $items = $query->orderBy('item_name')->get();

        return $items->each(function (Item $item) {
            $item->total_value = $item->quantity * $item->unit_price;
            $item->is_low_stock = $item->quantity <= $item->low_stock_threshold;
        });
    }

    /**
     * Grand total inventory value for the given filters.
     */
    public function getGrandTotal(?int $categoryId = null, ?int $supplierId = null, bool $lowStockOnly = false): float
    {
        return (float) $this->getInventorySummary($categoryId, $supplierId, $lowStockOnly)->sum('total_value');
    }

    /**
     * Movement ledger for a single item, most recent first, with a running
     * balance. The running balance is always computed from the item's full
     * movement history (so it stays anchored to the item's current
     * quantity) even when the displayed rows are limited to a date range.
     */
    public function getProductHistory(int $itemId, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $item = Item::findOrFail($itemId);

        $movements = StockMovement::with('user')
            ->where('item_id', $itemId)
            ->orderBy('created_at')
            ->get();

        $runningBalance = $item->quantity - $movements->sum('quantity');

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
