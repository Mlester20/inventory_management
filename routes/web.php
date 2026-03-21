<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Api\ItemController as ApiItemController;
use App\Http\Controllers\Api\PurchaseController as ApiPurchaseController;

//redirect to login page if not authenticated, otherwise redirect to appropriate dashboard
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->intended(Auth::user()->role === 'admin' ? route('admin.dashboard') : route('pages.home'));
    }
    return view('auth');
})->name('auth');

// Authentication Routes
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('guest')->name('login');
Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');


//User Route - Protected with auth middleware
Route::middleware(['auth'])->group(function() {
    // Dashboard / Home Page
    Route::get('/pages/home', function() {
        return view('pages.home');
    })->name('pages.home');

    // Purchase History
    Route::get('/purchases/history', function() {
        return view('pages.purchase-history');

    })->name('purchases.history');
    // POS - Point of Sale
    Route::get('/pos', function() {
        return view('pages.pos');
    })->name('pos');

});


// Admin Routes - Protected with admin middleware
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('admin/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');

    // Admin Profile Routes
    Route::get('admin/profile/edit', [ProfileController::class, 'edit'])->name('admin.profile.edit');
    Route::put('admin/profile', [ProfileController::class, 'update'])->name('admin.profile.update');

    Route::resource('admin/categories', CategoryController::class);
    Route::resource('admin/suppliers', SupplierController::class);
    Route::resource('admin/items', ItemController::class);
    Route::resource('admin/users', UserController::class);
    Route::resource('admin/purchases', PurchaseController::class);    
    Route::get('admin/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');

    // Stock Management Routes
    Route::prefix('admin/stock')->name('stock.')->group(function () {
        Route::get('restock', [StockController::class, 'restockPage'])->name('restock-page');
        Route::post('items/{item}/restock', [StockController::class, 'restock'])->name('restock');
        Route::get('items/{item}/history', [StockController::class, 'history'])->name('history');
        Route::post('items/{item}/deduct', [StockController::class, 'deduct'])->name('deduct');
        Route::post('items/{item}/adjust', [StockController::class, 'adjust'])->name('adjust');
        Route::get('items/{item}/report', [StockController::class, 'report'])->name('report');
        Route::get('low-stock', [StockController::class, 'lowStockItems'])->name('low-stock');
        Route::get('out-of-stock', [StockController::class, 'outOfStockItems'])->name('out-of-stock');
    });
});

// API Routes - Protected with auth middleware
Route::middleware(['auth'])->prefix('api')->name('api.')->group(function () {
    // Items API
    Route::get('items', [ApiItemController::class, 'index'])->name('items.index');
    Route::get('items/{item}', [ApiItemController::class, 'show'])->name('items.show');

    // Purchases API
    Route::post('purchases', [ApiPurchaseController::class, 'store'])->name('purchases.store');
    Route::get('purchases/history', [ApiPurchaseController::class, 'history'])->name('purchases.history');
});

// Search API - Protected with auth middleware
Route::middleware(['auth'])->get('api/search', [SearchController::class, 'search'])->name('api.search');