<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\ItemController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('admin/dashboard', function () {
    return view('admin.dashboard');
})->name('admin.dashboard');

// Category routes
Route::resource('admin/categories', CategoryController::class);

// Supplier routes
Route::resource('admin/suppliers', SupplierController::class);

// Item routes
Route::resource('admin/items', ItemController::class);