<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\PurchaseController;

/*
|--------------------------------------------------------------------------
| Web Routes - Lumina Flora Retail System
|--------------------------------------------------------------------------
| Seluruh route terstruktur rapi menggunakan named routes, resource routes,
| dan middleware authorization server-side sesuai rubrik UTS.
*/

// Halaman Utama: Publik / Storefront Bunga (Bukan file index yang menyimpan data rahasia)
Route::get('/', function () {
    return view('welcome');
})->name('home');

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Protected Routes (Harus Login)
Route::middleware('auth')->group(function () {

    // 1. Dashboard Eksekutif (Hanya Owner/Admin)
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('can:owner-only')
        ->name('dashboard');

    // 2. Master Data: Products, Categories, Suppliers
    Route::resource('products', ProductController::class);
    Route::resource('categories', CategoryController::class);
    Route::resource('suppliers', SupplierController::class);

    // 3. Transaksi Penjualan (Staff & Owner)
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('/sales/create', [SaleController::class, 'create'])->name('sales.create');
    Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');

    // ATURAN KHUSUS BISNIS: HANYA OWNER YANG BISA MEMBATALKAN TRANSAKSI & RESTOCK
    Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel'])
        ->middleware('can:owner-only')
        ->name('sales.cancel');

    // 4. Pembelian Stok / Stock-in dari Pemasok (Staff & Owner)
    Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
    Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
});
