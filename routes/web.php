<?php

use App\Http\Controllers\Admin\BakeryController;
use App\Http\Controllers\HomeRedirectController;
use App\Http\Controllers\Owner\BankController;
use App\Http\Controllers\Owner\CustomerAccountController;
use App\Http\Controllers\Owner\CustomerController;
use App\Http\Controllers\Owner\DashboardController;
use App\Http\Controllers\Owner\SaleController;
use App\Http\Controllers\Owner\SalesLogController;
use App\Http\Controllers\Owner\SettingsController;
use App\Http\Controllers\Owner\StatementExportController;
use App\Http\Controllers\Owner\UserController;
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
        Route::post('bakeries', [BakeryController::class, 'store'])->name('bakeries.store');
        Route::get('bakeries/{bakery}/edit', [BakeryController::class, 'edit'])->name('bakeries.edit');
        Route::put('bakeries/{bakery}', [BakeryController::class, 'update'])->name('bakeries.update');
        Route::delete('bakeries/{bakery}', [BakeryController::class, 'destroy'])->name('bakeries.destroy');
        Route::post('bakeries/{bakery}/renew', [BakeryController::class, 'renew'])->name('bakeries.renew');
        Route::post('bakeries/{bakery}/status', [BakeryController::class, 'updateStatus'])->name('bakeries.status');
    });

// Bakery owner and staff: sales, customers and flour-credit control panel.
// Every route checks its permission (9-ب item 7, gates in AppServiceProvider); the owner holds them all.
Route::middleware(['auth', 'role:bakery_owner,bakery_staff'])
    ->prefix('panel')
    ->name('panel.')
    ->group(function () {
        Route::inertia('subscription-expired', 'Owner/SubscriptionExpired')->name('subscription.expired');

        Route::middleware('subscription.active')->group(function () {
            // Where a staff member with no page to open lands, instead of a 403.
            Route::inertia('no-permissions', 'Owner/NoPermissions')->name('no-permissions');

            Route::get('dashboard', DashboardController::class)->middleware('can:view-dashboard')->name('dashboard');

            Route::get('customers', [CustomerController::class, 'index'])->middleware('can:view-customers')->name('customers.index');
            Route::get('customers/create', [CustomerController::class, 'create'])->middleware('can:create-customers')->name('customers.create');
            Route::post('customers', [CustomerController::class, 'store'])->middleware('can:create-customers')->name('customers.store');
            Route::get('customers/{customer}', [CustomerController::class, 'show'])->middleware('can:view-customers')->name('customers.show');
            Route::match(['put', 'patch'], 'customers/{customer}', [CustomerController::class, 'update'])->middleware('can:edit-customers')->name('customers.update');
            Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->middleware('can:delete-customers')->name('customers.destroy');
            Route::post('customers/{customer}/deposits', [CustomerAccountController::class, 'deposit'])->middleware('can:record-deposit')->name('customers.deposits.store');
            Route::post('customers/{customer}/flour-sales', [CustomerAccountController::class, 'flourSale'])->middleware('can:record-flour-sale')->name('customers.flour-sales.store');
            Route::post('customers/{customer}/payments', [CustomerAccountController::class, 'payment'])->middleware('can:record-payment')->name('customers.payments.store');
            Route::post('customers/{customer}/adjustments', [CustomerAccountController::class, 'adjustment'])->middleware('can:record-adjustment')->name('customers.adjustments.store');
            // export-statements plus the view permission of that ledger: checked in ExportStatementRequest.
            Route::get('customers/{customer}/statement/{ledger}.{format}', StatementExportController::class)
                ->whereIn('ledger', ['money', 'flour'])->whereIn('format', ['pdf', 'xlsx'])
                ->middleware('can:export-statements')
                ->name('customers.statement.export');

            Route::get('sales', [SaleController::class, 'index'])->middleware('can:view-sales')->name('sales.index');
            Route::get('sales/create', [SaleController::class, 'create'])->middleware('can:record-sale')->name('sales.create');
            Route::post('sales', [SaleController::class, 'store'])->middleware('can:record-sale')->name('sales.store');
            // Confirming the rest of a sale as received is a payment.
            Route::post('sales/{sale}/mark-paid', [SaleController::class, 'markPaid'])->middleware('can:record-payment')->name('sales.mark-paid');
            Route::get('sales-log', SalesLogController::class)->middleware('can:view-sales-log')->name('sales-log');

            Route::get('banks', [BankController::class, 'index'])->middleware('can:view-banks')->name('banks.index');
            Route::post('banks', [BankController::class, 'store'])->middleware('can:manage-banks')->name('banks.store');
            Route::match(['put', 'patch'], 'banks/{bank}', [BankController::class, 'update'])->middleware('can:manage-banks')->name('banks.update');
            Route::delete('banks/{bank}', [BankController::class, 'destroy'])->middleware('can:delete-banks')->name('banks.destroy');

            Route::middleware('can:manage-settings')->group(function () {
                Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
                Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
            });

            // The owner's staff accounts and their permissions. Owner only.
            Route::middleware('can:manage-users')->group(function () {
                Route::get('users', [UserController::class, 'index'])->name('users.index');
                Route::post('users', [UserController::class, 'store'])->name('users.store');
                Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
                Route::post('users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status');
            });
        });
    });

require __DIR__.'/auth.php';
