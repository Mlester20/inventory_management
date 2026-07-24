<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\GenericNameController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\InventoryItemsController;
use App\Http\Controllers\InventoryAdjustmentController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\CogsController;
use App\Http\Controllers\Admin\ProductExpirationReportController;
use App\Http\Controllers\Admin\InventoryReportController;
use App\Http\Controllers\Admin\SalesReportController;
use App\Http\Controllers\Admin\PurchaseReportController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TaxesController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\DeliveryReceiptController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\GoodsReceiptController;
use App\Http\Controllers\Api\ItemController as ApiItemController;
use App\Http\Controllers\Api\PurchaseController as ApiPurchaseController;
use App\Http\Controllers\Api\GenericNameController as ApiGenericNameController;
use App\Http\Controllers\Api\SalesOrderController as ApiSalesOrderController;
use App\Http\Controllers\Api\PurchaseOrderController as ApiPurchaseOrderController;
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

// Transaction Routes - usable from both the Admin side and the POS side,
// so open to any authenticated user (admin or regular user), not just admins.
// Deleting a transaction stays admin-only (registered in the admin group).
Route::middleware(['auth'])->group(function () {
    Route::resource('admin/invoices', InvoiceController::class)->except(['destroy']);
    Route::resource('admin/sales-orders', SalesOrderController::class)->except(['destroy']);
    Route::resource('admin/delivery-receipts', DeliveryReceiptController::class)->except(['destroy']);
    Route::post('admin/delivery-receipts/{deliveryReceipt}/create-invoice', [DeliveryReceiptController::class, 'createInvoice'])->name('delivery-receipts.create-invoice');
});

// Admin Routes - Protected with admin middleware 
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('admin/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('admin/cogs', [CogsController::class, 'index'])->name('admin.cogs.index');
    Route::get('admin/reports/expiration', [ProductExpirationReportController::class, 'index'])->name('admin.reports.expiration');
    Route::get('admin/reports/inventory-summary', [InventoryReportController::class, 'summary'])->name('admin.reports.inventory-summary');
    Route::get('admin/reports/product-history', [InventoryReportController::class, 'productHistory'])->name('admin.reports.product-history');
    Route::get('admin/reports/sales-summary', [SalesReportController::class, 'summary'])->name('admin.reports.sales-summary');
    Route::get('admin/reports/sales-per-customer', [SalesReportController::class, 'perCustomer'])->name('admin.reports.sales-per-customer');
    Route::get('admin/reports/purchase-summary', [PurchaseReportController::class, 'summary'])->name('admin.reports.purchase-summary');
    Route::get('admin/reports/purchases-per-supplier', [PurchaseReportController::class, 'perSupplier'])->name('admin.reports.purchases-per-supplier');

    // Admin Profile Routes
    Route::get('admin/profile/edit', [ProfileController::class, 'edit'])->name('admin.profile.edit');
    Route::put('admin/profile', [ProfileController::class, 'update'])->name('admin.profile.update');

    Route::resource('admin/categories', CategoryController::class);
    Route::resource('admin/generic-names', GenericNameController::class)->only(['store', 'update', 'destroy']);
    Route::resource('admin/suppliers', SupplierController::class);
    Route::resource('admin/customers', CustomerController::class);

    // Products & Inventory module — a single tabbed screen (General Item /
    // Products / Lot-Serial & Expiry / Product History); see
    // InventoryItemsController for the tab-driven index.
    Route::get('admin/inventory-items', [InventoryItemsController::class, 'index'])->name('inventory-items.index');
    Route::get('admin/items/{product}', function (\App\Models\Product $product) {
        return redirect()->route('inventory-items.index', ['tab' => 'products', 'search' => $product->item_name]);
    })->name('admin.items.show');
    Route::resource('admin/products', ProductController::class)->only(['store', 'update', 'destroy']);
    Route::resource('admin/inventory-adjustments', InventoryAdjustmentController::class)->only(['index', 'create', 'store', 'show']);

    Route::resource('admin/users', UserController::class);
    Route::resource('admin/purchases', PurchaseController::class);    
    Route::get('admin/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::resource('admin/return-items', ReturnItemController::class);
    Route::resource('admin/taxes', TaxesController::class);
    Route::resource('admin/purchase-orders', PurchaseOrderController::class);
    Route::resource('admin/goods-receipts', GoodsReceiptController::class)->except(['destroy']);

    // Invoices, Sales Orders, and Delivery Receipts are usable by both admin
    // and regular users (see the 'auth'-only group below) — deleting them
    // is the one action that stays admin-only.
    Route::delete('admin/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    Route::delete('admin/sales-orders/{sales_order}', [SalesOrderController::class, 'destroy'])->name('sales-orders.destroy');

    // Return Items Actions
    Route::post('admin/return-items/{returnItem}/approve', [ReturnItemController::class, 'approve'])->name('return-items.approve');
    Route::post('admin/return-items/{returnItem}/reject', [ReturnItemController::class, 'reject'])->name('return-items.reject');
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

    // Generic Names API (Delivery Receipt "Available Product?" check)
    Route::get('generic-names/{genericName}/available-items', [ApiGenericNameController::class, 'availableItems'])->name('generic-names.available-items');

    // Sales Orders API (Purchase-Order-mode Delivery Receipt remaining lines)
    Route::get('sales-orders/{salesOrder}/remaining-items', [ApiSalesOrderController::class, 'remainingItems'])->name('sales-orders.remaining-items');

    // Purchase Orders API (Goods-Receipt-against-Purchase-Order pending lines)
    Route::get('purchase-orders/{purchaseOrder}/pending-items', [ApiPurchaseOrderController::class, 'pendingItems'])->name('purchase-orders.pending-items');
});

// Search API - Protected with auth middleware
Route::middleware(['auth'])->get('api/search', [SearchController::class, 'search'])->name('api.search');