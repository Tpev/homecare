<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MarketingPagesController;

use App\Http\Controllers\AdminLeadsController;

Route::middleware(['web','auth','admin.email'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/leads', [AdminLeadsController::class, 'index'])->name('leads.index');
    });
Route::get('/', [MarketingPagesController::class, 'landing'])->name('landing');
Route::get('/families', [MarketingPagesController::class, 'family'])->name('landing.family');
Route::get('/caregivers', [MarketingPagesController::class, 'caregiver'])->name('landing.caregiver');
Route::get('/agencies', [MarketingPagesController::class, 'agency'])->name('landing.agency');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';