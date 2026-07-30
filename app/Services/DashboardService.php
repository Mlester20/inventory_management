<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\DeliveryReceipt;
use App\Models\Expense;
use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\SalesOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(
        protected SalesReportService $salesReportService,
        protected PurchaseReportService $purchaseReportService,
        protected ExpenseReportService $expenseReportService,
        protected InventoryReportService $inventoryReportService,
        protected ProductExpirationReportService $productExpirationReportService,
    ) {}

    /**
     * Today's and this month's Sales Invoice totals, reusing the same
     * aggregation the Sales Summary report uses, plus a trend vs. last month.
     */
    public function getSalesOverview(): array
    {
        $today = Carbon::today()->toDateString();
        $monthStart = Carbon::today()->startOfMonth()->toDateString();
        $monthEnd = Carbon::today()->endOfMonth()->toDateString();
        $lastMonthStart = Carbon::today()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $lastMonthEnd = Carbon::today()->subMonthNoOverflow()->endOfMonth()->toDateString();

        $month = $this->salesReportService->getSalesSummary($monthStart, $monthEnd);
        $lastMonth = $this->salesReportService->getSalesSummary($lastMonthStart, $lastMonthEnd);

        return [
            'today' => $this->salesReportService->getSalesSummary($today, $today),
            'month' => $month,
            'trend' => $this->computeTrend($month['total_amount'], $lastMonth['total_amount']),
        ];
    }

    /**
     * Today's and this month's Goods Receipt (supplier procurement) totals,
     * reusing the same aggregation the Purchase Summary report uses, plus a
     * trend vs. last month.
     */
    public function getPurchasesOverview(): array
    {
        $today = Carbon::today()->toDateString();
        $monthStart = Carbon::today()->startOfMonth()->toDateString();
        $monthEnd = Carbon::today()->endOfMonth()->toDateString();
        $lastMonthStart = Carbon::today()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $lastMonthEnd = Carbon::today()->subMonthNoOverflow()->endOfMonth()->toDateString();

        $month = $this->purchaseReportService->getPurchaseSummary($monthStart, $monthEnd);
        $lastMonth = $this->purchaseReportService->getPurchaseSummary($lastMonthStart, $lastMonthEnd);

        return [
            'today' => $this->purchaseReportService->getPurchaseSummary($today, $today),
            'month' => $month,
            'trend' => $this->computeTrend($month['total_amount'], $lastMonth['total_amount']),
        ];
    }

    /**
     * This month's expense total, reusing the Expense Summary report's
     * aggregation, plus a trend vs. last month.
     */
    public function getExpensesOverview(): array
    {
        $monthStart = Carbon::today()->startOfMonth()->toDateString();
        $monthEnd = Carbon::today()->endOfMonth()->toDateString();
        $lastMonthStart = Carbon::today()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $lastMonthEnd = Carbon::today()->subMonthNoOverflow()->endOfMonth()->toDateString();

        $month = $this->expenseReportService->getExpenseSummary($monthStart, $monthEnd);
        $lastMonth = $this->expenseReportService->getExpenseSummary($lastMonthStart, $lastMonthEnd);

        return [
            'month' => $month,
            'trend' => $this->computeTrend($month['total_amount'], $lastMonth['total_amount']),
        ];
    }

    /**
     * Percentage change of $current vs. $previous. Returns a null direction
     * when there's no prior-period baseline to compare against (avoids a
     * misleading "+100%"/divide-by-zero reading on a brand-new dataset).
     *
     * @return array{direction: 'up'|'down'|'flat'|null, percent: float|null}
     */
    protected function computeTrend(float $current, float $previous): array
    {
        if ($previous <= 0) {
            return ['direction' => null, 'percent' => null];
        }

        $percent = (($current - $previous) / $previous) * 100;
        $direction = match (true) {
            $percent > 0.05 => 'up',
            $percent < -0.05 => 'down',
            default => 'flat',
        };

        return ['direction' => $direction, 'percent' => $percent];
    }

    /**
     * Current inventory value, low-stock count, and out-of-stock count,
     * derived from the same product+batch rollup the Inventory Summary
     * report uses (one query, three numbers read off the same collection).
     */
    public function getInventorySnapshot(): array
    {
        $products = $this->inventoryReportService->getInventorySummary();

        return [
            'total_value' => (float) $products->sum('total_value'),
            'low_stock_count' => $products->where('is_low_stock', true)->count(),
            'out_of_stock_count' => $products->where('on_hand_qty', '<=', 0)->count(),
        ];
    }

    /**
     * Expired-batch count plus a short preview list, reusing the
     * Product Expiration Report's own service — this Dashboard widget is
     * new (no such section existed on the Dashboard before), so it links
     * out to the full `admin.reports.expiration` report rather than
     * duplicating its table.
     */
    public function getExpiredProductsSnapshot(int $previewLimit = 5): array
    {
        $summary = $this->productExpirationReportService->getExpirationSummary();
        $expiredItems = $this->productExpirationReportService->getExpiredItems();

        return [
            'expired_count' => $summary['expired'],
            'items' => $expiredItems->take($previewLimit),
        ];
    }

    /**
     * Counts of actionable/pending records across modules whose status
     * fields already exist — each maps to an existing index page so the
     * dashboard can link straight into the relevant filtered work queue.
     */
    public function getPendingActionItems(): array
    {
        return [
            'dr_for_delivery' => DeliveryReceipt::where('status', 'for_delivery')->count(),
            'dr_not_fully_invoiced' => DeliveryReceipt::whereHas(
                'items',
                fn ($query) => $query->whereColumn('invoiced_qty', '<', 'qty')
            )->count(),
            'so_in_progress' => SalesOrder::whereNotIn('status', ['completed', 'cancelled'])->count(),
            'po_awaiting_receipt' => PurchaseOrder::whereIn('status', ['open', 'partially_received'])->count(),
        ];
    }

    /**
     * Total Sales (Invoice) / POS Revenue (Purchase) / Expenses, bucketed by
     * week, month, or year — powers the hero chart's period filter toggle.
     * All three periods are precomputed together (rather than fetched via a
     * new AJAX endpoint per click) to match how the rest of this dashboard
     * already works: everything is aggregated once server-side and handed
     * to the view as JSON, with no separate live-data endpoint.
     *
     * @return array{week: array, month: array, year: array} each shaped as
     *         array{categories: array<int,string>, sales: array<int,float>, revenue: array<int,float>, expenses: array<int,float>}
     */
    public function getSalesTrendChartByPeriod(): array
    {
        return [
            'week' => $this->buildWeeklySalesTrend(),
            'month' => $this->buildMonthlySalesTrend(),
            'year' => $this->buildYearlySalesTrend(),
        ];
    }

    private function buildMonthlySalesTrend(): array
    {
        $year = now()->year;
        $categories = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        $sales = Sale::query()
            ->join('invoices', 'invoices.id', '=', 'sales.invoice_id')
            ->whereYear('invoices.created_at', $year)
            ->selectRaw('MONTH(invoices.created_at) as period, SUM(sales.amount) as total')
            ->groupBy('period')
            ->pluck('total', 'period');

        $revenue = Purchase::query()
            ->whereYear('purchase_date', $year)
            ->selectRaw('MONTH(purchase_date) as period, SUM(total_price) as total')
            ->groupBy('period')
            ->pluck('total', 'period');

        $expenses = Expense::query()
            ->whereYear('expense_date', $year)
            ->selectRaw('MONTH(expense_date) as period, SUM(amount) as total')
            ->groupBy('period')
            ->pluck('total', 'period');

        return [
            'categories' => $categories,
            'sales' => collect(range(1, 12))->map(fn ($m) => (float) ($sales[$m] ?? 0))->all(),
            'revenue' => collect(range(1, 12))->map(fn ($m) => (float) ($revenue[$m] ?? 0))->all(),
            'expenses' => collect(range(1, 12))->map(fn ($m) => (float) ($expenses[$m] ?? 0))->all(),
        ];
    }

    /**
     * Last 12 weeks (Monday-start), one query per week — same per-period
     * loop style already used by getLast5DaysComparison() below.
     */
    private function buildWeeklySalesTrend(): array
    {
        $weeks = collect(range(11, 0))->map(fn ($offset) => Carbon::now()->startOfWeek()->subWeeks($offset));

        $categories = [];
        $sales = [];
        $revenue = [];
        $expenses = [];

        foreach ($weeks as $start) {
            $end = $start->copy()->endOfWeek();
            $categories[] = $start->format('M d');

            $sales[] = (float) Sale::query()
                ->join('invoices', 'invoices.id', '=', 'sales.invoice_id')
                ->whereBetween('invoices.created_at', [$start, $end])
                ->sum('sales.amount');

            $revenue[] = (float) Purchase::query()
                ->whereBetween('purchase_date', [$start->toDateString(), $end->toDateString()])
                ->sum('total_price');

            $expenses[] = (float) Expense::query()
                ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
                ->sum('amount');
        }

        return compact('categories', 'sales', 'revenue', 'expenses');
    }

    /**
     * Last 5 calendar years including the current one.
     */
    private function buildYearlySalesTrend(): array
    {
        $currentYear = now()->year;
        $years = collect(range(4, 0))->map(fn ($offset) => $currentYear - $offset);

        $categories = [];
        $sales = [];
        $revenue = [];
        $expenses = [];

        foreach ($years as $year) {
            $categories[] = (string) $year;

            $sales[] = (float) Sale::query()
                ->join('invoices', 'invoices.id', '=', 'sales.invoice_id')
                ->whereYear('invoices.created_at', $year)
                ->sum('sales.amount');

            $revenue[] = (float) Purchase::query()->whereYear('purchase_date', $year)->sum('total_price');

            $expenses[] = (float) Expense::query()->whereYear('expense_date', $year)->sum('amount');
        }

        return compact('categories', 'sales', 'revenue', 'expenses');
    }

    /**
     * Sales (Invoice) vs. Purchases (Goods Receipt) totals for each of the
     * last 5 days — reuses the same summary methods the overview cards
     * already call, just once per day.
     *
     * @return array<int, array{label: string, sales: float, purchases: float}>
     */
    public function getLast5DaysComparison(): array
    {
        $days = collect(range(4, 0))->map(fn ($offset) => Carbon::today()->subDays($offset));

        return $days->map(function (Carbon $day) {
            $dateString = $day->toDateString();

            return [
                'label' => $day->format('M d'),
                'sales' => $this->salesReportService->getSalesSummary($dateString, $dateString)['total_amount'],
                'purchases' => $this->purchaseReportService->getPurchaseSummary($dateString, $dateString)['total_amount'],
            ];
        })->all();
    }

    /**
     * Customer base size and this-month Sales Order volume, each with a
     * trend vs. last month.
     */
    public function getCustomerAndOrderStats(): array
    {
        $monthStart = Carbon::today()->startOfMonth();
        $monthEnd = Carbon::today()->endOfMonth();
        $lastMonthStart = Carbon::today()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = Carbon::today()->subMonthNoOverflow()->endOfMonth();

        $totalCustomers = Customer::count();
        $newCustomersThisMonth = Customer::whereBetween('created_at', [$monthStart, $monthEnd])->count();
        $newCustomersLastMonth = Customer::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();

        $ordersThisMonth = SalesOrder::whereBetween('created_at', [$monthStart, $monthEnd])->count();
        $ordersLastMonth = SalesOrder::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();

        return [
            'total_customers' => $totalCustomers,
            'customers_trend' => $this->computeTrend($newCustomersThisMonth, $newCustomersLastMonth),
            'orders_this_month' => $ordersThisMonth,
            'orders_trend' => $this->computeTrend($ordersThisMonth, $ordersLastMonth),
        ];
    }

    /**
     * The latest Invoices, Delivery Receipts, and Goods Receipts merged
     * into one feed, sorted by timestamp — three cheap indexed "latest N"
     * queries merged in PHP rather than one expensive UNION across
     * differently-shaped tables.
     */
    public function getRecentActivity(int $limit = 8): Collection
    {
        $invoices = Invoice::latest()->limit($limit)->get()->map(fn (Invoice $invoice) => [
            'type' => 'Invoice',
            'badge' => 'success',
            'reference' => $invoice->sales_no,
            'detail' => $invoice->customer_name,
            'amount' => (float) $invoice->total_sales,
            'at' => $invoice->created_at,
            'url' => route('invoices.show', $invoice),
        ]);

        $deliveryReceipts = DeliveryReceipt::with('customer')->latest()->limit($limit)->get()->map(fn (DeliveryReceipt $dr) => [
            'type' => 'Delivery Receipt',
            'badge' => 'info',
            'reference' => $dr->dr_no,
            'detail' => $dr->customer->customer_name ?? '—',
            'amount' => null,
            'at' => $dr->created_at,
            'url' => route('delivery-receipts.show', $dr),
        ]);

        $goodsReceipts = GoodsReceipt::with('supplier')->latest()->limit($limit)->get()->map(fn (GoodsReceipt $gr) => [
            'type' => 'Goods Receipt',
            'badge' => 'warning',
            'reference' => $gr->gr_no,
            'detail' => $gr->supplier->supplier_name ?? '—',
            'amount' => null,
            'at' => $gr->created_at,
            'url' => route('goods-receipts.show', $gr),
        ]);

        return $invoices->concat($deliveryReceipts)->concat($goodsReceipts)
            ->sortByDesc('at')
            ->take($limit)
            ->values();
    }
}
