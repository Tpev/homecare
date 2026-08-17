<?php

use App\Http\Controllers\Admin\BlogPostPreviewController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BlogCoverController;
use App\Http\Controllers\BlogEventController;
use App\Http\Controllers\BlogFeedController;
use App\Http\Controllers\CaregiverCertificationDocumentController;
use App\Http\Controllers\CaregiverIdentityVerificationController;
use App\Http\Controllers\CaregiverStripeConnectController;
use App\Http\Controllers\CareRequestInvitationResponseController;
use App\Http\Controllers\DiditWebhookController;
use App\Http\Controllers\FamilyAccessEndedController;
use App\Http\Controllers\FamilyAccountInvitationController;
use App\Http\Controllers\FamilyBillingController;
use App\Http\Controllers\GoogleSheetsLeadWebhookController;
use App\Http\Controllers\IndexNowKeyController;
use App\Http\Controllers\LegalPageController;
use App\Http\Controllers\LlmsController;
use App\Http\Controllers\MarketingPagesController;
use App\Http\Controllers\NotificationEmailTrackingController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SeoPagesController;
use App\Http\Controllers\SessionLogoutController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TwilioSmsStatusWebhookController;
use App\Http\Controllers\TwilioSmsWebhookController;
use App\Http\Controllers\TwilioVoiceWebhookController;
use App\Livewire\Admin\AiSupport\ActivityIndex as AiSupportActivityIndex;
use App\Livewire\Admin\AiSupport\KnowledgeEditor as AiSupportKnowledgeEditor;
use App\Livewire\Admin\AiSupport\KnowledgeIndex as AiSupportKnowledgeIndex;
use App\Livewire\Admin\AiSupport\Overview as AiSupportOverview;
use App\Livewire\Admin\AiSupport\PilotsIndex as AiSupportPilotsIndex;
use App\Livewire\Admin\AiSupport\Settings as AiSupportSettings;
use App\Livewire\Admin\CaregiverCoverageMap;
use App\Livewire\Admin\CaregiverReviewsQueue;
use App\Livewire\Admin\CarePlanShow;
use App\Livewire\Admin\CarePlansIndex;
use App\Livewire\Admin\CareRequestShow as AdminCareRequestShow;
use App\Livewire\Admin\CareRequestsIndex;
use App\Livewire\Admin\Content\ContentSettings;
use App\Livewire\Admin\Content\MediaLibrary as ContentMediaLibrary;
use App\Livewire\Admin\Content\PostEditor as ContentPostEditor;
use App\Livewire\Admin\Content\PostsIndex as ContentPostsIndex;
use App\Livewire\Admin\ContinuousCoverageIndex as AdminContinuousCoverageIndex;
use App\Livewire\Admin\FunnelAnalytics;
use App\Livewire\Admin\LeadsIndex;
use App\Livewire\Admin\NotificationsCenter as AdminNotificationsCenter;
use App\Livewire\Admin\PaymentsQueue;
use App\Livewire\Admin\ProviderOutreachAi;
use App\Livewire\Admin\SdrOutreachCenter;
use App\Livewire\Admin\SmsInbox;
use App\Livewire\Admin\SupportTicketShow;
use App\Livewire\Admin\SupportTicketsQueue;
use App\Livewire\Admin\UsageAnalytics;
use App\Livewire\Admin\UserShow;
use App\Livewire\Admin\UsersIndex;
use App\Livewire\Admin\VoiceAiTest;
use App\Livewire\Auth\FamilyInvitationRegister;
use App\Livewire\Caregiver\ApplyToCareRequest;
use App\Livewire\Caregiver\BrowseCaregivers;
use App\Livewire\Caregiver\BrowseCareRequests;
use App\Livewire\Caregiver\ContinuousCoverageIndex as CaregiverContinuousCoverageIndex;
use App\Livewire\Caregiver\EarningsDashboard;
use App\Livewire\Caregiver\InsuranceSetup;
use App\Livewire\Caregiver\IntroVideoSetup;
use App\Livewire\Caregiver\InvitationsIndex;
use App\Livewire\Caregiver\NotificationsCenter;
use App\Livewire\Caregiver\OnboardingHub;
use App\Livewire\Caregiver\OnboardingWizard;
use App\Livewire\Caregiver\ProfileEditor;
use App\Livewire\Caregiver\RegularClients;
use App\Livewire\Caregiver\ShiftsIndex;
use App\Livewire\Caregiver\ShowCaregiver;
use App\Livewire\Caregiver\TaskComfortSetup;
use App\Livewire\Caregiver\WorkInbox;
use App\Livewire\Dashboard\Home as DashboardHome;
use App\Livewire\Family\AcceptFamilyInvitation;
use App\Livewire\Family\BookAgain;
use App\Livewire\Family\CareHistory;
use App\Livewire\Family\CareProfileEditor;
use App\Livewire\Family\CareProfiles;
use App\Livewire\Family\ContinuousCoverageCreate;
use App\Livewire\Family\ContinuousCoverageIndex as FamilyContinuousCoverageIndex;
use App\Livewire\Family\ContinuousCoverageShow;
use App\Livewire\Family\CreateCareRequestWizard;
use App\Livewire\Family\FamilyAccess;
use App\Livewire\Family\ManageCareRequest;
use App\Livewire\Family\NotificationsCenter as FamilyNotificationsCenter;
use App\Livewire\Family\RegularCareComposer;
use App\Livewire\Family\RegularCareIndex;
use App\Livewire\Family\RegularCareShow;
use App\Livewire\Family\RequestsIndex;
use App\Livewire\Messaging\Inbox;
use App\Livewire\Sdr\CallingConsole;
use App\Livewire\Support\TicketConversation;
use App\Livewire\Support\TicketsCenter;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'crm.access'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/crm', LeadsIndex::class)->name('crm.index');
        Route::get('/leads', LeadsIndex::class)->name('leads.index');
    });

Route::middleware(['web', 'auth', 'sdr.access'])
    ->prefix('sdr')
    ->name('sdr.')
    ->group(function () {
        Route::get('/calling', CallingConsole::class)->name('calling');
    });

Route::middleware(['web', 'auth', 'content.access'])
    ->prefix('admin/content')
    ->name('admin.content.')
    ->group(function () {
        Route::get('/posts', ContentPostsIndex::class)->name('posts.index');
        Route::get('/posts/{blogPost:id}/edit', ContentPostEditor::class)->whereNumber('blogPost')->name('posts.edit');
        Route::get('/posts/{blogPost:id}/preview', BlogPostPreviewController::class)
            ->middleware('signed')
            ->whereNumber('blogPost')
            ->name('posts.preview');
        Route::get('/media', ContentMediaLibrary::class)->name('media.index');
        Route::get('/settings', ContentSettings::class)->name('settings');
    });

Route::middleware(['web', 'auth', 'admin.email'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/ai-support', AiSupportOverview::class)->name('ai-support.index');
        Route::get('/ai-support/pilots', AiSupportPilotsIndex::class)->name('ai-support.pilots.index');
        Route::get('/ai-support/knowledge', AiSupportKnowledgeIndex::class)->name('ai-support.knowledge.index');
        Route::get('/ai-support/knowledge/create', AiSupportKnowledgeEditor::class)->name('ai-support.knowledge.create');
        Route::get('/ai-support/knowledge/{entry}/edit', AiSupportKnowledgeEditor::class)->name('ai-support.knowledge.edit');
        Route::get('/ai-support/activity', AiSupportActivityIndex::class)->name('ai-support.activity.index');
        Route::redirect('/ai-support/readiness', '/admin/ai-support/settings')->name('ai-support.readiness');
        Route::get('/ai-support/settings', AiSupportSettings::class)->name('ai-support.settings');
        Route::get('/caregivers/reviews', CaregiverReviewsQueue::class)->name('caregivers.reviews');
        Route::get('/caregivers/moderation-logs', \App\Livewire\Admin\CaregiverModerationLogs::class)
            ->name('caregivers.moderation_logs');
        Route::get('/support/tickets', SupportTicketsQueue::class)->name('support.tickets');
        Route::get('/support/tickets/{ticket}', SupportTicketShow::class)
            ->whereNumber('ticket')
            ->name('support.tickets.show');
        Route::get('/notifications', AdminNotificationsCenter::class)->name('notifications.index');
        Route::get('/sms', SmsInbox::class)->name('sms.index');
        Route::get('/voice-ai', VoiceAiTest::class)->name('voice-ai.index');
        Route::get('/provider-outreach-ai', ProviderOutreachAi::class)->name('provider-outreach-ai.index');
        Route::get('/sdr-outreach', SdrOutreachCenter::class)->name('sdr-outreach.index');
        Route::get('/payments/ops', PaymentsQueue::class)->name('payments.ops');
        Route::get('/analytics/usage', UsageAnalytics::class)->name('analytics.usage');
        Route::get('/analytics/funnel', FunnelAnalytics::class)->name('analytics.funnel');
        Route::get('/analytics/caregiver-map', CaregiverCoverageMap::class)->name('analytics.caregiver-map');
        Route::get('/users', UsersIndex::class)->name('users.index');
        Route::get('/users/{user}', UserShow::class)->name('users.show');
        Route::get('/requests', CareRequestsIndex::class)->name('requests.index');
        Route::get('/requests/{careRequest}', AdminCareRequestShow::class)->name('requests.show');
        Route::get('/care-plans', CarePlansIndex::class)->name('care-plans.index');
        Route::get('/care-plans/{carePlan}', CarePlanShow::class)->name('care-plans.show');
        Route::get('/continuous-coverage', AdminContinuousCoverageIndex::class)
            ->middleware('continuous.coverage')
            ->name('continuous-coverage.index');
    });

Route::get('/', [MarketingPagesController::class, 'landing'])->name('landing');
Route::get('/families', [MarketingPagesController::class, 'family'])->name('landing.family');
Route::get('/get-care', [MarketingPagesController::class, 'getCare'])->name('landing.get-care');
Route::get('/families/{variant}', [MarketingPagesController::class, 'familyVariant'])
    ->whereIn('variant', ['a', 'b', 'c', 'd', 'e'])
    ->name('landing.family.variant');
Route::get('/caregivers', [MarketingPagesController::class, 'caregiver'])->name('landing.caregiver');
Route::view('/flyer/family', 'marketing.flyer-family')->name('marketing.flyer.family');
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/feed.xml', BlogFeedController::class)->name('blog.feed');
Route::get('/blog/category/{category:slug}', [BlogController::class, 'category'])->name('blog.category');
Route::get('/blog/topic/{tag:slug}', [BlogController::class, 'tag'])->name('blog.tag');
Route::get('/blog/authors/{author:slug}', [BlogController::class, 'author'])->name('blog.author');
Route::get('/blog/{blogSlug}/cover', BlogCoverController::class)->name('blog.cover');
Route::get('/blog/{blogSlug}', [BlogController::class, 'show'])->name('blog.show');
Route::post('/blog/{blogPost:id}/events', BlogEventController::class)->middleware('throttle:30,1')->name('blog.events');
Route::get('/notifications/email/open/{delivery}/{token}', [NotificationEmailTrackingController::class, 'open'])
    ->whereNumber('delivery')
    ->name('notifications.email.open');
Route::get('/notifications/email/click/{delivery}/{token}', [NotificationEmailTrackingController::class, 'click'])
    ->whereNumber('delivery')
    ->name('notifications.email.click');
Route::get('/legal', [LegalPageController::class, 'index'])->name('legal.index');
Route::view('/legal/sms-opt-in-evidence', 'legal.sms-opt-in-evidence')->name('legal.sms-opt-in-evidence');
Route::get('/legal/{slug}', [LegalPageController::class, 'show'])
    ->whereIn('slug', array_keys(config('legal_pages.pages', [])))
    ->name('legal.show');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.xml');
Route::get('/robots.txt', [RobotsController::class, 'index'])->name('robots.txt');
Route::get('/llms.txt', [LlmsController::class, 'index'])->name('llms.txt');
Route::get('/indexnow-key.txt', IndexNowKeyController::class)->name('indexnow.key');
Route::post('/webhooks/didit/identity', DiditWebhookController::class)
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('webhooks.didit.identity');
Route::post('/webhooks/stripe', StripeWebhookController::class)
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('webhooks.stripe');
Route::post('/webhooks/twilio/sms', TwilioSmsWebhookController::class)
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('webhooks.twilio.sms');
Route::post('/webhooks/twilio/sms/status', TwilioSmsStatusWebhookController::class)
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('webhooks.twilio.sms.status');
Route::post('/webhooks/twilio/voice/{voiceAiCall}/answer', [TwilioVoiceWebhookController::class, 'answer'])
    ->whereNumber('voiceAiCall')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('webhooks.twilio.voice.answer');
Route::post('/webhooks/twilio/voice/{voiceAiCall}/gather', [TwilioVoiceWebhookController::class, 'gather'])
    ->whereNumber('voiceAiCall')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('webhooks.twilio.voice.gather');
Route::post('/webhooks/twilio/voice/{voiceAiCall}/status', [TwilioVoiceWebhookController::class, 'status'])
    ->whereNumber('voiceAiCall')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('webhooks.twilio.voice.status');
Route::post('/webhooks/google-sheets/leads', GoogleSheetsLeadWebhookController::class)
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('webhooks.google-sheets.leads');
Route::get('/{seoSlug}', [SeoPagesController::class, 'show'])
    ->whereIn('seoSlug', array_keys(config('seo_pages.pages', [])))
    ->name('seo.page');

Route::get('/caregivers/search', BrowseCaregivers::class)->name('caregivers.search');
Route::get('/caregivers/{slug}', ShowCaregiver::class)->name('caregivers.show');

Route::get('/family/invitations/{token}', FamilyAccountInvitationController::class)
    ->where('token', '[A-Fa-f0-9]{64}')
    ->name('family.invitations.show');
Route::get('/family/invitations/{token}/register', FamilyInvitationRegister::class)
    ->middleware('guest')
    ->where('token', '[A-Fa-f0-9]{64}')
    ->name('family.invitations.register');
Route::get('/family/invitations/{token}/review', AcceptFamilyInvitation::class)
    ->middleware('auth')
    ->where('token', '[A-Fa-f0-9]{64}')
    ->name('family.invitations.review');
Route::get('/family/access-ended', FamilyAccessEndedController::class)
    ->middleware('auth')
    ->name('family.access.ended');
Route::post('/logout', SessionLogoutController::class)
    ->middleware('auth')
    ->name('logout');

Route::get('dashboard', DashboardHome::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth'])->group(function () {
    Route::get('/caregiver/certifications/{certification}/document', CaregiverCertificationDocumentController::class)
        ->whereNumber('certification')
        ->name('caregiver.certifications.document');
    Route::get('/messages', Inbox::class)->name('messages.index');
    Route::get('/messages/{conversation}', Inbox::class)->whereNumber('conversation')->name('messages.show');
    Route::get('/support', TicketsCenter::class)->name('support.index');
    Route::get('/support/tickets/{ticket}', TicketConversation::class)
        ->whereNumber('ticket')
        ->name('support.tickets.show');
});

Route::middleware(['auth', 'caregiver.role'])->group(function () {
    Route::get('/caregiver/setup', OnboardingHub::class)->name('caregiver.setup.index');
    Route::get('/caregiver/onboarding', OnboardingWizard::class)->name('caregiver.onboarding');
    Route::get('/caregiver/profile/edit', ProfileEditor::class)->name('caregiver.profile.edit');
    Route::get('/caregiver/profile/tasks', TaskComfortSetup::class)->name('caregiver.tasks.edit');
    Route::get('/caregiver/profile/insurance', InsuranceSetup::class)->name('caregiver.insurance.edit');
    Route::get('/caregiver/profile/intro-video', IntroVideoSetup::class)->name('caregiver.video.edit');
    Route::get('/caregiver/verification', [CaregiverIdentityVerificationController::class, 'show'])
        ->name('caregiver.verification.show');
    Route::post('/caregiver/verification/session', [CaregiverIdentityVerificationController::class, 'store'])
        ->name('caregiver.verification.session');
    Route::get('/caregiver/verification/return', [CaregiverIdentityVerificationController::class, 'returned'])
        ->name('caregiver.verification.return');
    Route::get('/caregiver/payouts/connect', [CaregiverStripeConnectController::class, 'show'])
        ->name('caregiver.payouts.connect.show');
    Route::post('/caregiver/payouts/connect/start', [CaregiverStripeConnectController::class, 'start'])
        ->name('caregiver.payouts.connect.start');
    Route::get('/caregiver/payouts/connect/return', [CaregiverStripeConnectController::class, 'returned'])
        ->name('caregiver.payouts.connect.return');
    Route::get('/caregiver/invitations', InvitationsIndex::class)->name('caregiver.invitations.index');
    Route::post('/caregiver/invitations/{invitation}/accept', [CareRequestInvitationResponseController::class, 'accept'])
        ->whereNumber('invitation')
        ->name('caregiver.invitations.accept');
    Route::post('/caregiver/invitations/{invitation}/decline', [CareRequestInvitationResponseController::class, 'decline'])
        ->whereNumber('invitation')
        ->name('caregiver.invitations.decline');
    Route::get('/caregiver/work-inbox', WorkInbox::class)->name('caregiver.work-inbox.index');
    Route::get('/caregiver/regular-clients', RegularClients::class)->name('caregiver.regular-clients.index');
    Route::get('/caregiver/notifications', NotificationsCenter::class)->name('caregiver.notifications.index');
    Route::get('/caregiver/shifts', ShiftsIndex::class)->name('caregiver.shifts.index');
    Route::get('/caregiver/earnings', EarningsDashboard::class)->name('caregiver.earnings.index');
    Route::get('/care-requests', BrowseCareRequests::class)->name('care-requests.index');
    Route::get('/care-requests/{careRequest}/apply', ApplyToCareRequest::class)
        ->whereNumber('careRequest')
        ->name('care-requests.apply');
    Route::get('/caregiver/continuous-coverage', CaregiverContinuousCoverageIndex::class)
        ->middleware('continuous.coverage')
        ->name('caregiver.continuous-coverage.index');
});

Route::middleware(['auth', 'family.role'])->prefix('family')->name('family.')->group(function () {
    Route::get('/access', FamilyAccess::class)->name('access');
    Route::get('/care-profiles', CareProfiles::class)->name('care-profiles.index');
    Route::get('/care-profiles/create', CareProfileEditor::class)->name('care-profiles.create');
    Route::get('/care-profiles/{careRecipientProfile}/edit', CareProfileEditor::class)
        ->whereNumber('careRecipientProfile')
        ->name('care-profiles.edit');
    Route::middleware('continuous.coverage')->prefix('continuous-coverage')->name('continuous-coverage.')->group(function () {
        Route::get('/', FamilyContinuousCoverageIndex::class)->name('index');
        Route::get('/create', ContinuousCoverageCreate::class)->name('create');
        Route::get('/{coveragePlan}', ContinuousCoverageShow::class)->whereNumber('coveragePlan')->name('show');
    });
    Route::get('/care', RegularCareIndex::class)->name('care.index');
    Route::get('/care/history', CareHistory::class)->name('care.history');
    Route::get('/care/{carePlan}', RegularCareShow::class)
        ->whereNumber('carePlan')
        ->name('care.show');
    Route::get('/requests', RequestsIndex::class)->name('requests.index');
    Route::get('/requests/create', CreateCareRequestWizard::class)->name('requests.create');
    Route::get('/notifications', FamilyNotificationsCenter::class)->name('notifications.index');
    Route::get('/billing', [FamilyBillingController::class, 'show'])->name('billing.show');
    Route::post('/billing/checkout', [FamilyBillingController::class, 'createCheckout'])->name('billing.checkout');
    Route::get('/requests/{careRequest}/regular-care', RegularCareComposer::class)
        ->whereNumber('careRequest')
        ->name('care.compose');
    Route::get('/requests/{careRequest}/book-again', BookAgain::class)
        ->whereNumber('careRequest')
        ->name('requests.book_again');
    Route::get('/requests/{careRequest}', ManageCareRequest::class)
        ->whereNumber('careRequest')
        ->name('requests.show');
});

require __DIR__.'/auth.php';
