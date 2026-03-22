<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\ReturnItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class CogsService
{
    /**
     * Calculate total COGS for a given date range.
     * Formula: SUM(quantity_sold * unit_price) from purchases
     *          MINUS SUM(quantity * unit_price) from approved returns
     *
     * @param string|null $startDate  Y-m-d format
     * @param string|null $endDate    Y-m-d format
     * @return array{gross_cogs: float, return_deductions: float, net_cogs: float}
     */
    public function calculate(?string $startDate = null, ?string $endDate = null): array
    {
        // Calculate gross COGS from purchases
        $grossCogsQuery = Purchase::query();
        
        if ($startDate && $endDate) {
            $grossCogsQuery->whereBetween('purchase_date', [$startDate, $endDate]);
        }
        
        $grossCogs = (float) ($grossCogsQuery->selectRaw('SUM(quantity_sold * unit_price) as total')
            ->first()?->total ?? 0);

        // Calculate return deductions from approved returns
        $returnDeductionsQuery = ReturnItem::query()
            ->join('items', 'items.id', '=', 'return_items.item_id')
            ->where('return_items.status', 'approved');
        
        if ($startDate && $endDate) {
            $returnDeductionsQuery->whereBetween('return_items.return_date', [$startDate, $endDate]);
        }
        
        $returnDeductions = (float) ($returnDeductionsQuery->selectRaw('SUM(return_items.quantity * items.unit_price) as total')
            ->first()?->total ?? 0);

        $netCogs = $grossCogs - $returnDeductions;

        return [
            'gross_cogs' => $grossCogs,
            'return_deductions' => $returnDeductions,
            'net_cogs' => $netCogs,
        ];
    }

    /**
     * Return COGS broken down per item for a date range.
     * Useful for the per-item table in the admin UI.
     *
     * @return Collection
     */
    public function perItem(?string $startDate = null, ?string $endDate = null): Collection
    {
        $query = DB::table('purchases')
            ->join('items', 'items.id', '=', 'purchases.item_id')
            ->leftJoin('return_items', function ($join) {
                $join->on('return_items.item_id', '=', 'purchases.item_id')
                    ->where('return_items.status', '=', 'approved');
            })
            ->select(
                'items.id',
                'items.item_name',
                DB::raw('SUM(purchases.quantity_sold) as qty_sold'),
                DB::raw('SUM(purchases.quantity_sold * purchases.unit_price) as gross_cogs'),
                DB::raw('COALESCE(SUM(return_items.quantity), 0) as return_qty'),
                DB::raw('COALESCE(SUM(return_items.quantity * items.unit_price), 0) as return_value'),
                DB::raw('SUM(purchases.quantity_sold * purchases.unit_price) - COALESCE(SUM(return_items.quantity * items.unit_price), 0) as net_cogs')
            );

        if ($startDate && $endDate) {
            $query->whereBetween('purchases.purchase_date', [$startDate, $endDate]);
        }

        return $query->groupBy('items.id', 'items.item_name')
            ->orderByDesc('net_cogs')
            ->get();
    }

    /**
     * Return monthly COGS trend for a given year.
     * Used to power the trend chart in the admin UI.
     *
     * @param int $year
     * @return Collection  shape: [{ month: int, label: string, net_cogs: float }]
     */
    public function monthlyTrend(int $year): Collection
    {
        // Get purchases by month
        $purchasesByMonth = DB::table('purchases')
            ->selectRaw('MONTH(purchase_date) as month, SUM(quantity_sold * unit_price) as purchase_total')
            ->whereRaw('YEAR(purchase_date) = ?', [$year])
            ->groupByRaw('MONTH(purchase_date)')
            ->get()
            ->keyBy('month');

        // Get approved returns by month
        $returnsByMonth = DB::table('return_items')
            ->join('items', 'items.id', '=', 'return_items.item_id')
            ->selectRaw('MONTH(return_items.return_date) as month, SUM(return_items.quantity * items.unit_price) as return_total')
            ->where('return_items.status', 'approved')
            ->whereRaw('YEAR(return_items.return_date) = ?', [$year])
            ->groupByRaw('MONTH(return_items.return_date)')
            ->get()
            ->keyBy('month');

        // Build complete month array
        $monthLabels = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
            7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
        ];

        $result = collect();
        
        for ($month = 1; $month <= 12; $month++) {
            $purchase = $purchasesByMonth->get($month)?->purchase_total ?? 0;
            $return = $returnsByMonth->get($month)?->return_total ?? 0;
            $netCogs = (float) ($purchase - $return);

            $result->push([
                'month' => $month,
                'label' => $monthLabels[$month],
                'net_cogs' => $netCogs,
            ]);
        }

        return $result;
    }
}
