<?php

use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DeliveryReceiptController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SalesOrderController;
use Illuminate\Support\Facades\Route;

/*
 * The read-only API. Every call needs a token that carries the "read" ability (php artisan api:token),
 * sent as "Authorization: Bearer <token>". There are only GET routes: nothing here can change data.
 */
Route::prefix('v1')->middleware(['auth:sanctum', 'abilities:read', 'throttle:api'])->group(function () {
    Route::get('customers', [CustomerController::class, 'index']);
    Route::get('customers/{customer}', [CustomerController::class, 'show']);

    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);

    Route::get('sales-orders', [SalesOrderController::class, 'index']);
    Route::get('sales-orders/{salesOrder}', [SalesOrderController::class, 'show']);

    Route::get('delivery-receipts', [DeliveryReceiptController::class, 'index']);
    Route::get('delivery-receipts/{deliveryReceipt}', [DeliveryReceiptController::class, 'show']);

    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
});
