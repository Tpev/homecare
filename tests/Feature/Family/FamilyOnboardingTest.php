<?php

namespace Tests\Feature\Family;

use App\Jobs\SendFamilyOnboardingEmail;
use App\Livewire\Admin\FamilyOnboardingShow;
use App\Livewire\Auth\FamilyInvitationRegister;
use App\Livewire\Family\CreateCareRequestWizard;
use App\Livewire\Family\OnboardingWizard;
use App\Mail\Ops\FamilyOnboardingCompletedMail;
use App\Models\CareRecipientProfile;
use App\Models\CareRequest;
use App\Models\CareTask;
use App\Models\FamilyAccountMember;
use App\Models\FamilyOnboarding;
use App\Models\FamilyWelcomeVisit;
use App\Models\User;
use App\Services\CareRecipientProfiles\CareRecipientProfileService;
use App\Services\Family\FamilyOnboardingDeliveryService;
use App\Services\Family\FamilyOnboardingService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\FamilyAccounts\FamilyAccountInvitationService;
use App\Services\FamilyAccounts\FamilyAccountOwnershipService;
use App\Support\FamilyQuickRequestDraft;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Mockery;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class FamilyOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'family_onboarding.enrollment_enabled' => true,
            'family_onboarding.enforcement_enabled' => true,
            'family_onboarding.timezone' => 'America/New_York',
            'marketplace.ops_alert_recipients' => ['ops@example.com', 'OPS@example.com'],
        ]);
        Mail::fake();
        Queue::fake();
        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-09-16 10:00:00', 'America/New_York'));
    }

    private function enrolled(): User
    {
        $user = User::factory()->create(['role' => 'family', 'phone' => '(984) 400-4008']);
        DB::transaction(fn () => app(FamilyOnboardingService::class)->enrollRegistration($user));
        $this->actingAs($user);

        return $user;
    }

    private function answers(array $overrides = []): array
    {
        return array_replace([
            'care_for' => 'family', 'recipient_name' => 'Susan Taylor', 'relationship' => 'Parent',
            'address_line1' => '123 Oak Street', 'address_line2' => 'Unit 2', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
            'care_notes' => 'Enjoys the garden.', 'care_preferences' => 'A gentle pace.',
            'welcome_visit' => 'yes', 'visit_date' => '2026-09-18', 'visit_time' => 'morning', 'phone' => '(984) 400-4008',
        ], $overrides);
    }

    private function ready(User $user, array $overrides = []): FamilyOnboarding
    {
        $service = app(FamilyOnboardingService::class);
        $record = $service->forOwner($user);
        for ($step = 1; $step <= 4; $step++) {
            $record = $service->saveStep($user, $this->answers($overrides), $step, $record->revision);
        }

        return $record;
    }

    private function completed(User $user, array $overrides = []): FamilyOnboarding
    {
        return app(FamilyOnboardingService::class)->complete($user, $this->ready($user, $overrides)->revision);
    }

    public function test_regular_registration_enrolls_once_and_redirects(): void
    {
        Volt::test('pages.auth.register')->set('name', 'Jane Taylor')->set('email', 'jane@example.com')
            ->set('phone', '(984) 400-4008')->set('password', 'password')->set('password_confirmation', 'password')
            ->set('accept_terms', true)->call('register')->assertHasNoErrors()
            ->assertRedirect(route('family.onboarding', absolute: false));
        $this->assertDatabaseCount('family_onboardings', 1);
        $this->assertSame(Auth::id(), FamilyOnboarding::query()->sole()->initiated_by_user_id);
        $this->assertSame('registration', FamilyOnboarding::query()->sole()->source);
        $this->assertDatabaseCount('family_onboarding_deliveries', 0);
    }

    public function test_existing_family_with_no_profiles_is_not_enrolled_even_when_account_is_lazily_created(): void
    {
        $user = User::factory()->create(['role' => 'family']);
        $this->actingAs($user)->get(route('family.requests.index'))->assertOk();
        $this->get(route('family.requests.create'))->assertOk();
        $this->get(route('family.onboarding'))->assertRedirect(route('family.requests.index'));
        $this->assertDatabaseCount('family_onboardings', 0);
    }

    public function test_invitation_registration_and_acceptance_never_enroll(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $invitation = app(FamilyAccountInvitationService::class)->send($owner, 'invited@example.com');
        $token = $invitation['token'];
        Livewire::test(FamilyInvitationRegister::class, ['token' => $token])->set('name', 'Invited Family')
            ->set('password', 'password')->set('password_confirmation', 'password')->set('accept_terms', true)
            ->call('register')->assertHasNoErrors()->assertRedirect(route('family.invitations.review', ['token' => $token], absolute: false));
        $invitee = User::query()->where('email', 'invited@example.com')->firstOrFail();
        app(FamilyAccountInvitationService::class)->accept($invitee, $token);
        $this->actingAs($invitee)->get(route('family.requests.create'))->assertOk();
        $this->assertDatabaseCount('family_onboardings', 0);
    }

    public function test_pending_owner_is_guarded_but_support_and_profile_are_accessible(): void
    {
        $user = $this->enrolled();
        foreach (['family.requests.index', 'family.requests.create', 'dashboard'] as $route) {
            $this->get(route($route))->assertRedirect(route('family.onboarding'));
        }
        $this->get(route('family.onboarding'))->assertOk()->assertSee('Who is receiving care?');
        $this->get(route('profile'))->assertOk();
        $this->get(route('support.index'))->assertOk();
        Livewire::actingAs($user)->test(CreateCareRequestWizard::class)->assertRedirect(route('family.onboarding'));
    }

    public function test_login_resumes_and_verification_does_not_skip_onboarding(): void
    {
        $user = $this->enrolled();
        $record = app(FamilyOnboardingService::class)->forOwner($user);
        app(FamilyOnboardingService::class)->saveStep($user, $this->answers(), 1, $record->revision);
        Auth::logout();
        Volt::test('pages.auth.login')->set('form.email', $user->email)->set('form.password', 'password')
            ->call('login')->assertRedirect(route('family.onboarding', absolute: false));
        Livewire::test(OnboardingWizard::class)->assertSet('step', 2)->assertSet('form.recipient_name', 'Susan Taylor');
        $user->forceFill(['email_verified_at' => null])->save();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->withSession(['url.intended' => route('family.requests.create')])->get($url)
            ->assertRedirect(route('family.onboarding', absolute: false).'?verified=1');
    }

    public function test_invitation_login_takes_priority_over_onboarding_and_quick_draft(): void
    {
        $user = $this->enrolled();
        Auth::logout();
        $url = route('family.invitations.review', ['token' => str_repeat('a', 64)]);
        session(['url.intended' => $url]);
        FamilyQuickRequestDraft::put(['care_for' => 'self']);
        Volt::test('pages.auth.login')->set('form.email', $user->email)->set('form.password', 'password')
            ->call('login')->assertRedirect($url);
    }

    public function test_members_cannot_read_or_write_owners_onboarding(): void
    {
        $owner = $this->enrolled();
        $member = User::factory()->create(['role' => 'family']);
        FamilyAccountMember::query()->create(['family_account_id' => app(FamilyAccountContext::class)->account($owner)->id,
            'user_id' => $member->id, 'access_level' => 'member', 'status' => 'active', 'joined_at' => now()]);
        $this->actingAs($member)->get(route('family.onboarding'))->assertRedirect(route('family.requests.index'));
        $this->get(route('family.requests.create'))->assertOk();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(FamilyOnboardingService::class)->saveStep($member, $this->answers(), 1, 0);
    }

    public function test_saved_steps_survive_reload_and_back_preserves_partial_fields(): void
    {
        $this->enrolled();
        Livewire::test(OnboardingWizard::class)->set('form.care_for', 'family')->set('form.recipient_name', 'Susan Taylor')
            ->set('form.relationship', 'Parent')->call('next')->assertHasNoErrors()->assertSet('step', 2)
            ->set('form.address_line1', 'Partial address')->call('back')->assertHasNoErrors()->assertSet('step', 1);
        Livewire::test(OnboardingWizard::class)->assertSet('form.address_line1', 'Partial address')
            ->set('form.care_for', 'me')->call('next')->assertHasNoErrors()->assertSet('form.recipient_name', '')->assertSet('form.relationship', '');
    }

    public function test_stale_tab_cannot_overwrite_newer_answers(): void
    {
        $this->enrolled();
        $first = Livewire::test(OnboardingWizard::class);
        $second = Livewire::test(OnboardingWizard::class);
        $first->set('form.care_for', 'me')->call('next')->assertHasNoErrors();
        $second->set('form.care_for', 'family')->call('next')->assertHasErrors('conflict')
            ->call('reloadSaved')->assertSet('step', 2)->assertSet('form.care_for', 'me');
    }

    public function test_required_conditional_fields_and_optional_notes(): void
    {
        $user = $this->enrolled();
        Livewire::test(OnboardingWizard::class)->set('form.care_for', 'family')->call('next')
            ->assertHasErrors(['form.recipient_name', 'form.relationship']);
        $record = $this->completed($user, ['care_for' => 'me', 'care_notes' => '', 'care_preferences' => '', 'welcome_visit' => 'no']);
        $this->assertSame($user->name, $record->submitted_snapshot['recipient_name']);
        $this->assertSame('', $record->submitted_snapshot['visit_date']);
        $this->assertDatabaseCount('family_welcome_visits', 0);
        $this->assertSame('draft', CareRecipientProfile::query()->sole()->status);
    }

    public function test_self_care_preserves_long_signup_names_with_a_bounded_display_name(): void
    {
        $user = $this->enrolled();
        $name = str_repeat('Long Name ', 20);
        $user->update(['name' => trim($name)]);
        $record = $this->completed($user, ['care_for' => 'me', 'welcome_visit' => 'no']);
        $profile = CareRecipientProfile::query()->sole();
        $this->assertSame(trim($name), $profile->full_name);
        $this->assertSame(trim($name), $record->submitted_snapshot['recipient_name']);
        $this->assertLessThanOrEqual(80, mb_strlen($profile->preferred_name));
    }

    public function test_exact_24_hour_boundary_and_dst_use_elapsed_time(): void
    {
        $service = app(FamilyOnboardingService::class);
        $this->travelTo(CarbonImmutable::parse('2026-03-07 08:00:00', 'America/New_York'));
        $service->validateDraft($this->answers(['visit_date' => '2026-03-08', 'phone' => '+19844004008']), [4]);
        $this->travel(1)->seconds();
        $this->expectException(ValidationException::class);
        $service->validateDraft($this->answers(['visit_date' => '2026-03-08', 'phone' => '+19844004008']), [4]);
    }

    public function test_final_submit_revalidates_stale_visit_and_returns_to_scheduling(): void
    {
        $user = $this->enrolled();
        $this->ready($user);
        $component = Livewire::test(OnboardingWizard::class)->assertSet('step', 5);
        $this->travelTo(CarbonImmutable::parse('2026-09-17 10:00:00', 'America/New_York'));
        $component->call('next')->assertSet('step', 4)->assertHasErrors('form.visit_date');
        $this->assertNull(FamilyOnboarding::query()->sole()->completed_at);
        $this->assertDatabaseCount('family_onboarding_deliveries', 0);
    }

    public function test_completion_is_idempotent_and_saves_all_information_without_sharing_profile(): void
    {
        $user = $this->enrolled();
        $ready = $this->ready($user);
        $service = app(FamilyOnboardingService::class);
        $record = $service->complete($user, $ready->revision);
        $service->complete($user, $ready->revision);
        $this->assertDatabaseCount('care_recipient_profiles', 1);
        $this->assertDatabaseCount('family_welcome_visits', 1);
        $this->assertDatabaseCount('family_onboarding_deliveries', 1);
        $this->assertDatabaseCount('care_requests', 0);
        $this->assertNull(CareRecipientProfile::query()->sole()->sharing_acknowledged_at);
        $this->assertSame('A gentle pace.', CareRecipientProfile::query()->sole()->good_visit_notes);
        $this->assertSame('2026-09-18T13:00:00+00:00', FamilyWelcomeVisit::query()->sole()->window_start_at->toIso8601String());
        $this->assertSame('+19844004008', $user->fresh()->phone);
        $this->assertSame('completed', $record->status);
        $this->get(route('family.onboarding'))->assertRedirect(route('family.requests.index'));
        $this->get(route('family.requests.create'))->assertOk();
        $this->assertSame('Susan Taylor', $record->submitted_snapshot['recipient_name']);
    }

    public function test_transaction_failure_leaves_no_partial_profiles_visit_or_delivery(): void
    {
        $user = $this->enrolled();
        $ready = $this->ready($user);
        $this->mock(CareRecipientProfileService::class)->shouldReceive('saveDraft')->once()->andThrow(new RuntimeException('failed'));
        try {
            app(FamilyOnboardingService::class)->complete($user, $ready->revision);
            $this->fail('Expected a failure');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('family_household_profiles', 0);
            $this->assertDatabaseCount('family_welcome_visits', 0);
            $this->assertDatabaseCount('family_onboarding_deliveries', 0);
            $this->assertSame('in_progress', $ready->fresh()->status);
        }
    }

    public function test_homepage_handoff_survives_session_loss_and_preserves_request_edits(): void
    {
        $task = CareTask::query()->create(['name' => 'Companionship']);
        FamilyQuickRequestDraft::put(['care_for' => 'other', 'recipient_full_name' => 'Old Name', 'step' => 4,
            'selectedTasks' => [$task->id], 'requested_start_at' => '2026-09-25 10:00', 'requested_end_at' => '2026-09-25 12:00',
            'address_line1' => 'Old address', 'additional_info' => 'Original care request']);
        $user = $this->enrolled();
        $this->completed($user);
        session()->forget(FamilyQuickRequestDraft::SESSION_KEY);
        Livewire::test(CreateCareRequestWizard::class)->assertSet('recipient_full_name', 'Susan Taylor')
            ->assertSet('address_line1', '123 Oak Street')->assertSet('selectedTasks', [$task->id])
            ->assertSet('requested_start_date', '2026-09-25')->assertSet('quick_profile_good_visit', 'A gentle pace.')
            ->set('address_line1', '456 New Street')->call('previousStep')->assertHasNoErrors();
        Livewire::test(CreateCareRequestWizard::class)->assertSet('address_line1', '456 New Street')->assertSet('step', 3);
    }

    public function test_normal_handoff_starts_at_need_step_and_does_not_schedule_paid_care(): void
    {
        $user = $this->enrolled();
        $this->completed($user);
        Livewire::test(CreateCareRequestWizard::class)->assertSet('step', 1)->assertSet('modeChosen', true)
            ->assertSet('requested_start_date', '')->assertSet('requested_start_time', '')->assertSet('city', 'Raleigh');
    }

    public function test_request_shows_the_full_standard_help_catalog(): void
    {
        $this->completed($this->enrolled());
        $this->seed(\Database\Seeders\CareTaskSeeder::class);
        $this->seed(\Database\Seeders\CareTaskSeeder::class);
        $this->assertDatabaseCount('care_tasks', 7);
        Livewire::test(CreateCareRequestWizard::class)->assertSee('Companionship')
            ->assertSee('Meal preparation')->assertSee('Light housekeeping')->assertSee('Transportation')
            ->assertSee('Medication reminders')->assertSee('Errands')->assertSee('Daily living assistance');
    }

    public function test_onboarding_answers_open_the_simple_profile_and_edits_and_skipping_survive_refresh(): void
    {
        $record = $this->completed($this->enrolled());
        Livewire::test(CreateCareRequestWizard::class)
            ->assertSet('onboardingCareProfileId', $record->care_recipient_profile_id)
            ->assertSet('createQuickCareProfile', true)->assertSee('Simple care profile')
            ->assertSet('quick_profile_about', 'Enjoys the garden.')
            ->assertSet('quick_profile_good_visit', 'A gentle pace.')
            ->assertSet('recipient_care_notes', '')->assertSet('quick_profile_sharing_acknowledged', false)
            ->set('quick_profile_about', 'Enjoys the garden and quiet music.')->assertHasNoErrors();
        Livewire::test(CreateCareRequestWizard::class)
            ->assertSet('quick_profile_about', 'Enjoys the garden and quiet music.')
            ->set('createQuickCareProfile', false)->assertHasNoErrors();
        Livewire::test(CreateCareRequestWizard::class)->assertSet('createQuickCareProfile', false)
            ->assertSet('quick_profile_good_visit', 'A gentle pace.');
        $this->assertDatabaseCount('care_recipient_profiles', 1);
        $this->assertDatabaseCount('care_recipient_profile_versions', 0);
    }

    public function test_old_handoff_reveals_the_profile_and_removes_only_duplicated_notes(): void
    {
        $record = $this->completed($this->enrolled());
        $record->update(['request_context' => ['wizard_state' => [
            'createQuickCareProfile' => false,
            'recipient_care_notes' => FamilyOnboardingService::combinedNotes($record->submitted_snapshot),
            'address_line1' => '456 Edited Street',
        ]]]);
        Livewire::test(CreateCareRequestWizard::class)->assertSet('createQuickCareProfile', true)
            ->assertSet('recipient_care_notes', '')->assertSet('address_line1', '456 Edited Street');
        $record->update(['request_context' => ['wizard_state' => ['recipient_care_notes' => 'Notes added to this request.']]]);
        Livewire::test(CreateCareRequestWizard::class)->assertSet('recipient_care_notes', 'Notes added to this request.');
    }

    public function test_either_onboarding_answer_can_fill_the_profile_without_requiring_the_other(): void
    {
        $task = CareTask::query()->create(['name' => 'Companionship']);
        foreach ([['care_notes' => ''], ['care_preferences' => '']] as $overrides) {
            $record = $this->completed($this->enrolled(), $overrides);
            Livewire::test(CreateCareRequestWizard::class)->assertSet('createQuickCareProfile', true)
                ->set('quick_profile_sharing_acknowledged', true)->set('selectedTasks', [$task->id])
                ->set('requested_start_date', '2026-09-25')->set('requested_start_time', '10:00')
                ->call('publish')->assertHasNoErrors();
            $profile = CareRecipientProfile::query()->findOrFail($record->care_recipient_profile_id);
            $this->assertTrue($profile->isReady());
            $this->assertSame($profile->id, CareRequest::query()->latest('id')->firstOrFail()->recipient->care_recipient_profile_id);
        }
        $this->assertDatabaseCount('care_recipient_profiles', 2);
    }

    public function test_blank_onboarding_answers_do_not_require_a_profile_to_post(): void
    {
        $record = $this->completed($this->enrolled(), ['care_notes' => '', 'care_preferences' => '']);
        $task = CareTask::query()->create(['name' => 'Companionship']);
        Livewire::test(CreateCareRequestWizard::class)->assertSet('createQuickCareProfile', false)
            ->set('selectedTasks', [$task->id])->set('requested_start_date', '2026-09-25')
            ->set('requested_start_time', '10:00')->call('publish')->assertHasNoErrors();
        $this->assertNull(CareRequest::query()->sole()->recipient->care_recipient_profile_id);
        $this->assertSame('draft', CareRecipientProfile::query()->findOrFail($record->care_recipient_profile_id)->status);
    }

    public function test_prefilled_profile_still_requires_sharing_acknowledgment(): void
    {
        $this->completed($this->enrolled());
        $task = CareTask::query()->create(['name' => 'Companionship']);
        Livewire::test(CreateCareRequestWizard::class)->set('selectedTasks', [$task->id])
            ->set('requested_start_date', '2026-09-25')->set('requested_start_time', '10:00')
            ->call('publish')->assertHasErrors('quick_profile_sharing_acknowledged');
        $this->assertDatabaseCount('care_requests', 0);
        $this->assertDatabaseCount('care_recipient_profile_versions', 0);
    }

    public function test_request_does_not_overwrite_a_profile_edited_elsewhere_and_reload_uses_latest_answers(): void
    {
        $user = $this->enrolled();
        $record = $this->completed($user);
        $task = CareTask::query()->create(['name' => 'Companionship']);
        $wizard = Livewire::test(CreateCareRequestWizard::class)->set('selectedTasks', [$task->id])
            ->set('requested_start_date', '2026-09-25')->set('requested_start_time', '10:00')
            ->set('quick_profile_sharing_acknowledged', true)->set('quick_profile_about', 'Unsaved older tab edit.');
        $service = app(CareRecipientProfileService::class);
        $profile = CareRecipientProfile::query()->findOrFail($record->care_recipient_profile_id);
        $profile = $service->saveDraft($user, $profile, $service->mergedData($profile, [
            'about_them' => 'Updated by the family.', 'mobility_level' => 'uses_aid',
        ]), (int) $profile->revision);
        $wizard->call('publish')->assertHasErrors('profile')->assertSee('updated this profile while you were editing');
        $this->assertDatabaseCount('care_requests', 0);
        Livewire::test(CreateCareRequestWizard::class)->assertSet('quick_profile_about', 'Updated by the family.')
            ->assertSet('quick_profile_sharing_acknowledged', false)->set('quick_profile_sharing_acknowledged', true)
            ->call('publish')->assertHasNoErrors();
        $this->assertSame('uses_aid', $profile->fresh()->mobility_level);
        $this->assertDatabaseCount('care_recipient_profiles', 1);
    }

    public function test_single_page_request_edits_resume_without_next_or_back_controls(): void
    {
        $user = $this->enrolled();
        $this->completed($user);
        Livewire::test(CreateCareRequestWizard::class)->set('address_line1', '789 Saved Edit')->assertHasNoErrors();
        Livewire::test(CreateCareRequestWizard::class)->assertSet('address_line1', '789 Saved Edit');
    }

    public function test_email_capture_does_not_log_private_form_data_or_claim_delivery(): void
    {
        $record = $this->completed($this->enrolled());
        Mail::swap(new \Illuminate\Mail\MailManager($this->app));
        config(['mail.default' => 'log']);
        app(FamilyOnboardingDeliveryService::class)->send($record->deliveries()->sole()->id);
        $delivery = $record->deliveries()->sole();
        $this->assertSame('blocked', $delivery->status);
        $this->assertSame('non_delivery_mailer', $delivery->last_error_code);
        $this->assertNull($delivery->accepted_at);
    }

    public function test_rollout_flags_do_not_enroll_existing_or_reset_completed_accounts(): void
    {
        $user = $this->enrolled();
        config(['family_onboarding.enforcement_enabled' => false, 'family_onboarding.enrollment_enabled' => false]);
        $this->get(route('family.requests.create'))->assertOk();
        $newUser = User::factory()->create(['role' => 'family']);
        $this->assertNull(app(FamilyOnboardingService::class)->enrollRegistration($newUser));
        $this->completed($user);
        config(['family_onboarding.enforcement_enabled' => true, 'family_onboarding.enrollment_enabled' => true]);
        $this->assertFalse(app(FamilyOnboardingService::class)->pending($user));
        $this->assertFalse(app(FamilyOnboardingService::class)->pending($newUser));
    }

    public function test_email_sends_all_info_and_duplicate_job_is_a_noop(): void
    {
        $record = $this->completed($this->enrolled(), ['care_notes' => '<script>unsafe</script>']);
        $delivery = $record->deliveries()->sole();
        $service = app(FamilyOnboardingDeliveryService::class);
        $service->send($delivery->id);
        $service->send($delivery->id);
        Mail::assertSent(FamilyOnboardingCompletedMail::class, 1);
        $this->assertSame('accepted', $delivery->fresh()->status);
        $html = (new FamilyOnboardingCompletedMail($record))->render();
        foreach (['Susan Taylor', '123 Oak Street', 'A gentle pace.', 'Morning', 'America/New_York', 'Awaiting confirmation by text', '&lt;script&gt;'] as $value) {
            $this->assertStringContainsString($value, $html);
        }
        $this->assertStringNotContainsString('<script>unsafe', $html);
    }

    public function test_missing_recipients_are_visible_and_recovered_when_configuration_is_fixed(): void
    {
        config(['marketplace.ops_alert_recipients' => []]);
        $record = $this->completed($this->enrolled());
        $this->assertSame('blocked', $record->deliveries()->sole()->status);
        config(['marketplace.ops_alert_recipients' => ['ops@example.com']]);
        app(FamilyOnboardingDeliveryService::class)->recover();
        $this->assertSame(1, $record->deliveries()->where('status', 'pending')->count());
        $this->assertSame(1, $record->deliveries()->where('status', 'superseded')->count());
    }

    private function rejectingTransport(string $message): void
    {
        $manager = new \Illuminate\Mail\MailManager($this->app);
        $transport = Mockery::mock(\Symfony\Component\Mailer\Transport\TransportInterface::class);
        $transport->shouldReceive('send')->once()->andThrow(new TransportException($message));
        $manager->extend('onboarding-test', fn () => $transport);
        config(['mail.default' => 'onboarding-test', 'mail.mailers.onboarding-test' => ['transport' => 'onboarding-test']]);
        Mail::swap($manager);
    }

    public function test_provider_rejection_is_retryable(): void
    {
        $record = $this->completed($this->enrolled());
        $delivery = $record->deliveries()->sole();
        $this->rejectingTransport('Expected response code "250" but got code "550".');
        $service = app(FamilyOnboardingDeliveryService::class);
        $service->send($delivery->id);
        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->next_attempt_at);
        $this->assertSame('completed', $record->fresh()->status);
    }

    public function test_provider_timeout_is_unconfirmed_and_not_blindly_resent(): void
    {
        $record = $this->completed($this->enrolled());
        $delivery = $record->deliveries()->sole();
        $this->rejectingTransport('Connection timed out after DATA.');
        $service = app(FamilyOnboardingDeliveryService::class);
        $service->send($delivery->id);
        $service->send($delivery->id);
        $this->assertSame('unconfirmed', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->next_attempt_at);
    }

    public function test_queue_failure_keeps_pending_outbox_and_recovery_deduplicates_dispatch(): void
    {
        $record = $this->completed($this->enrolled());
        $delivery = $record->deliveries()->sole();
        $delivery->update(['queued_at' => null]);
        \Illuminate\Support\Facades\Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue unavailable'));
        app(FamilyOnboardingDeliveryService::class)->dispatch($delivery->id);
        $this->assertSame('pending', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->queued_at);
        $this->assertSame('completed', $record->fresh()->status);
    }

    public function test_outer_transaction_rollback_does_not_dispatch_or_save_completion(): void
    {
        $user = $this->enrolled();
        $ready = $this->ready($user);
        try {
            DB::transaction(function () use ($user, $ready) {
                app(FamilyOnboardingService::class)->complete($user, $ready->revision);
                throw new RuntimeException('Rollback');
            });
        } catch (RuntimeException) {
            Queue::assertNotPushed(SendFamilyOnboardingEmail::class);
            $this->assertDatabaseCount('family_onboarding_deliveries', 0);
            $this->assertSame('in_progress', $ready->fresh()->status);
        }
    }

    public function test_ownership_transfer_exempts_pending_onboarding_without_prompting_invited_owner(): void
    {
        config(['services.stripe.bypass' => true]);
        $owner = $this->enrolled();
        $account = app(FamilyAccountContext::class)->account($owner);
        $member = User::factory()->create(['role' => 'family']);
        $membership = FamilyAccountMember::query()->create(['family_account_id' => $account->id, 'user_id' => $member->id,
            'access_level' => 'member', 'status' => 'active', 'joined_at' => now()]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        app(FamilyAccountOwnershipService::class)->transfer($admin, $account, $membership, 'Family requested new account owner.');
        $this->assertSame('exempted', FamilyOnboarding::query()->sole()->status);
        $this->assertFalse(app(FamilyOnboardingService::class)->pending($member));
        $this->assertFalse(app(FamilyOnboardingService::class)->pending($owner));
    }

    public function test_lead_context_survives_session_loss(): void
    {
        $lead = \App\Models\Lead::withoutEvents(fn () => \App\Models\Lead::query()->create([
            'lead_type' => 'family', 'email' => 'lead@example.com', 'name' => 'Lead Family', 'zip' => '27602', 'phone' => '9195550100',
            'data' => ['care_preferences' => ['help_needed' => 'Companionship after lunch']],
        ]));
        $welcome = \App\Models\LeadWelcomeEmail::query()->create(['lead_id' => $lead->id, 'email' => $lead->email, 'status' => 'sent']);
        session(['lead_welcome' => ['id' => $welcome->id, 'expires' => now()->addDay()->timestamp]]);
        Volt::test('pages.auth.register')->set('name', 'Lead Family')->set('email', $lead->email)->set('phone', $lead->phone)
            ->set('password', 'password')->set('password_confirmation', 'password')->set('accept_terms', true)->call('register')
            ->assertHasNoErrors()->assertRedirect(route('family.onboarding', absolute: false));
        $user = Auth::user();
        $this->assertSame('lead_welcome', FamilyOnboarding::query()->sole()->source);
        $this->assertSame('27602', FamilyOnboarding::query()->sole()->draft['zip']);
        $this->completed($user, ['zip' => '27603']);
        session()->forget('lead_welcome');
        Livewire::test(CreateCareRequestWizard::class)->assertSet('additional_info', 'Companionship after lunch')->assertSet('zip', '27603');
    }

    public function test_no_clears_previous_visit_and_final_submit_ignores_unsaved_spoofed_fields(): void
    {
        $user = $this->enrolled();
        $this->ready($user);
        Livewire::test(OnboardingWizard::class)->call('back')->set('form.welcome_visit', 'no')->call('next')->assertHasNoErrors()
            ->assertSet('form.visit_date', '')->assertSet('form.visit_time', '')
            ->set('form.recipient_name', 'Unvalidated overwrite')->call('next')->assertRedirect(route('family.requests.create'));
        $this->assertDatabaseCount('family_welcome_visits', 0);
        $this->assertSame('Susan Taylor', FamilyOnboarding::query()->sole()->submitted_snapshot['recipient_name']);
    }

    public function test_published_request_consumes_durable_handoff_once(): void
    {
        $user = $this->enrolled();
        $record = $this->completed($user);
        $task = CareTask::query()->create(['name' => 'Companionship']);
        Livewire::test(CreateCareRequestWizard::class)->set('selectedTasks', [$task->id])
            ->set('requested_start_date', '2026-09-25')->set('requested_start_time', '10:00')
            ->set('requested_duration_minutes', '120')->set('quick_profile_sharing_acknowledged', true)
            ->call('publish')->assertHasNoErrors();
        $request = CareRequest::query()->sole();
        $this->assertSame('Susan Taylor', $request->recipient->full_name);
        $this->assertNull($request->recipient->care_notes);
        $profile = CareRecipientProfile::query()->sole();
        $this->assertSame($record->care_recipient_profile_id, $profile->id);
        $this->assertSame($profile->id, $request->recipient->care_recipient_profile_id);
        $this->assertSame($profile->latest_ready_version_id, $request->recipient->care_recipient_profile_version_id);
        $this->assertSame('Enjoys the garden.', $profile->about_them);
        $this->assertSame('A gentle pace.', $profile->good_visit_notes);
        $this->assertTrue($profile->isReady());
        $this->assertNotNull($record->fresh()->request_handoff_completed_at);
        $this->assertNull($record->fresh()->request_context);
        Livewire::test(CreateCareRequestWizard::class)->assertSet('onboardingHandoffRevision', null)->assertSet('address_line1', '');
    }

    public function test_interrupted_email_delivery_is_unconfirmed_and_not_retried(): void
    {
        $record = $this->completed($this->enrolled());
        $delivery = $record->deliveries()->sole();
        $delivery->update(['status' => 'sending', 'claimed_at' => now()->subMinutes(20), 'attempts' => 1]);
        app(FamilyOnboardingDeliveryService::class)->recover();
        app(FamilyOnboardingDeliveryService::class)->send($delivery->id);
        $this->assertSame('unconfirmed', $delivery->fresh()->status);
        Mail::assertNotSent(FamilyOnboardingCompletedMail::class);
    }

    public function test_admin_view_requires_admin_and_visit_confirmation_is_audited(): void
    {
        $record = $this->completed($this->enrolled());
        $this->get(route('admin.family-onboarding.show', $record))->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.family-onboarding.index'))->assertOk()->assertSee('Visits awaiting follow-up');
        $this->get(route('admin.family-onboarding.show', $record))->assertOk()->assertSee('Susan Taylor');
        Livewire::test(FamilyOnboardingShow::class, ['onboarding' => $record])->set('visitStatus', 'confirmed')
            ->set('confirmedStart', '2026-09-18T10:00')->call('saveVisit')->assertHasErrors('textConfirmed')
            ->set('textConfirmed', true)->call('saveVisit')->assertHasNoErrors();
        $visit = $record->welcomeVisit()->first();
        $this->assertSame('2026-09-18T14:00:00+00:00', $visit->confirmed_start_at->toIso8601String());
        $this->assertSame($admin->id, $visit->handled_by_user_id);
        $this->assertDatabaseHas('family_account_activity_logs', ['action' => 'welcome_visit_updated', 'actor_user_id' => $admin->id]);
    }
}
