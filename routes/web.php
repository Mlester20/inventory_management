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
use App\Http\Controllers\Admin\CogsController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Api\ItemController as ApiItemController;
use App\Http\Controllers\Api\PurchaseController as ApiPurchaseController;
use App\Http\Controllers\ReturnItemController;
use App\Http\Controllers\ReturnItemController as ApiReturnItemController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\Api\ActivityLogController as ApiActivityLogController;

//redirect to login page if not authenticated, otherwise redirect to appropriate dashboard
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->intended(Auth::user()->role === 'admin' ? route('admin.dashboard') : route('pages.home'));
    }
    return view('auth');
})->name('auth');

// Authentication Routes
Route::get('/login', function () {
    return view('auth');
})->middleware('guest')->name('login');
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

    // Return Items
    Route::get('/returns', function() {
        return view('pages.return-items');
    })->name('returns');

    // POS - Point of Sale
    Route::get('/pos', function() {
        return view('pages.pos');
    })->name('pos');

    // User Profile
    Route::get('/profile', function() {
        $user = Auth::user();
        return view('pages.profile', compact('user'));
    })->name('profile');
    
    Route::get('/profile/edit', [UserProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [UserProfileController::class, 'update'])->name('profile.update');

    // Activity Log
    Route::get('/activity-log', function() {
        return view('pages.activity-log');
    })->name('pages.activity-log');

});

// Admin Routes - Protected with admin middleware
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('admin/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('admin/cogs', [CogsController::class, 'index'])->name('admin.cogs.index');

    // Admin Profile Routes
    Route::get('admin/profile/edit', [ProfileController::class, 'edit'])->name('admin.profile.edit');
    Route::put('admin/profile', [ProfileController::class, 'update'])->name('admin.profile.update');

    Route::resource('admin/categories', CategoryController::class);
    Route::resource('admin/suppliers', SupplierController::class);
    Route::resource('admin/items', ItemController::class);
    Route::resource('admin/users', UserController::class);
    Route::resource('admin/purchases', PurchaseController::class);    
    Route::get('admin/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::resource('admin/return-items', ReturnItemController::class);
    
    // Return Items Actions
    Route::post('admin/return-items/{returnItem}/approve', [ReturnItemController::class, 'approve'])->name('return-items.approve');
    Route::post('admin/return-items/{returnItem}/reject', [ReturnItemController::class, 'reject'])->name('return-items.reject');

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

    // Return Item API
    Route::post('return-items', [ApiReturnItemController::class, 'store'])->name('return-items.store');
    Route::get('return-items', [ApiReturnItemController::class, 'index'])->name('return-items.index');
    // Get Activity Log Api

    Route::get('activity-log', [ApiActivityLogController::class, 'index'])->name('activity-log.index');
});

// Search API - Protected with auth middleware
Route::middleware(['auth'])->get('api/search', [SearchController::class, 'search'])->name('api.search');