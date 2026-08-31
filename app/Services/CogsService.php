<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class CogsService
{
    /**
     * Cost of Goods Sold, combined across BOTH sales channels — POS
     * (`purchases`) and wholesale (`sales` via a non-cancelled `invoice`).
     * Cost is `products.unit_cost` — the only cost basis this app tracks
     * (updated on every Goods Receipt); it's the product's current/latest
     * cost, not a per-batch historical snapshot, since `product_batches`
     * has no cost column of its own. A cancelled Invoice's sales are
     * excluded from both cost and revenue (matches Cancel/Void meaning "no
     * longer a valid transaction"), but a cancelled/trashed/archived
     * Product or Sales Order has no bearing here — those flags only affect
     * whether a record shows in its own module's listing, not whether a
     * historical sale actually happened.
     */
    public function calculate(?string $startDate = null, ?string $endDate = null): array
    {
        $posCogs = (float) ($this->posCogsQuery($startDate, $endDate)->value('total') ?? 0);
        $posRevenue = (float) ($this->posRevenueQuery($startDate, $endDate)->value('total') ?? 0);

        $wholesaleCogs = (float) ($this->wholesaleCogsQuery($startDate, $endDate)->value('total') ?? 0);
        $wholesaleRevenue = (float) ($this->wholesaleRevenueQuery($startDate, $endDate)->value('total') ?? 0);

        $returnCostDeduction = (float) ($this->returnCostDeductionQuery($startDate, $endDate)->value('total') ?? 0);
        $returnRefunds = (float) ($this->returnRefundsQuery($startDate, $endDate)->value('total') ?? 0);

        $grossCogs = $posCogs + $wholesaleCogs;
        $netCogs = $grossCogs - $returnCostDeduction;
        $revenue = $posRevenue + $wholesaleRevenue - $returnRefunds;
        $grossProfit = $revenue - $netCogs;
        $marginPercent = $revenue > 0 ? ($grossProfit / $revenue) * 100 : 0.0;

        return [
            'gross_cogs' => $grossCogs,
            'return_deductions' => $returnCostDeduction,
            'net_cogs' => $netCogs,
            'revenue' => $revenue,
            'gross_profit' => $grossProfit,
            'margin_percent' => $marginPercent,
        ];
    }

    /**
     * Per-item breakdown, merging POS + wholesale qty/cost/revenue and
     * approved-return cost deductions for the same product.
     */
    public function perItem(?string $startDate = null, ?string $endDate = null): Collection
    {
        $pos = DB::table('purchases')
            ->join('product_batches', 'product_batches.id', '=', 'purchases.product_batch_id')
            ->join('products', 'products.id', '=', 'product_batches.product_id')
            ->when($startDate && $endDate, fn ($q) => $q->whereBetween('purchases.purchase_date', [$startDate, $endDate]))
            ->selectRaw('products.id, products.item_name,
                SUM(purchases.quantity_sold) as qty_sold,
                SUM(purchases.quantity_sold * products.unit_cost) as gross_cogs,
                SUM(purchases.total_price) as revenue')
            ->groupBy('products.id', 'products.item_name')
            ->get()
            ->keyBy('id');

        $wholesale = DB::table('sales')
            ->join('invoices', 'invoices.id', '=', 'sales.invoice_id')
            ->join('product_batches', 'product_batches.id', '=', 'sales.product_batch_id')
            ->join('products', 'products.id', '=', 'product_batches.product_id')
            ->whereNull('invoices.cancelled_at')
            ->when($startDate && $endDate, fn ($q) => $q->whereBetween('invoices.created_at', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"]))
            ->selectRaw('products.id, products.item_name,
                SUM(sales.qty) as qty_sold,
                SUM(sales.qty * products.unit_cost) as gross_cogs,
                SUM(sales.amount) as revenue')
            ->groupBy('products.id', 'products.item_name')
            ->get()
            ->keyBy('id');

        $returns = DB::table('return_items')
            ->join('product_batches', 'product_batches.id', '=', 'return_items.product_batch_id')
            ->join('products', 'products.id', '=', 'product_batches.product_id')
            ->where('return_items.status', 'approved')
            ->when($startDate && $endDate, fn ($q) => $q->whereBetween('return_items.return_date', [$startDate, $endDate]))
            ->selectRaw('products.id,
                SUM(return_items.quantity) as return_qty,
                SUM(return_items.quantity * products.unit_cost) as return_value')
            ->groupBy('products.id')
            ->get()
            ->keyBy('id');

        return $pos->keys()->merge($wholesale->keys())->unique()
            ->map(function ($id) use ($pos, $wholesale, $returns) {
                $posRow = $pos->get($id);
                $wsRow = $wholesale->get($id);
                $returnRow = $returns->get($id);

                $qtySold = (int) (($posRow->qty_sold ?? 0) + ($wsRow->qty_sold ?? 0));
                $grossCogs = (float) (($posRow->gross_cogs ?? 0) + ($wsRow->gross_cogs ?? 0));
                $revenue = (float) (($posRow->revenue ?? 0) + ($wsRow->revenue ?? 0));
                $returnQty = (int) ($returnRow->return_qty ?? 0);
                $returnValue = (float) ($returnRow->return_value ?? 0);
                $netCogs = $grossCogs - $returnValue;

                return (object) [
                    'id' => $id,
                    'item_name' => $posRow->item_name ?? $wsRow->item_name,
                    'qty_sold' => $qtySold,
                    'gross_cogs' => $grossCogs,
                    'revenue' => $revenue,
                    'return_qty' => $returnQty,
                    'return_value' => $returnValue,
                    'net_cogs' => $netCogs,
                    'margin_percent' => $revenue > 0 ? (($revenue - $netCogs) / $revenue) * 100 : 0.0,
                ];
            })
            ->sortByDesc('net_cogs')
            ->values();
    }

    /**
     * Monthly Net COGS trend for a given year, combining both channels.
     */
    public function monthlyTrend(int $year): Collection
    {
        $posByMonth = DB::table('purchases')
            ->join('product_batches', 'product_batches.id', '=', 'purchases.product_batch_id')
            ->join('products', 'products.id', '=', 'product_batches.product_id')
            ->whereRaw('YEAR(purchases.purchase_date) = ?', [$year])
            ->selectRaw('MONTH(purchases.purchase_date) as month,
                SUM(purchases.quantity_sold * products.unit_cost) as cogs,
                SUM(purchases.total_price) as revenue')
            ->groupByRaw('MONTH(purchases.purchase_date)')
            ->get()
            ->keyBy('month');

        $wholesaleByMonth = DB::table('sales')
            ->join('invoices', 'invoices.id', '=', 'sales.invoice_id')
            ->join('product_batches', 'product_batches.id', '=', 'sales.product_batch_id')
            ->join('products', 'products.id', '=', 'product_batches.product_id')
            ->whereNull('invoices.cancelled_at')
            ->whereRaw('YEAR(invoices.created_at) = ?', [$year])
            ->selectRaw('MONTH(invoices.created_at) as month,
                SUM(sales.qty * products.unit_cost) as cogs,
                SUM(sales.amount) as revenue')
            ->groupByRaw('MONTH(invoices.created_at)')
            ->get()
            ->keyBy('month');

        $returnsByMonth = DB::table('return_items')
            ->join('product_batches', 'product_batches.id', '=', 'return_items.product_batch_id')
            ->join('products', 'products.id', '=', 'product_batches.product_id')
            ->where('return_items.status', 'approved')
            ->whereRaw('YEAR(return_items.return_date) = ?', [$year])
            ->selectRaw('MONTH(return_items.return_date) as month,
                SUM(return_items.quantity * products.unit_cost) as cost_deduction,
                SUM(return_items.refund_amount) as refunds')
            ->groupByRaw('MONTH(return_items.return_date)')
            ->get()
            ->keyBy('month');

        $monthLabels = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
            7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];

        $result = collect();

        for ($month = 1; $month <= 12; $month++) {
            $posCogs = (float) ($posByMonth->get($month)?->cogs ?? 0);
            $wsCogs = (float) ($wholesaleByMonth->get($month)?->cogs ?? 0);
            $posRevenue = (float) ($posByMonth->get($month)?->revenue ?? 0);
            $wsRevenue = (float) ($wholesaleByMonth->get($month)?->revenue ?? 0);
            $returnDeduction = (float) ($returnsByMonth->get($month)?->cost_deduction ?? 0);
            $returnRefunds = (float) ($returnsByMonth->get($month)?->refunds ?? 0);

            $netCogs = $posCogs + $wsCogs - $returnDeduction;
            $revenue = $posRevenue + $wsRevenue - $returnRefunds;

            $result->push([
                'month' => $month,
                'label' => $monthLabels[$month],
                'net_cogs' => $netCogs,
                'revenue' => $revenue,
                'gross_profit' => $revenue - $netCogs,
            ]);
        }

        return $result;
    }

    protected function posCogsQuery(?string $startDate, ?string $endDate)
    {
        return DB::table('purchases')
            ->join('product_batches', 'product_batches.id', '=', 'purchases.product_batch_id')
            ->join('products', 'products.id', '=', 'product_batches.product_id')
            ->when($startDate && $endDate, fn ($q) => $q->whereBetween('purchases.purchase_date', [$startDate, $endDate]))
            ->selectRaw('SUM(purchases.quantity_sold * products.unit_cost) as total');
    }

    protected function posRevenueQuery(?string $startDate, ?string $endDate)
    {
        return DB::table('purchases')
            ->when($startDate && $endDate, fn ($q) => $q->whereBetween('purchase_date', [$startDate, $endDate]))
            ->selectRaw('SUM(total_price) as total');
    }

    protected function wholesaleCogsQuery(?string $startDate, ?string $endDate)
    {
        return DB::table('sales')
            ->join('invoices', 'invoices.id', '=', 'sales.invoice_id')
            ->join('product_batches', 'product_batches.id', '=', 'sales.product_batch_id')
            ->join('products', 'products.id', '=', 'product_batches.product_id')
            ->whereNull('invoices.cancelled_at')
            ->when($startDate && $endDate, fn ($q) => $q->whereBetween('invoices.created_at', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"]))
            ->selectRaw('SUM(sales.qty * products.unit_cost) as total');
    }

    protected function wholesaleRevenueQuery(?string $startDate, ?string $endDate)
    {
        return DB::table('sales')
            ->join('invoices', 'invoices.id', '=', 'sales.invoice_id')
            ->whereNull('invoices.cancelled_at')
            ->when($startDate && $endDate, fn ($q) => $q->whereBetween('invoices.created_at', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"]))
            ->selectRaw('SUM(sales.amount) as total');
    }

    protected function returnCostDeductionQuery(?string $startDate, ?string $endDate)
    {
        return DB::table('return_items')
            ->join('product_batches', 'product_batches.id', '=', 'return_items.product_batch_id')
            ->join('products', 'products.id', '=', 'product_batches.product_id')
            ->where('return_items.status', 'approved')
            ->when($startDate && $endDate, fn ($q) => $q->whereBetween('return_items.return_date', [$startDate, $endDate]))
            ->selectRaw('SUM(return_items.quantity * products.unit_cost) as total');
    }

    protected function returnRefundsQuery(?string $startDate, ?string $endDate)
    {
        return DB::table('return_items')
            ->where('status', 'approved')
            ->when($startDate && $endDate, fn ($q) => $q->whereBetween('return_date', [$startDate, $endDate]))
            ->selectRaw('SUM(refund_amount) as total');
    }
}
