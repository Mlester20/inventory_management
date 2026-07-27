<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class HomeController extends Controller
{
    /**
     * The POS "Dashboard" landing page — a personal mini-dashboard for the
     * logged-in cashier (today's/this month's own sales, all-time count,
     * and their most recent transactions), distinct from the full Sales
     * History table at `purchases.history`.
     */
    public function index()
    {
        $userId = Auth::id();

        $todayQuery = Purchase::where('user_id', $userId)->whereDate('purchase_date', Carbon::today());
        $todayCount = (clone $todayQuery)->count();
        $todayAmount = (clone $todayQuery)->sum('total_price');

        $monthQuery = Purchase::where('user_id', $userId)
            ->whereYear('purchase_date', Carbon::today()->year)
            ->whereMonth('purchase_date', Carbon::today()->month);
        $monthCount = (clone $monthQuery)->count();
        $monthAmount = (clone $monthQuery)->sum('total_price');

        $allTimeCount = Purchase::where('user_id', $userId)->count();

        $recentTransactions = Purchase::with('productBatch.product.category')
            ->where('user_id', $userId)
            ->latest('purchase_date')
            ->take(5)
            ->get();

        return view('pages.home', compact(
            'todayCount',
            'todayAmount',
            'monthCount',
            'monthAmount',
            'allTimeCount',
            'recentTransactions',
        ));
    }
}
