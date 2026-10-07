<?php

namespace Tests\Feature\Family;

use App\Livewire\Admin\FamilyLeadsIndex;
use App\Livewire\Admin\FamilyOnboardingShow;
use App\Livewire\Admin\LeadsIndex;
use App\Models\FamilyAccount;
use App\Models\FamilyOnboarding;
use App\Models\Lead;
use App\Models\PageViewEvent;
use App\Models\User;
use App\Services\Family\FamilyOnboardingService;
use App\Services\FamilyAccounts\FamilyAccountProvisioner;
use App\Services\FamilyAcquisition\FamilyCrmSyncService;
use App\Services\FamilyAcquisition\FamilySignupAttribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class FamilyCrmSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
        Notification::fake();
        config(['family_onboarding.timezone' => 'America/New_York']);
    }

    public function test_direct_registration_immediately_creates_a_new_crm_lead(): void
    {
        Volt::test('pages.auth.register')->set('name', 'Jane Taylor')->set('email', 'jane@example.com')
            ->set('phone', '(984) 400-4008')->set('password', 'password')->set('password_confirmation', 'password')
            ->set('accept_terms', true)->call('register')->assertHasNoErrors()->assertRedirect('/family/onboarding');

        $lead = Lead::query()->sole();
        $onboarding = FamilyOnboarding::query()->sole();
        $this->assertSame('new', $lead->status);
        $this->assertSame('website_signup', $lead->source);
        $this->assertSame('Jane Taylor', $lead->name);
        $this->assertSame('(984) 400-4008', $lead->phone);
        $this->assertSame($onboarding->family_account_id, $lead->family_account_id);
        $this->assertNull($lead->welcomeEmail);
        $this->assertNull($lead->converted_at);
        $this->assertSame(1, $lead->activities()->where('summary', 'Family account created')->count());
    }

    public function test_completion_updates_contact_details_and_qualifies_once_with_a_readable_snapshot(): void
    {
        [$user, $onboarding] = $this->enroll();
        $service = app(FamilyOnboardingService::class);
        $record = $this->ready($user);
        $lead = Lead::query()->sole();
        $this->assertSame('new', $lead->status);
        $this->assertFalse($lead->activities()->where('body', 'like', '%Enjoys gardening%')->exists());

        $service->complete($user, $record->revision);
        $service->complete($user, $record->revision);
        app(FamilyCrmSyncService::class)->sync($onboarding);
        $lead->refresh();
        $this->assertSame('qualified', $lead->status);
        $this->assertSame('Raleigh, NC', $lead->location);
        $this->assertSame('27601', $lead->zip);
        $this->assertSame('+19845550123', $lead->phone);
        $note = $lead->activities()->where('summary', 'Family onboarding completed')->sole();
        foreach (['Recipient name: Susan Taylor', 'Street address: 123 Oak Street', 'Enjoys gardening.', 'A gentle pace.', 'Requested — 1 hour, free', 'Awaiting confirmation by text'] as $detail) {
            $this->assertStringContainsString($detail, $note->body);
        }
        $this->assertSame(1, $lead->activities()->where('type', 'stage_change')->count());
        $this->assertNull($lead->converted_at);

        // A later manual stage or phone edit must survive reconciliation.
        $lead->update(['status' => 'new', 'phone' => '+19845550999']);
        app(FamilyCrmSyncService::class)->sync($onboarding);
        $this->assertSame('new', $lead->fresh()->status);
        $this->assertSame('+19845550999', $lead->fresh()->phone);
    }

    public function test_existing_family_lead_is_linked_by_email_without_losing_campaign_or_staff_work(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lead = Lead::withoutEvents(fn () => Lead::create([
            'lead_type' => 'family', 'name' => 'Jane', 'email' => ' JANE@example.com ', 'source' => 'meta_lead_ads',
            'external_source' => 'meta', 'external_id' => '12345', 'source_detail' => 'Spring campaign',
            'status' => 'callback_scheduled', 'assigned_admin_id' => $admin->id, 'next_follow_up_at' => now()->addDay(),
            'data' => ['meta' => ['campaign_name' => 'Spring campaign']],
        ]));
        $lead->activities()->create(['type' => 'note', 'summary' => 'Call tomorrow', 'body' => 'Speak with her brother.', 'occurred_at' => now()]);
        [$user] = $this->enroll('jane@example.com');
        app(FamilyOnboardingService::class)->complete($user, $this->ready($user)->revision);
        $lead->refresh();

        $this->assertSame(1, Lead::count());
        $this->assertSame('callback_scheduled', $lead->status);
        $this->assertSame($admin->id, $lead->assigned_admin_id);
        $this->assertNotNull($lead->next_follow_up_at);
        $this->assertSame('meta_lead_ads', $lead->source);
        $this->assertSame('12345', $lead->external_id);
        $this->assertSame('Spring campaign', $lead->data['meta']['campaign_name']);
        $this->assertTrue($lead->activities()->where('summary', 'Call tomorrow')->exists());
    }

    public function test_completion_preserves_terminal_and_staff_managed_stages_and_do_not_contact(): void
    {
        foreach (['converted', 'closed', 'lost', 'not_fit', 'unreachable', 'nurture', 'intake_scheduled', 'assessment_scheduled', 'callback_scheduled'] as $stage) {
            [$user] = $this->enroll();
            $lead = Lead::where('email', $user->email)->firstOrFail();
            $lead->update(['status' => $stage]);
            app(FamilyOnboardingService::class)->complete($user, $this->ready($user, false)->revision);
            $this->assertSame($stage, $lead->fresh()->status);
        }
        [$user] = $this->enroll();
        $lead = Lead::where('email', $user->email)->firstOrFail();
        $lead->update(['do_not_contact_at' => now()]);
        app(FamilyOnboardingService::class)->complete($user, $this->ready($user, false)->revision);
        $this->assertSame('new', $lead->fresh()->status);
        $this->assertNotNull($lead->fresh()->do_not_contact_at);
    }

    public function test_matching_never_reuses_a_referral_or_another_households_lead(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $account = app(FamilyAccountProvisioner::class)->provisionOwner($owner);
        $otherLead = Lead::withoutEvents(fn () => Lead::create(['lead_type' => 'family', 'email' => 'jane@example.com',
            'family_account_id' => $account->id, 'status' => 'converted']));
        $referral = Lead::withoutEvents(fn () => Lead::create(['lead_type' => 'referral', 'email' => 'jane@example.com', 'status' => 'qualified']));
        [, $onboarding] = $this->enroll('jane@example.com');

        $lead = Lead::where('family_account_id', $onboarding->family_account_id)->sole();
        $this->assertNotSame($otherLead->id, $lead->id);
        $this->assertNotSame($referral->id, $lead->id);
        $this->assertSame('new', $lead->status);
        $this->assertSame(3, Lead::count());
    }

    public function test_internal_navigation_and_expired_tracking_do_not_claim_an_acquisition_channel(): void
    {
        session()->put(FamilySignupAttribution::SESSION_KEY, ['utm_source' => 'old_campaign', 'captured_at' => now()->subDays(31)->timestamp]);
        $this->withHeader('Referer', url('/'))->get('/register')->assertOk();
        $this->enroll();
        $lead = Lead::query()->sole();
        $this->assertSame('Website signup', $lead->source_detail);
        $this->assertNull($lead->referrer_url);
    }

    public function test_confirmed_welcome_visit_moves_to_assessment_and_adds_a_note_once(): void
    {
        [$user, $onboarding] = $this->enroll();
        app(FamilyOnboardingService::class)->complete($user, $this->ready($user)->revision);
        $this->assertSame('qualified', Lead::query()->sole()->status);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Livewire::test(FamilyOnboardingShow::class, ['onboarding' => $onboarding->fresh()])
            ->set('visitStatus', 'confirmed')->set('confirmedStart', now()->addDays(3)->format('Y-m-d').'T10:00')
            ->set('textConfirmed', true)->set('staffNote', 'Confirmed with Jane by text.')
            ->call('saveVisit')->assertHasNoErrors();
        app(FamilyCrmSyncService::class)->sync($onboarding);
        $lead = Lead::query()->sole();
        $this->assertSame('assessment_scheduled', $lead->status);
        $this->assertStringContainsString('Confirmed with Jane by text.', $lead->activities()->where('summary', 'Welcome visit updated')->sole()->body);
        $this->assertNull($lead->converted_at);
    }

    public function test_backfill_is_idempotent_dry_run_safe_and_does_not_send_email(): void
    {
        $user = User::factory()->create(['role' => 'family']);
        $account = app(FamilyAccountProvisioner::class)->provisionOwner($user);
        FamilyOnboarding::create(['family_account_id' => $account->id, 'initiated_by_user_id' => $user->id,
            'source' => 'registration', 'draft' => [], 'status' => 'completed', 'completed_at' => now()->subDay(),
            'submitted_snapshot' => $this->answers(false) + ['name' => $user->name, 'email' => $user->email]]);
        $this->artisan('family-crm:sync-signups --dry-run')->assertSuccessful();
        $this->assertSame(0, Lead::count());
        $this->artisan('family-crm:sync-signups')->assertSuccessful();
        $this->artisan('family-crm:sync-signups')->assertSuccessful();
        $lead = Lead::query()->sole();
        $this->assertSame('qualified', $lead->status);
        $this->assertSame(2, $lead->activities()->where('type', 'note')->count());
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_non_family_users_and_transferred_or_closed_households_are_not_added(): void
    {
        $this->assertNull(app(FamilyOnboardingService::class)->enrollRegistration(User::factory()->create(['role' => 'caregiver'])));
        $original = User::factory()->create(['role' => 'family']);
        $replacement = User::factory()->create(['role' => 'family']);
        $account = app(FamilyAccountProvisioner::class)->provisionOwner($original);
        $onboarding = FamilyOnboarding::create(['family_account_id' => $account->id, 'initiated_by_user_id' => $original->id, 'source' => 'registration', 'draft' => []]);
        $account->update(['owner_user_id' => $replacement->id]);
        $this->assertNull(app(FamilyCrmSyncService::class)->sync($onboarding));
        $account->update(['owner_user_id' => $original->id, 'status' => FamilyAccount::STATUS_CLOSED]);
        $this->assertNull(app(FamilyCrmSyncService::class)->sync($onboarding));
        $this->artisan('family-crm:sync-signups')->assertSuccessful();
        $this->assertSame(0, Lead::count());
    }

    public function test_registration_rollback_leaves_no_partial_lead_or_notes(): void
    {
        DB::beginTransaction();
        $this->enroll();
        $this->assertSame(1, Lead::count());
        DB::rollBack();
        $this->assertDatabaseCount('leads', 0);
        $this->assertDatabaseCount('lead_activities', 0);
        $this->assertDatabaseCount('family_onboardings', 0);
    }

    public function test_signup_retains_real_campaign_attribution_across_registration_requests(): void
    {
        $this->get('/register?utm_source=google&utm_medium=cpc&utm_campaign=triangle')->assertOk();
        [$user] = $this->enroll();
        $lead = Lead::where('email', $user->email)->sole();
        $this->assertSame('Website signup · google / cpc', $lead->source_detail);
        $this->assertSame('triangle', data_get($lead->data, 'website_signup.attribution.utm_campaign'));
    }

    public function test_homepage_referrer_is_retained_without_guessing_google_for_untracked_signups(): void
    {
        $anonId = (string) Str::uuid();
        PageViewEvent::create(['event_name' => 'family_landing_view', 'anon_id' => $anonId,
            'url' => 'https://carelolo.com/', 'referrer' => 'https://www.google.com/']);
        $this->withCookie(config('analytics.anon_cookie_name', 'hc_anon_id'), $anonId)->get('/register')->assertOk();
        [$user] = $this->enroll();
        $this->assertSame('Website signup · www.google.com', Lead::where('email', $user->email)->sole()->source_detail);
        session()->forget(FamilySignupAttribution::SESSION_KEY);
        [$other] = $this->enroll();
        $this->assertSame('Website signup', Lead::where('email', $other->email)->sole()->source_detail);
    }

    public function test_both_crm_screens_show_signup_progress_and_family_filters_include_direct_signups(): void
    {
        [$user, $onboarding] = $this->enroll();
        $lead = Lead::query()->sole();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Livewire::test(FamilyLeadsIndex::class)->set('source', 'website_signup')->set('welcome', 'account')
            ->assertSee($user->name)->assertSee('Onboarding in progress');
        Livewire::test(LeadsIndex::class)->set('selectedLeadId', $lead->id)
            ->assertSee('Onboarding in progress')->assertSee('Family account created');
        app(FamilyOnboardingService::class)->complete($user, $this->ready($user)->revision);
        Livewire::test(FamilyLeadsIndex::class)->set('welcome', 'onboarding')->set('selectedLeadId', $lead->id)
            ->assertSee($user->name)->assertSee('Onboarding completed')->assertSee('Recipient name: Susan Taylor')
            ->assertSee('awaiting confirmation')->assertSee(route('admin.family-onboarding.show', $onboarding));
    }

    private function enroll(?string $email = null): array
    {
        $user = User::factory()->create(['role' => 'family', 'email' => $email ?? Str::uuid().'@example.com', 'phone' => '+19845550000']);
        $onboarding = DB::transaction(fn () => app(FamilyOnboardingService::class)->enrollRegistration($user));

        return [$user, $onboarding];
    }

    private function ready(User $user, bool $visit = true): FamilyOnboarding
    {
        $service = app(FamilyOnboardingService::class);
        $record = $service->forOwner($user);
        for ($step = 1; $step <= 4; $step++) {
            $record = $service->saveStep($user, $this->answers($visit), $step, $record->revision);
        }

        return $record;
    }

    private function answers(bool $visit): array
    {
        return ['care_for' => 'family', 'recipient_name' => 'Susan Taylor', 'relationship' => 'Parent',
            'address_line1' => '123 Oak Street', 'address_line2' => 'Unit 2', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
            'care_notes' => 'Enjoys gardening.', 'care_preferences' => 'A gentle pace.',
            'welcome_visit' => $visit ? 'yes' : 'no', 'visit_date' => $visit ? now()->addDays(3)->format('Y-m-d') : '',
            'visit_time' => $visit ? 'morning' : '', 'phone' => '(984) 555-0123'];
    }
}
