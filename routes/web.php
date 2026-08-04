<?php

use App\Http\Controllers\Admin\BakeryController;
use App\Http\Controllers\HomeRedirectController;
use App\Http\Controllers\Owner\CustomerController;
use App\Http\Controllers\Owner\DashboardController;
use App\Http\Controllers\Owner\SaleController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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
        Route::get('bakeries/{bakery}/edit', [BakeryController::class, 'edit'])->name('bakeries.edit');
    });

// Bakery owner: sales, customers and flour-credit control panel.
Route::middleware(['auth', 'role:bakery_owner'])
    ->prefix('panel')
    ->name('panel.')
    ->group(function () {
        Route::get('subscription-expired', function () {
            return view('owner.subscription-expired');
        })->name('subscription.expired');

        Route::middleware('subscription.active')->group(function () {
            Route::get('dashboard', DashboardController::class)->name('dashboard');

            Route::resource('customers', CustomerController::class)->only(['index', 'create', 'show', 'edit']);

            Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
            Route::get('sales/create', [SaleController::class, 'create'])->name('sales.create');

            Route::get('settings', function () {
                return view('owner.settings.edit');
            })->name('settings.edit');
        });
    });

require __DIR__.'/auth.php';
