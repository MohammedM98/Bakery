<?php

use App\Http\Controllers\Api\Admin\BakeryController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\OperationController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SalesLogController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

/*
 * The mobile app's private API (section 13), documented in docs/api.md. Token auth (Sanctum), JSON only.
 * Reads mirror the /panel and /admin route groups and their `can:` gates (section 5, 9-ب item 7).
 * Writes go through App\Http\Api\Operations, which checks role, subscription and permission per operation
 * (so a queued offline batch can be judged one operation at a time) and runs the website's Form Request and Action.
 */

/** A write route: the operation type travels as a route default; the record ids in the URL join the payload. */
$operation = fn (string $method, string $uri, string $type) => Route::$method($uri, OperationController::class)
    ->defaults('operation', $type)->whereNumber(['customer_id', 'sale_id', 'bank_id', 'user_id', 'bakery_id'])->name($type);

Route::prefix('v1')->name('api.')->group(function () use ($operation) {
    Route::post('login', [AuthController::class, 'login'])->name('login');

    Route::middleware(['auth:sanctum', 'active'])->group(function () use ($operation) {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');

        Route::middleware('role:bakery_owner,bakery_staff')->group(function () use ($operation) {
            // The subscription is checked per operation (push) and in the answer (pull), not for the whole request.
            Route::post('sync/push', [SyncController::class, 'push'])->name('sync.push');
            Route::get('sync/pull', [SyncController::class, 'pull'])->name('sync.pull');

            Route::middleware('subscription.active')->group(function () use ($operation) {
                Route::get('dashboard', DashboardController::class)->middleware('can:view-dashboard')->name('dashboard');
                Route::get('customers', [CustomerController::class, 'index'])->middleware('can:view-customers')->name('customers.index');
                Route::get('customers/{customer}', [CustomerController::class, 'show'])->whereNumber('customer')->middleware('can:view-customers')->name('customers.show');
                Route::get('sales', SaleController::class)->middleware('can:view-sales')->name('sales.index');
                Route::get('sales-log', SalesLogController::class)->middleware('can:view-sales-log')->name('sales-log');
                Route::get('banks', BankController::class)->middleware('can:view-banks')->name('banks.index');
                Route::get('users', StaffController::class)->middleware('can:manage-users')->name('users.index');
                Route::get('settings', SettingsController::class)->middleware('can:manage-settings')->name('settings.show');

                $operation('post', 'sales', 'sale');
                $operation('post', 'sales/{sale_id}/mark-paid', 'mark_paid');
                $operation('post', 'customers', 'customer.create');
                $operation('put', 'customers/{customer_id}', 'customer.update');
                $operation('delete', 'customers/{customer_id}', 'customer.delete');
                $operation('post', 'customers/{customer_id}/flour-sales', 'flour_sale');
                $operation('post', 'customers/{customer_id}/deposits', 'deposit');
                $operation('post', 'customers/{customer_id}/payments', 'payment');
                $operation('post', 'customers/{customer_id}/adjustments', 'adjustment');
                $operation('post', 'banks', 'bank.create');
                $operation('put', 'banks/{bank_id}', 'bank.update');
                $operation('delete', 'banks/{bank_id}', 'bank.delete');
                $operation('put', 'settings', 'settings.update');
                $operation('post', 'users', 'staff.create');
                $operation('put', 'users/{user_id}', 'staff.update');
                $operation('post', 'users/{user_id}/status', 'staff.status');
            });
        });

        Route::middleware('role:super_admin')->prefix('admin')->name('admin.')->group(function () use ($operation) {
            Route::get('bakeries', [BakeryController::class, 'index'])->name('bakeries.index');
            Route::get('bakeries/{bakery}', [BakeryController::class, 'show'])->whereNumber('bakery')->name('bakeries.show');

            $operation('post', 'bakeries', 'bakery.create');
            $operation('put', 'bakeries/{bakery_id}', 'bakery.update');
            $operation('post', 'bakeries/{bakery_id}/renew', 'bakery.renew');
            $operation('post', 'bakeries/{bakery_id}/status', 'bakery.status');
        });
    });
});
