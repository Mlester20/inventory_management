<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Location;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Category;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboardService) {}

    /**
     * Display the dashboard with dynamic inventory data.
     */
    public function index()
    {
        // --- Stock Summary ---
        $totalItems = Product::count();

        // Warehouse-scoped: this is the stock Admin manages day-to-day
        // (reorder from supplier, transfer to POS) — not a combined total
        // across every location, since POS running low doesn't mean
        // Warehouse needs reordering. See DashboardService::getLowStockAlert().
        // $warehouseId is also passed to the view so its "view full report"
        // links can carry the same scoping — otherwise the linked report
        // would fall back to a combined total that can silently disagree
        // with what this banner just claimed.
        $warehouseId = Location::warehouse()->id;
        $lowStockAlert = $this->dashboardService->getLowStockAlert($warehouseId);
        $lowStockCount = $lowStockAlert['low_stock_count'];
        $lowStockItems = $lowStockAlert['low_stock_items'];
        $outOfStockCount = $lowStockAlert['out_of_stock_count'];
        $outOfStockItems = $lowStockAlert['out_of_stock_items'];

        // --- Purchase Summary ---
        $totalPurchases = Purchase::count();
        $totalRevenue = Purchase::sum('total_price');

        // Recent purchases with product info
        $recentPurchases = Purchase::with('productBatch.product.category')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // --- Stock Movement Summary ---
        $totalStockIn = StockMovement::where('type', 'in')->sum('quantity');
        $totalStockOut = StockMovement::where('type', 'out')->sum('quantity');

        // --- New widgets, all aggregation delegated to DashboardService ---
        $salesOverview = $this->dashboardService->getSalesOverview();
        $purchasesOverview = $this->dashboardService->getPurchasesOverview();
        $expensesOverview = $this->dashboardService->getExpensesOverview();
        $inventorySnapshot = $this->dashboardService->getInventorySnapshot();
        $expiredProducts = $this->dashboardService->getExpiredProductsSnapshot();
        $pendingActionItems = $this->dashboardService->getPendingActionItems();
        $recentActivity = $this->dashboardService->getRecentActivity();
        $salesTrendByPeriod = $this->dashboardService->getSalesTrendChartByPeriod();
        $last5DaysComparison = $this->dashboardService->getLast5DaysComparison();
        $customerOrderStats = $this->dashboardService->getCustomerAndOrderStats();
        $recentInvoices = Invoice::latest()->limit(6)->get();

        return view('admin.dashboard', compact(
            'totalItems',
            'warehouseId',
            'lowStockItems',
            'lowStockCount',
            'outOfStockItems',
            'outOfStockCount',
            'totalPurchases',
            'totalRevenue',
            'recentPurchases',
            'totalStockIn',
            'totalStockOut',
            'salesOverview',
            'purchasesOverview',
            'expensesOverview',
            'inventorySnapshot',
            'expiredProducts',
            'pendingActionItems',
            'recentActivity',
            'salesTrendByPeriod',
            'last5DaysComparison',
            'customerOrderStats',
            'recentInvoices',
        ));
    }
}
