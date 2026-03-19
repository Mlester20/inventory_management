<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Admin\ProfileController;

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


// User Home Page
Route::get('/pages/home', function () {
    return view('pages.home');
})->middleware('auth')->name('pages.home');


// Admin Routes - Protected with admin middleware
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('admin/dashboard', function () {
        return view('admin.dashboard');
    })->name('admin.dashboard');

    // Admin Profile Routes
    Route::get('admin/profile/edit', [ProfileController::class, 'edit'])->name('admin.profile.edit');
    Route::put('admin/profile', [ProfileController::class, 'update'])->name('admin.profile.update');

    Route::resource('admin/categories', CategoryController::class);
    Route::resource('admin/suppliers', SupplierController::class);
    Route::resource('admin/items', ItemController::class);
    Route::resource('admin/users', UserController::class);
});