<?php

use App\Http\Controllers\Admin\BakeryController;
use App\Http\Controllers\HomeRedirectController;
use App\Http\Controllers\Owner\CustomerAccountController;
use App\Http\Controllers\Owner\CustomerController;
use App\Http\Controllers\Owner\DashboardController;
use App\Http\Controllers\Owner\SaleController;
use App\Http\Controllers\Owner\SettingsController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('/dashboard', HomeRedirectController::class)
    ->middleware(['auth'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Site owner: manage bakeries and their subscriptions.
Route::middleware(['auth', 'role:super_admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('bakeries', [BakeryController::class, 'index'])->name('bakeries.index');
        Route::get('bakeries/create', [BakeryController::class, 'create'])->name('bakeries.create');
        Route::post('bakeries', [BakeryController::class, 'store'])->name('bakeries.store');
        Route::get('bakeries/{bakery}/edit', [BakeryController::class, 'edit'])->name('bakeries.edit');
        Route::put('bakeries/{bakery}', [BakeryController::class, 'update'])->name('bakeries.update');
        Route::delete('bakeries/{bakery}', [BakeryController::class, 'destroy'])->name('bakeries.destroy');
        Route::post('bakeries/{bakery}/renew', [BakeryController::class, 'renew'])->name('bakeries.renew');
        Route::post('bakeries/{bakery}/status', [BakeryController::class, 'updateStatus'])->name('bakeries.status');
    });

// Bakery owner: sales, customers and flour-credit control panel.
Route::middleware(['auth', 'role:bakery_owner'])
    ->prefix('panel')
    ->name('panel.')
    ->group(function () {
        Route::inertia('subscription-expired', 'Owner/SubscriptionExpired')->name('subscription.expired');

        Route::middleware('subscription.active')->group(function () {
            Route::get('dashboard', DashboardController::class)->name('dashboard');

            Route::resource('customers', CustomerController::class);
            Route::post('customers/{customer}/deposits', [CustomerAccountController::class, 'deposit'])->name('customers.deposits.store');
            Route::post('customers/{customer}/flour-sales', [CustomerAccountController::class, 'flourSale'])->name('customers.flour-sales.store');
            Route::post('customers/{customer}/payments', [CustomerAccountController::class, 'payment'])->name('customers.payments.store');

            Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
            Route::get('sales/create', [SaleController::class, 'create'])->name('sales.create');
            Route::post('sales', [SaleController::class, 'store'])->name('sales.store');
            Route::post('sales/{sale}/mark-paid', [SaleController::class, 'markPaid'])->name('sales.mark-paid');

            Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        });
    });

require __DIR__.'/auth.php';
