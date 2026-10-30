<?php

use App\Http\Controllers\CareHours\AdminCareHoursController;
use App\Http\Controllers\CareHours\AdminGiftController;
use App\Http\Controllers\CareHours\ContributionController;
use App\Http\Controllers\CareHours\FamilyCareHoursController;
use App\Http\Controllers\CareHours\GiftClaimController;
use App\Http\Controllers\CareHours\GiftClaimEntryController;
use App\Http\Controllers\CareHours\GiftController;
use App\Http\Middleware\CareHoursPrivacyHeaders;
use Illuminate\Support\Facades\Route;

// A buyer retains their own receipts after leaving a family account.
Route::get('/family/care-hours/purchases/{purchase:public_id}', [FamilyCareHoursController::class, 'purchase'])
    ->middleware(['auth', CareHoursPrivacyHeaders::class])->name('family.care-hours.purchase');

Route::middleware(['auth', 'family.role', CareHoursPrivacyHeaders::class])
    ->prefix('family/care-hours')->name('family.care-hours.')->group(function () {
        Route::get('/', [FamilyCareHoursController::class, 'index'])->name('index');
        Route::get('/buy', [FamilyCareHoursController::class, 'buy'])->name('buy');
        Route::post('/buy', [FamilyCareHoursController::class, 'startBuy'])->name('buy.start');
        Route::get('/claim', [GiftClaimController::class, 'index'])->name('claim');
        Route::post('/claim/preview', [GiftClaimController::class, 'preview'])->middleware('throttle:8,1,care-hours-claim.preview:')->name('claim.preview');
        Route::post('/claim/confirm', [GiftClaimController::class, 'confirm'])->middleware('throttle:8,1,care-hours-claim.confirm:')->name('claim.confirm');
        Route::post('/claim/reset', [GiftClaimController::class, 'reset'])->name('claim.reset');
        Route::get('/claim/ready/{purchase:public_id}', [GiftClaimController::class, 'ready'])->name('claim.ready');
        Route::post('/enroll', [FamilyCareHoursController::class, 'enroll'])->name('enroll');
        Route::get('/{account}', [FamilyCareHoursController::class, 'show'])->whereNumber('account')->name('show');
        Route::get('/{account}/buy', [FamilyCareHoursController::class, 'packages'])->whereNumber('account')->name('packages');
        Route::post('/{account}/checkout', [FamilyCareHoursController::class, 'checkout'])->middleware('throttle:20,1,care-hours-checkout:')->whereNumber('account')->name('checkout');
        Route::post('/{account}/contribution-link', [FamilyCareHoursController::class, 'createLink'])->whereNumber('account')->name('link.create');
        Route::delete('/{account}/contribution-link/{link}', [FamilyCareHoursController::class, 'revokeLink'])->whereNumber(['account', 'link'])->name('link.revoke');
        Route::post('/plans/{plan}/funding', [FamilyCareHoursController::class, 'planFunding'])->whereNumber('plan')->name('plan');
        Route::post('/visits/{booking}/shortfall', [FamilyCareHoursController::class, 'shortfall'])->whereNumber('booking')->middleware('throttle:10,1,care-hours-shortfall:')->name('shortfall');
        Route::get('/visits/{booking}/payment-return', [FamilyCareHoursController::class, 'shortfallReturn'])->whereNumber('booking')->middleware('throttle:20,1,care-hours-shortfall.return:')->name('shortfall.return');
        Route::post('/changes/{change}/payment', [FamilyCareHoursController::class, 'scheduleChangePayment'])->whereNumber('change')->middleware('throttle:10,1,care-hours-change.payment:')->name('change.payment');
        Route::get('/changes/{change}/payment-return', [FamilyCareHoursController::class, 'scheduleChangeReturn'])->whereNumber('change')->middleware('throttle:20,1,care-hours-change.return:')->name('change.return');
        Route::post('/visits/{booking}/coverage', [FamilyCareHoursController::class, 'coverage'])->whereNumber('booking')->middleware('throttle:20,1,care-hours-coverage:')->name('coverage');
    });

Route::middleware([CareHoursPrivacyHeaders::class, 'throttle:60,1,care-hours-public:'])
    ->prefix('care-hours')->name('care-hours.')->group(function () {
        Route::get('/claim', [GiftClaimEntryController::class, 'index'])->name('claim');
        Route::post('/claim', [GiftClaimEntryController::class, 'start'])->middleware('throttle:8,1,care-hours-claim.start:')->name('claim.start');
        Route::post('/claim/change-code', [GiftClaimEntryController::class, 'reset'])->middleware('throttle:10,1,care-hours-claim.reset:')->name('claim.reset');
        Route::get('/gifts', [GiftController::class, 'index'])->name('gifts.index');
        Route::post('/gifts/verify', [GiftController::class, 'requestCode'])->middleware('throttle:5,1,care-hours-gifts.verify:')->name('gifts.verify');
        Route::post('/gifts/confirm-email', [GiftController::class, 'confirmCode'])->middleware('throttle:10,1,care-hours-gifts.confirm:')->name('gifts.confirm');
        Route::post('/gifts/checkout', [GiftController::class, 'checkout'])->middleware('throttle:10,1,care-hours-gifts.checkout:')->name('gifts.checkout');
        Route::get('/gifts/receipts/{purchase:public_id}', [GiftController::class, 'receipt'])->name('gifts.receipt');
        Route::post('/gifts/receipts/{purchase:public_id}/verify', [GiftController::class, 'receiptCode'])->middleware('throttle:5,1,care-hours-gifts.receipt.verify:')->name('gifts.receipt.verify');
        Route::post('/gifts/receipts/{purchase:public_id}/confirm', [GiftController::class, 'receiptConfirm'])->middleware('throttle:10,1,care-hours-gifts.receipt.confirm:')->name('gifts.receipt.confirm');
        Route::get('/give/{token}', [ContributionController::class, 'show'])->name('contribute');
        Route::post('/give/{token}/verify', [ContributionController::class, 'requestCode'])->middleware('throttle:5,1,care-hours-contribute.verify:')->name('contribute.verify');
        Route::post('/give/{token}/confirm-email', [ContributionController::class, 'confirmCode'])->middleware('throttle:10,1,care-hours-contribute.confirm:')->name('contribute.confirm');
        Route::post('/give/{token}/checkout', [ContributionController::class, 'checkout'])->middleware('throttle:10,1,care-hours-contribute.checkout:')->name('contribute.checkout');
        Route::get('/receipts/{purchase:public_id}', [ContributionController::class, 'receipt'])->name('receipt');
        Route::post('/receipts/{purchase:public_id}/verify', [ContributionController::class, 'receiptCode'])->middleware('throttle:5,1,care-hours-receipt.verify:')->name('receipt.verify');
        Route::post('/receipts/{purchase:public_id}/confirm', [ContributionController::class, 'receiptConfirm'])->middleware('throttle:10,1,care-hours-receipt.confirm:')->name('receipt.confirm');
    });

Route::middleware(['auth', 'admin.email', CareHoursPrivacyHeaders::class])
    ->prefix('admin/care-hours')->name('admin.care-hours.')->group(function () {
        Route::get('/', [AdminCareHoursController::class, 'index'])->name('index');
        Route::get('/gifts', [AdminGiftController::class, 'index'])->name('gifts.index');
        Route::get('/gifts/{purchase:public_id}', [AdminGiftController::class, 'show'])->name('gifts.show');
        Route::post('/gifts/{purchase:public_id}/recheck', [AdminGiftController::class, 'recheck'])->middleware('throttle:10,1,care-hours-gifts.recheck:')->name('gifts.recheck');
        Route::get('/{account}', [AdminCareHoursController::class, 'show'])->whereNumber('account')->name('show');
        Route::post('/{account}/purchases/{purchase}/recheck', [AdminCareHoursController::class, 'recheck'])->whereNumber(['account', 'purchase'])->middleware('throttle:10,1,care-hours-recheck:')->name('recheck');
        Route::post('/{account}/purchases/{purchase}/correct', [AdminCareHoursController::class, 'correct'])->whereNumber(['account', 'purchase'])->middleware('throttle:10,1,care-hours-correct:')->name('correct');
        Route::post('/{account}/visits/{funding}/recheck', [AdminCareHoursController::class, 'recheckVisit'])->whereNumber(['account', 'funding'])->middleware('throttle:10,1,care-hours-visit.recheck:')->name('visit.recheck');
    });
