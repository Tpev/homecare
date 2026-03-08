<?php

use App\Http\Controllers\AdminLeadsController;
use App\Http\Controllers\CaregiverIdentityVerificationController;
use App\Http\Controllers\DiditWebhookController;
use App\Http\Controllers\MarketingPagesController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SeoPagesController;
use App\Http\Controllers\SitemapController;
use App\Livewire\Admin\FunnelAnalytics;
use App\Livewire\Admin\CaregiverReviewsQueue;
use App\Livewire\Admin\SupportTicketsQueue;
use App\Livewire\Caregiver\ApplyToCareRequest;
use App\Livewire\Caregiver\BrowseCareRequests;
use App\Livewire\Caregiver\BrowseCaregivers;
use App\Livewire\Caregiver\InvitationsIndex;
use App\Livewire\Caregiver\ProfileEditor;
use App\Livewire\Caregiver\ShowCaregiver;
use App\Livewire\Dashboard\Home as DashboardHome;
use App\Livewire\Family\AiRequestCopilot;
use App\Livewire\Family\CreateCareRequestWizard;
use App\Livewire\Family\ManageCareRequest;
use App\Livewire\Family\RequestsIndex;
use App\Livewire\Messaging\Inbox;
use App\Livewire\Support\TicketsCenter;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'admin.email'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/leads', [AdminLeadsController::class, 'index'])->name('leads.index');
        Route::get('/caregivers/reviews', CaregiverReviewsQueue::class)->name('caregivers.reviews');
        Route::get('/caregivers/moderation-logs', \App\Livewire\Admin\CaregiverModerationLogs::class)
            ->name('caregivers.moderation_logs');
        Route::get('/support/tickets', SupportTicketsQueue::class)->name('support.tickets');
        Route::get('/analytics/funnel', FunnelAnalytics::class)->name('analytics.funnel');
    });

Route::get('/', [MarketingPagesController::class, 'landing'])->name('landing');
Route::get('/families', [MarketingPagesController::class, 'family'])->name('landing.family');
Route::get('/caregivers', [MarketingPagesController::class, 'caregiver'])->name('landing.caregiver');
Route::get('/agencies', [MarketingPagesController::class, 'agency'])->name('landing.agency');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.xml');
Route::get('/robots.txt', [RobotsController::class, 'index'])->name('robots.txt');
Route::post('/webhooks/didit/identity', DiditWebhookController::class)
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('webhooks.didit.identity');
Route::get('/{seoSlug}', [SeoPagesController::class, 'show'])
    ->whereIn('seoSlug', array_keys(config('seo_pages.pages', [])))
    ->name('seo.page');

Route::get('/caregivers/search', BrowseCaregivers::class)->name('caregivers.search');
Route::get('/caregivers/{slug}', ShowCaregiver::class)->name('caregivers.show');

Route::get('dashboard', DashboardHome::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth'])->group(function () {
    Route::get('/messages', Inbox::class)->name('messages.index');
    Route::get('/messages/{conversation}', Inbox::class)->whereNumber('conversation')->name('messages.show');
    Route::get('/support', TicketsCenter::class)->name('support.index');
});

Route::middleware(['auth', 'caregiver.role'])->group(function () {
    Route::get('/caregiver/profile/edit', ProfileEditor::class)->name('caregiver.profile.edit');
    Route::get('/caregiver/verification', [CaregiverIdentityVerificationController::class, 'show'])
        ->name('caregiver.verification.show');
    Route::post('/caregiver/verification/session', [CaregiverIdentityVerificationController::class, 'store'])
        ->name('caregiver.verification.session');
    Route::get('/caregiver/verification/return', [CaregiverIdentityVerificationController::class, 'returned'])
        ->name('caregiver.verification.return');
    Route::get('/caregiver/invitations', InvitationsIndex::class)->name('caregiver.invitations.index');
    Route::get('/care-requests', BrowseCareRequests::class)->name('care-requests.index');
    Route::get('/care-requests/{careRequest}/apply', ApplyToCareRequest::class)
        ->whereNumber('careRequest')
        ->name('care-requests.apply');
});

Route::middleware(['auth', 'family.role'])->prefix('family')->name('family.')->group(function () {
    Route::get('/requests', RequestsIndex::class)->name('requests.index');
    Route::get('/requests/create', CreateCareRequestWizard::class)->name('requests.create');
    Route::get('/requests/create/ai', AiRequestCopilot::class)->name('requests.create_ai');
    Route::get('/requests/{careRequest}', ManageCareRequest::class)
        ->whereNumber('careRequest')
        ->name('requests.show');
});

require __DIR__.'/auth.php';
