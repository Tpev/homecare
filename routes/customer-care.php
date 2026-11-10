<?php

use App\Http\Controllers\CustomerCareController;
use App\Http\Middleware\CareHoursPrivacyHeaders;
use App\Http\Middleware\EnsureCustomerCareAccess;
use Illuminate\Support\Facades\Route;

// Dedicated CS workflow access; existing administrator billing/refund routes stay administrator-only.
Route::middleware(['auth', EnsureCustomerCareAccess::class, CareHoursPrivacyHeaders::class])
    ->prefix('admin/customer-care')->name('customer-care.')->group(function (): void {
        Route::get('/', [CustomerCareController::class, 'index'])->name('index');
        Route::get('/gifts', [CustomerCareController::class, 'gifts'])->name('gifts');
        Route::post('/customers', [CustomerCareController::class, 'createCustomer'])->middleware('throttle:20,1')->name('customers.store');
        Route::get('/customers/{customer}', [CustomerCareController::class, 'customer'])->whereNumber('customer')->name('customer');
        Route::post('/customers/{customer}/credits', [CustomerCareController::class, 'credit'])->whereNumber('customer')->middleware('throttle:20,1')->name('credit');
        Route::get('/customers/{customer}/requests/create', [CustomerCareController::class, 'create'])->whereNumber('customer')->name('create');
        Route::post('/customers/{customer}/requests', [CustomerCareController::class, 'store'])->whereNumber('customer')->name('store');
        Route::get('/requests/{careRequest}', [CustomerCareController::class, 'show'])->whereNumber('careRequest')->name('show');
        Route::get('/requests/{careRequest}/edit', [CustomerCareController::class, 'edit'])->whereNumber('careRequest')->name('edit');
        Route::post('/requests/{careRequest}', [CustomerCareController::class, 'update'])->whereNumber('careRequest')->name('update');
        Route::post('/requests/{careRequest}/actions', [CustomerCareController::class, 'action'])->whereNumber('careRequest')->middleware('throttle:60,1')->name('action');
    });
