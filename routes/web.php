<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;


Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->intended(Auth::user()->role === 'admin' ? route('admin.dashboard') : route('pages.home'));
    }
    return view('auth');
})->name('auth');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('guest')->name('login');
Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

// Password Reset Routes temporarily added here for testing
Route::get('auth/forgot-password', function () {
    return view('auth.forgot-password');
})->name('forgot.password');


// User Home Page
Route::get('/pages/home', function () {
    return view('pages.home');
})->middleware('auth')->name('pages.home');

// Admin Routes - Protected with admin middleware
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('admin/dashboard', function () {
        return view('admin.dashboard');
    })->name('admin.dashboard');

    Route::resource('admin/categories', CategoryController::class);
    Route::resource('admin/suppliers', SupplierController::class);
    Route::resource('admin/items', ItemController::class);
});