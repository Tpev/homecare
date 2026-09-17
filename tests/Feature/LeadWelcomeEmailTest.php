<?php

namespace Tests\Feature;

use App\Jobs\SendLeadWelcomeEmail;
use App\Livewire\Admin\FamilyLeadsIndex;
use App\Livewire\Admin\WelcomeEmailPanel;
use App\Livewire\Family\CreateCareRequestWizard;
use App\Mail\FamilyLeadWelcomeMail;
use App\Models\CareRequest;
use App\Models\FamilyAcquisitionSetting;
use App\Models\Lead;
use App\Models\LeadWelcomeEmail;
use App\Models\User;
use App\Services\FamilyAcquisition\LeadWelcomeService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LeadWelcomeEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
        config(['mail.default' => 'smtp']);
        FamilyAcquisitionSetting::current()->update(['alerts_enabled' => false, 'welcome_email_enabled' => true]);
    }

    private function lead(array $attributes = []): Lead
    {
        return Lead::create(array_merge([
            'lead_type' => 'family', 'name' => 'Sarah Example', 'email' => 'sarah@example.test',
            'phone' => '9195550100', 'source' => 'facebook_lead_ad', 'status' => 'new', 'zip' => '27601',
        ], $attributes));
    }

    public function test_new_facebook_lead_queues_once_and_repeated_jobs_do_not_resend(): void
    {
        $lead = $this->lead();
        $message = $lead->welcomeEmail;
        Queue::assertPushed(SendLeadWelcomeEmail::class, 1);
        $this->assertSame('queued', $message->status);
        app(LeadWelcomeService::class)->capture($lead);
        app(LeadWelcomeService::class)->send($message->id);
        app(LeadWelcomeService::class)->send($message->id);
        Mail::assertSent(FamilyLeadWelcomeMail::class, 1);
        $this->assertSame('sent', $message->fresh()->status);
        $this->assertNotNull($message->fresh()->sent_at);
        $this->assertSame('new', $lead->fresh()->status);
    }

    public function test_duplicate_contacts_across_import_sources_receive_only_one_email(): void
    {
        $this->lead(['external_source' => 'facebook_lead_ads', 'external_id' => 'fb-1']);
        $duplicate = $this->lead(['email' => ' SARAH@example.test ', 'source' => 'meta_lead_ads', 'external_source' => 'google_sheets', 'external_id' => 'row-1']);
        Queue::assertPushed(SendLeadWelcomeEmail::class, 1);
        $this->assertSame('skipped', $duplicate->welcomeEmail->status);
        $this->assertStringContainsString('already recorded', $duplicate->welcomeEmail->reason);
    }

    public function test_missing_email_paused_closed_and_opted_out_leads_are_skipped(): void
    {
        $this->assertSame('No email address provided.', $this->lead(['email' => null])->welcomeEmail->reason);
        $this->assertSame('Invalid email address.', $this->lead(['email' => 'not an email'])->welcomeEmail->reason);
        $this->assertSame('Contact has opted out.', $this->lead(['email' => ' SARAH@EXAMPLE.TEST ', 'do_not_contact_at' => now()])->welcomeEmail->reason);
        $this->assertSame('Contact has opted out.', $this->lead()->welcomeEmail->reason);
        $this->assertSame('This lead is no longer active.', $this->lead(['email' => 'closed@example.test', 'status' => 'closed'])->welcomeEmail->reason);
        FamilyAcquisitionSetting::current()->update(['welcome_email_enabled' => false]);
        $this->assertSame('Automatic welcome email was paused.', $this->lead(['email' => 'paused@example.test'])->welcomeEmail->reason);
        $this->assertNull($this->lead(['source' => 'manual_crm'])->welcomeEmail);
        Queue::assertNothingPushed();
    }

    public function test_worker_rechecks_opt_out_and_pause_before_sending(): void
    {
        $lead = $this->lead();
        $lead->update(['do_not_contact_at' => now()]);
        app(LeadWelcomeService::class)->send($lead->welcomeEmail->id);
        $this->assertSame('skipped', $lead->welcomeEmail->fresh()->status);
        $second = $this->lead(['email' => 'another@example.test']);
        FamilyAcquisitionSetting::current()->update(['welcome_email_enabled' => false]);
        app(LeadWelcomeService::class)->send($second->welcomeEmail->id);
        Mail::assertNothingSent();
    }

    public function test_local_capture_is_not_counted_as_sent(): void
    {
        config(['mail.default' => 'log']);
        $lead = $this->lead();
        app(LeadWelcomeService::class)->send($lead->welcomeEmail->id);
        $this->assertSame('previewed', $lead->welcomeEmail->fresh()->status);
        $this->assertNull($lead->welcomeEmail->fresh()->sent_at);
        $this->assertNotNull($lead->welcomeEmail->fresh()->previewed_at);
    }

    public function test_signed_link_prefills_registration_and_continues_through_onboarding_to_request_creation(): void
    {
        $lead = $this->lead();
        $this->get($lead->welcomeEmail->startUrl())->assertRedirect(route('register'));
        Volt::test('pages.auth.register')
            ->assertSet('name', 'Sarah Example')->assertSet('email', 'sarah@example.test')->assertSet('phone', '9195550100')
            ->set('password', 'password123')->set('password_confirmation', 'password123')->set('accept_terms', true)
            ->call('register')->assertHasNoErrors()->assertRedirect(route('family.onboarding', absolute: false));
        $this->assertAuthenticated();
        $message = $lead->welcomeEmail->fresh();
        $this->assertNotNull($message->account_created_at);
        $this->assertSame(auth()->id(), $message->account_user_id);
        Livewire::test(\App\Livewire\Family\OnboardingWizard::class)->assertSet('form.zip', '27601')
            ->set('form.care_for', 'family')->set('form.recipient_name', 'Sarah’s mother')->set('form.relationship', 'Parent')
            ->call('next')->assertHasNoErrors()
            ->set('form.address_line1', '100 Example Street')->set('form.city', 'Raleigh')->set('form.state', 'NC')
            ->call('next')->assertHasNoErrors()->call('next')->assertHasNoErrors()
            ->set('form.welcome_visit', 'no')->call('next')->assertHasNoErrors()
            ->call('next')->assertHasNoErrors()->assertRedirect(route('family.requests.create'));
        $task = \App\Models\CareTask::create(['name' => 'Companionship']);
        Livewire::test(CreateCareRequestWizard::class)->assertSet('zip', '27601')->assertSet('modeChosen', true)
            ->set('selectedTasks', [$task->id])->set('requested_start_date', now()->addDay()->toDateString())
            ->set('requested_start_time', '10:00')->set('requested_duration_minutes', '120')
            ->set('address_line1', '100 Example Street')->set('city', 'Raleigh')->set('state', 'NC')
            ->set('recipient_full_name', 'Sarah’s mother')->call('publish')->assertHasNoErrors();
        $this->assertNotNull($message->fresh()->request_posted_at);
    }

    public function test_expired_and_tampered_links_are_rejected(): void
    {
        $lead = $this->lead();
        $url = URL::temporarySignedRoute('lead-welcome.start', now()->subMinute(), ['welcomeEmail' => $lead->welcomeEmail->id]);
        $this->get($url)->assertForbidden();
        $this->get($lead->welcomeEmail->startUrl().'&extra=1')->assertForbidden();
        $this->assertNull(session(LeadWelcomeService::SESSION_KEY));
    }

    public function test_existing_user_can_log_in_and_unrelated_account_cannot_claim_lead(): void
    {
        $lead = $this->lead();
        $user = User::factory()->create(['role' => 'family', 'email' => $lead->email]);
        $this->get($lead->welcomeEmail->startUrl());
        Volt::test('pages.auth.login')->set('form.email', $user->email)->set('form.password', 'password')
            ->call('login')->assertHasNoErrors()->assertRedirect(route('family.requests.create', absolute: false));
        $this->assertNotNull($lead->welcomeEmail->fresh()->account_linked_at);
        $this->assertNull($lead->welcomeEmail->fresh()->account_created_at);
        $unrelated = $this->lead(['email' => 'other@example.test']);
        $this->get($unrelated->welcomeEmail->startUrl())->assertRedirect(route('dashboard'));
        $this->assertNull($unrelated->welcomeEmail->fresh()->account_user_id);
    }

    public function test_publication_records_milestone_once_without_changing_call_stage(): void
    {
        $lead = $this->lead();
        $user = User::factory()->create(['role' => 'family', 'email' => $lead->email]);
        event(new Registered($user));
        $request = CareRequest::create([
            'family_user_id' => $user->id, 'created_by_user_id' => $user->id, 'status' => CareRequest::STATUS_DRAFT,
            'title' => 'Help at home', 'request_type' => 'one_time', 'address_line1' => '100 Example St',
            'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        ]);
        $this->assertNull($lead->welcomeEmail->fresh()->request_posted_at);
        $request->update(['status' => CareRequest::STATUS_OPEN]);
        $this->assertSame($request->id, $lead->welcomeEmail->fresh()->care_request_id);
        $this->assertNotNull($lead->welcomeEmail->fresh()->request_posted_at);
        app(LeadWelcomeService::class)->requestPosted($request);
        $this->assertSame(1, $lead->activities()->where('summary', 'Care request posted')->count());
        $this->assertSame('new', $lead->fresh()->status);
        $this->assertNull($lead->fresh()->converted_at);
    }

    public function test_unsubscribe_requires_post_and_suppresses_duplicate_contact_without_stopping_calls(): void
    {
        $lead = $this->lead();
        $url = $lead->welcomeEmail->unsubscribeUrl();
        $this->get($url)->assertOk()->assertSee('Email preferences');
        $this->assertNull($lead->welcomeEmail->fresh()->unsubscribed_at);
        $this->post($url)->assertOk()->assertSee('You’re unsubscribed');
        app(LeadWelcomeService::class)->send($lead->welcomeEmail->id);
        Mail::assertNothingSent();
        $this->assertSame('Contact has opted out.', $this->lead()->welcomeEmail->reason);
        $this->assertNull($lead->fresh()->do_not_contact_at);
    }

    public function test_admin_can_send_a_test_to_a_custom_recipient_without_creating_lead_activity(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Livewire::actingAs($admin)->test(WelcomeEmailPanel::class)
            ->assertSet('testRecipient', $admin->email)
            ->set('testRecipient', ' review@example.test ')->call('sendTest')->assertHasNoErrors()
            ->assertSee('Test email sent to review@example.test.');
        Mail::assertSent(FamilyLeadWelcomeMail::class, fn ($mail) => $mail->hasTo('review@example.test')
            && ! $mail->hasTo($admin->email) && str_starts_with($mail->emailSubject, '[Test] ')
            && $mail->startUrl === route('register'));
        Mail::assertSentCount(1);
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('lead_welcome_emails', 0);
    }

    public function test_test_send_requires_one_valid_recipient(): void
    {
        $panel = Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(WelcomeEmailPanel::class);
        foreach (['', 'invalid', 'one@example.test,two@example.test', "one@example.test\r\nBcc: two@example.test"] as $recipient) {
            $panel->set('testRecipient', $recipient)->call('sendTest')->assertHasErrors('testRecipient');
        }
        Mail::assertNothingSent();
    }

    public function test_settings_preview_filters_and_permissions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Livewire::actingAs($admin)->test(WelcomeEmailPanel::class)->assertSee(LeadWelcomeService::SUBJECT)
            ->set('subject', 'LoLo Care | Your next step')->set('enabled', false)->call('save')->assertHasNoErrors();
        $this->assertFalse(FamilyAcquisitionSetting::current()->welcome_email_enabled);
        $lead = $this->lead();
        $lead->welcomeEmail->update(['status' => 'sent', 'sent_at' => now()]);
        Livewire::actingAs($admin)->test(FamilyLeadsIndex::class)->set('source', 'facebook')->set('welcome', 'sent')->assertSee('Sarah Example');
        Livewire::actingAs($admin)->test(FamilyLeadsIndex::class)->set('welcome', 'request')->assertDontSee('Sarah Example');
        Livewire::actingAs(User::factory()->create(['role' => 'sdr']))->test(WelcomeEmailPanel::class)->assertForbidden();
    }

    public function test_inflight_job_is_not_automatically_resent_after_an_ambiguous_interruption(): void
    {
        $lead = $this->lead();
        $lead->welcomeEmail->update(['status' => 'sending', 'last_attempt_at' => now()->subMinutes(10)]);
        app(LeadWelcomeService::class)->send($lead->welcomeEmail->id);
        Mail::assertNothingSent();
        $this->assertSame('Delivery unconfirmed', $lead->welcomeEmail->fresh()->statusLabel());
    }

    public function test_preparation_error_can_retry_without_replaying_a_transport_attempt(): void
    {
        $lead = $this->lead();
        $originalMail = Mail::getFacadeRoot();
        Mail::shouldReceive('mailer')->once()->andThrow(new \RuntimeException('Invalid mail configuration'));
        try {
            app(LeadWelcomeService::class)->send($lead->welcomeEmail->id);
            $this->fail('Expected a transport exception');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Invalid mail configuration', $exception->getMessage());
        }
        Mail::swap($originalMail);
        $this->assertSame('retrying', $lead->welcomeEmail->fresh()->status);
        $this->assertNull($lead->welcomeEmail->fresh()->last_attempt_at);
        (new SendLeadWelcomeEmail($lead->welcomeEmail->id))->failed(new \RuntimeException('Failed'));
        $this->assertSame('failed', $lead->welcomeEmail->fresh()->status);
        $this->assertTrue($lead->activities()->where('summary', 'Welcome email failed')->exists());
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(WelcomeEmailPanel::class)
            ->call('retry', $lead->welcomeEmail->id)->assertHasNoErrors();
        $this->assertSame('queued', $lead->welcomeEmail->fresh()->status);
        Queue::assertPushed(SendLeadWelcomeEmail::class, 2);
        app(LeadWelcomeService::class)->send($lead->welcomeEmail->id);
        app(LeadWelcomeService::class)->send($lead->welcomeEmail->id);
        Mail::assertSent(FamilyLeadWelcomeMail::class, 1);
    }

    public function test_provider_timeout_is_never_retried_even_by_failed_job_or_admin_retry(): void
    {
        $lead = $this->lead();
        $id = $lead->welcomeEmail->id;
        $originalMail = Mail::getFacadeRoot();
        Mail::shouldReceive('mailer->render')->andReturn('<p>Welcome</p>');
        Mail::shouldReceive('mailer->to->send')->once()->andThrow(new \RuntimeException('Connection lost after acceptance'));
        app(LeadWelcomeService::class)->send($id);
        Mail::swap($originalMail);

        $this->assertSame('unconfirmed', $lead->welcomeEmail->fresh()->status);
        $this->assertNotNull($lead->welcomeEmail->fresh()->last_attempt_at);
        (new SendLeadWelcomeEmail($id))->handle(app(LeadWelcomeService::class));
        (new SendLeadWelcomeEmail($id))->failed(new \RuntimeException('Worker timeout'));
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(WelcomeEmailPanel::class)
            ->assertSee('Delivery unconfirmed')->call('retry', $id);
        Mail::assertNothingSent();
        $this->assertSame('unconfirmed', $lead->welcomeEmail->fresh()->status);
        Queue::assertPushed(SendLeadWelcomeEmail::class, 1);
    }

    public function test_overlapping_workers_cannot_send_the_same_message_twice(): void
    {
        $lead = $this->lead();
        $id = $lead->welcomeEmail->id;
        $originalMail = Mail::getFacadeRoot();
        Mail::shouldReceive('mailer->render')->andReturn('<p>Welcome</p>');
        Mail::shouldReceive('mailer->to->send')->once()->andReturnUsing(function () use ($id): void {
            // A second worker executes while the first is inside the provider call.
            app(LeadWelcomeService::class)->send($id);
            (new SendLeadWelcomeEmail($id))->failed(new \RuntimeException('Stale duplicate job'));
        });
        app(LeadWelcomeService::class)->send($id);
        Mail::swap($originalMail);
        $this->assertSame('sent', $lead->welcomeEmail->fresh()->status);
        $this->assertSame(1, $lead->welcomeEmail->fresh()->attempts);
    }

    public function test_sent_or_attempted_messages_cannot_be_replayed_by_resetting_the_queue_status(): void
    {
        $lead = $this->lead();
        $id = $lead->welcomeEmail->id;
        app(LeadWelcomeService::class)->send($id);
        $lead->welcomeEmail->update(['status' => 'queued']);
        app(LeadWelcomeService::class)->send($id);
        Mail::assertSent(FamilyLeadWelcomeMail::class, 1);

        $attempted = $this->lead(['email' => 'attempted@example.test'])->welcomeEmail;
        $attempted->update(['status' => 'retrying', 'last_attempt_at' => now()]);
        app(LeadWelcomeService::class)->send($attempted->id);
        (new SendLeadWelcomeEmail($attempted->id))->failed(new \RuntimeException('Timeout'));
        $attempted->update(['status' => 'failed']);
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(WelcomeEmailPanel::class)
            ->call('retry', $attempted->id);
        $this->assertSame('failed', $attempted->fresh()->status);
        Mail::assertSent(FamilyLeadWelcomeMail::class, 1);
        Queue::assertPushed(SendLeadWelcomeEmail::class, 2);
    }

    public function test_deleting_and_reimporting_a_lead_preserves_the_contact_reservation_and_opt_out(): void
    {
        $lead = $this->lead();
        $id = $lead->welcomeEmail->id;
        $unsubscribe = $lead->welcomeEmail->unsubscribeUrl();
        app(LeadWelcomeService::class)->send($id);
        $lead->delete();
        $this->assertNull(LeadWelcomeEmail::findOrFail($id)->lead_id);
        $this->assertStringContainsString('already recorded', $this->lead()->welcomeEmail->reason);
        $this->post($unsubscribe)->assertOk()->assertSee('You’re unsubscribed');
        $this->assertSame('Contact has opted out.', $this->lead()->welcomeEmail->reason);
        Mail::assertSent(FamilyLeadWelcomeMail::class, 1);
        Queue::assertPushed(SendLeadWelcomeEmail::class, 1);
    }

    public function test_webhook_replays_and_duplicate_external_ids_send_only_one_welcome(): void
    {
        config(['services.zapier_facebook_leads.webhook_secret' => 'test-secret']);
        $payload = ['Lead Id' => 'fb-welcome-1', 'Full Name' => 'Sarah Example', 'Email' => 'sarah@example.test'];
        $headers = ['X-LoLo-Zapier-Token' => 'test-secret'];
        $this->postJson(route('api.webhooks.zapier.facebook-leads'), $payload, $headers)->assertOk();
        $this->postJson(route('api.webhooks.zapier.facebook-leads'), $payload, $headers)->assertOk();
        $payload['Lead Id'] = 'fb-welcome-2';
        $this->postJson(route('api.webhooks.zapier.facebook-leads'), $payload, $headers)->assertOk();
        LeadWelcomeEmail::each(fn ($message) => app(LeadWelcomeService::class)->send($message->id));
        $this->assertDatabaseCount('leads', 2);
        Queue::assertPushed(SendLeadWelcomeEmail::class, 1);
        Mail::assertSent(FamilyLeadWelcomeMail::class, 1);
    }

    public function test_enabling_does_not_backfill_paused_leads_and_signup_before_send_suppresses_mail(): void
    {
        FamilyAcquisitionSetting::current()->update(['welcome_email_enabled' => false]);
        $old = $this->lead();
        FamilyAcquisitionSetting::current()->update(['welcome_email_enabled' => true]);
        app(LeadWelcomeService::class)->capture($old);
        app(LeadWelcomeService::class)->send($old->welcomeEmail->id);
        Queue::assertNothingPushed();

        $new = $this->lead(['email' => 'new@example.test']);
        User::factory()->create(['role' => 'family', 'email' => $new->email]);
        app(LeadWelcomeService::class)->send($new->welcomeEmail->id);
        Mail::assertNothingSent();
        $this->assertSame('skipped', $new->welcomeEmail->fresh()->status);
    }

    public function test_existing_accounts_are_skipped_and_not_counted_as_new_registrations(): void
    {
        User::factory()->create(['email' => 'sarah@example.test', 'role' => 'family']);
        $message = $this->lead()->welcomeEmail;
        $this->assertSame('Existing account', $message->progressLabel());
        $this->assertSame('skipped', $message->status);
        $this->assertNull($message->account_created_at);
        Queue::assertNothingPushed();
    }

    public function test_duplicate_leads_do_not_inflate_account_or_request_totals(): void
    {
        $lead = $this->lead();
        $this->lead();
        $user = User::factory()->create(['role' => 'family', 'email' => $lead->email]);
        event(new Registered($user));
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(WelcomeEmailPanel::class)
            ->assertViewHas('counts', fn ($counts) => $counts['account'] === 1 && $counts['all'] === 2);
    }
}
