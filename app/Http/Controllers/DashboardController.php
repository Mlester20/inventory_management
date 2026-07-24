<?php

namespace App\Http\Controllers;

use App\Models\Product;
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
        $totalItems = Product::count();
        $totalStock = (int) \DB::table('product_batches')->sum('qty');

        // Products with computed on-hand quantity, for low-stock/table use.
        $productsWithQty = Product::with('category', 'supplier')
            ->withSum('batches', 'qty')
            ->orderByRaw('COALESCE((select sum(qty) from product_batches where product_batches.product_id = products.id), 0) asc')
            ->get()
            ->each(fn (Product $product) => $product->on_hand_qty = (int) ($product->batches_sum_qty ?? 0));

        // Low stock: products where on-hand qty <= low_stock_threshold
        $lowStockItems = $productsWithQty->filter(fn (Product $product) => $product->on_hand_qty <= $product->low_stock_threshold)->values();
        $lowStockCount = $lowStockItems->count();

        // All products with current stock vs threshold for the stock table
        $stockItems = $productsWithQty;

        // --- Purchase Summary ---
        $totalPurchases = Purchase::count();
        $totalRevenue = Purchase::sum('total_price');

        // Monthly revenue for the current year (for chart)
        $monthlyRevenue = Purchase::selectRaw('MONTH(purchase_date) as month, SUM(total_price) as revenue')
            ->whereYear('purchase_date', now()->year)
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('revenue', 'month');

        // Recent purchases with product info
        $recentPurchases = Purchase::with('productBatch.product.category')
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
