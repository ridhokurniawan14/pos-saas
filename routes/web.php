<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentSettingController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReceiptSettingController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\StockController;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::resource('products', ProductController::class)->except('show');
    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('stock', [StockController::class, 'index'])->name('stock.index');
    Route::post('stock/adjust', [StockController::class, 'adjust'])->name('stock.adjust');

    Route::get('pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('pos/orders', [PosController::class, 'store'])->name('pos.orders.store');

    Route::get('payment-settings', [PaymentSettingController::class, 'index'])->name('payments.index');
    Route::put('payment-settings', [PaymentSettingController::class, 'update']);
    Route::post('payment-settings/qris', [PaymentSettingController::class, 'qris']);
    Route::delete('payment-settings/qris', [PaymentSettingController::class, 'destroyQris']);
    Route::post('payment-settings/banks', [PaymentSettingController::class, 'storeBank']);
    Route::delete('payment-settings/banks/{id}', [PaymentSettingController::class, 'destroyBank']);

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{id}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{id}/void', [OrderController::class, 'void'])->name('orders.void');

    Route::get('shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::post('shifts', [ShiftController::class, 'store'])->name('shifts.store');
    Route::get('shifts/{id}', [ShiftController::class, 'show'])->name('shifts.show');
    Route::post('shifts/{id}/cash', [ShiftController::class, 'cash'])->name('shifts.cash');
    Route::post('shifts/{id}/close', [ShiftController::class, 'close'])->name('shifts.close');

    Route::get('receipt-settings', [ReceiptSettingController::class, 'index'])->name('receipt.index');
    Route::put('receipt-settings/business', [ReceiptSettingController::class, 'business']);
    Route::put('receipt-settings/outlets/{id}', [ReceiptSettingController::class, 'outlet']);
    Route::post('receipt-settings/logo', [ReceiptSettingController::class, 'logo']);
    Route::delete('receipt-settings/logo', [ReceiptSettingController::class, 'destroyLogo']);
});

Route::middleware('auth')->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
});

Route::middleware('guest')->group(function () {
    Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');
});

require __DIR__ . '/settings.php';
