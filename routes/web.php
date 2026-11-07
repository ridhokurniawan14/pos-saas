<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentSettingController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PosDraftController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReceiptSettingController;
use App\Http\Controllers\ReceivableController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('business/switch', [BusinessController::class, 'switch'])->name('business.switch');

    Route::middleware('access:pos')->group(function () {
        Route::get('pos', [PosController::class, 'index'])->name('pos.index');
        Route::post('pos/orders', [PosController::class, 'store'])->name('pos.orders.store');
        Route::post('pos/drafts', [PosDraftController::class, 'store'])->name('pos.drafts.store');
        Route::delete('pos/drafts/{id}', [PosDraftController::class, 'destroy'])->name('pos.drafts.destroy');
        Route::get('pos/customers', [CustomerController::class, 'search']);
        Route::post('pos/customers', [CustomerController::class, 'quick']);
    });

    Route::middleware('access:orders')->group(function () {
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{id}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{id}/void', [OrderController::class, 'void'])->name('orders.void');
    });

    Route::middleware('access:shifts')->group(function () {
        Route::get('shifts', [ShiftController::class, 'index'])->name('shifts.index');
        Route::post('shifts', [ShiftController::class, 'store'])->name('shifts.store');
        Route::get('shifts/{id}', [ShiftController::class, 'show'])->name('shifts.show');
        Route::post('shifts/{id}/cash', [ShiftController::class, 'cash'])->name('shifts.cash');
        Route::post('shifts/{id}/close', [ShiftController::class, 'close'])->name('shifts.close');
    });

    Route::middleware('access:customers')->group(function () {
        Route::resource('customers', CustomerController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
        Route::post('customers/{id}/payments', [ReceivableController::class, 'store']);
    });

    Route::middleware('access:products')->group(function () {
        Route::resource('products', ProductController::class)->except('show');
    });

    Route::middleware('access:categories')->group(function () {
        Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    Route::middleware('access:stock')->group(function () {
        Route::get('stock', [StockController::class, 'index'])->name('stock.index');
        Route::post('stock/adjust', [StockController::class, 'adjust'])->name('stock.adjust');
    });

    Route::middleware('access:suppliers')->group(function () {
        Route::resource('suppliers', SupplierController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    Route::middleware('access:purchases')->group(function () {
        Route::resource('purchases', PurchaseController::class)->only(['index', 'create', 'store', 'show']);
    });

    Route::middleware('access:reports')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    });

    Route::middleware('access:expenses')->group(function () {
        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::delete('expenses/{id}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    });

    Route::middleware('access:payments')->group(function () {
        Route::get('payment-settings', [PaymentSettingController::class, 'index'])->name('payments.index');
        Route::put('payment-settings', [PaymentSettingController::class, 'update']);
        Route::post('payment-settings/qris', [PaymentSettingController::class, 'qris']);
        Route::delete('payment-settings/qris', [PaymentSettingController::class, 'destroyQris']);
        Route::post('payment-settings/banks', [PaymentSettingController::class, 'storeBank']);
        Route::delete('payment-settings/banks/{id}', [PaymentSettingController::class, 'destroyBank']);
    });

    Route::middleware('access:settings')->group(function () {
        Route::get('receipt-settings', [ReceiptSettingController::class, 'index'])->name('receipt.index');
        Route::put('receipt-settings/business', [ReceiptSettingController::class, 'business']);
        Route::put('receipt-settings/outlets/{id}', [ReceiptSettingController::class, 'outlet']);
        Route::post('receipt-settings/logo', [ReceiptSettingController::class, 'logo']);
        Route::delete('receipt-settings/logo', [ReceiptSettingController::class, 'destroyLogo']);
        Route::put('receipt-settings/loyalty', [ReceiptSettingController::class, 'loyalty']);
    });

    Route::middleware('access:users')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users/invitations', [UserController::class, 'invite'])->name('users.invite');
        Route::delete('users/invitations/{id}', [UserController::class, 'cancelInvite'])->name('users.invite.cancel');
        Route::put('users/{userId}', [UserController::class, 'updateRole'])->name('users.role');
        Route::delete('users/{userId}', [UserController::class, 'remove'])->name('users.remove');
    });
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
