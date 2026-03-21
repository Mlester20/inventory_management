<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Category;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with dynamic inventory data.
     */
    public function index()
    {
        // --- Stock Summary ---
        $totalItems = Item::count();
        $totalStock = Item::sum('quantity');

        // Low stock: items where quantity <= low_stock_threshold
        $lowStockItems = Item::whereColumn('quantity', '<=', 'low_stock_threshold')
            ->with('category', 'supplier')
            ->get();
        $lowStockCount = $lowStockItems->count();

        // All items with current stock vs threshold for the stock table
        $stockItems = Item::with('category', 'supplier')
            ->orderBy('quantity', 'asc')
            ->get();

        // --- Purchase Summary ---
        $totalPurchases = Purchase::count();
        $totalRevenue = Purchase::sum('total_price');

        // Monthly revenue for the current year (for chart)
        $monthlyRevenue = Purchase::selectRaw('MONTH(purchase_date) as month, SUM(total_price) as revenue')
            ->whereYear('purchase_date', now()->year)
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('revenue', 'month');

        // Recent purchases with item info
        $recentPurchases = Purchase::with('item.category')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // --- Stock Movement Summary ---
        $totalStockIn = StockMovement::where('type', 'in')->sum('quantity');
        $totalStockOut = StockMovement::where('type', 'out')->sum('quantity');

        return view('admin.dashboard', compact(
            'totalItems',
            'totalStock',
            'lowStockItems',
            'lowStockCount',
            'stockItems',
            'totalPurchases',
            'totalRevenue',
            'monthlyRevenue',
            'recentPurchases',
            'totalStockIn',
            'totalStockOut',
        ));
    }
}
