<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendIncomingSmsAlert;
use App\Mail\Ops\IncomingSmsMail;
use App\Models\CaregiverCrmRecord;
use App\Models\FamilyAcquisitionSetting;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\Admin\StaffOffboardingService;
use App\Services\Content\ContentApiTokenManager;
use App\Services\Messaging\TwilioSmsClient;
use App\Support\StaffAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StaffOffboardingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'array']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->staff = User::factory()->create([
            'role' => 'sdr', 'content_role' => 'publisher', 'email' => 'departed@example.test',
            'phone' => '+19195550123', 'sms_opt_in' => true, 'sms_opt_in_phone' => '+19195550123',
            'remember_token' => 'old-remember-token',
        ]);
    }

    public function test_preview_is_read_only_and_requires_the_exact_account(): void
    {
        $this->artisan('staff:offboard', [
            'user' => $this->staff->id, '--email' => $this->staff->email, '--by' => $this->admin->id,
            '--alias' => ['staff-alias@example.test'],
        ])->assertSuccessful();
        $this->assertNull($this->staff->fresh()->access_revoked_at);
        $this->assertDatabaseCount('staff_access_blocks', 0);
        $this->artisan('staff:offboard', [
            'user' => $this->staff->id, '--email' => 'wrong@example.test', '--by' => $this->admin->id, '--apply' => true,
            '--reason' => 'Staff departure confirmed',
        ])->assertFailed();
        $this->assertNull($this->staff->fresh()->access_revoked_at);
    }

    public function test_offboarding_unassigns_work_but_preserves_history_and_other_users(): void
    {
        $lead = Lead::create(['name' => 'Owned lead', 'lead_type' => 'family', 'status' => 'new', 'assigned_admin_id' => $this->staff->id, 'next_follow_up_at' => now()->addDay()]);
        $history = LeadActivity::create(['lead_id' => $lead->id, 'actor_user_id' => $this->staff->id, 'type' => 'call', 'summary' => 'Past call', 'occurred_at' => now()]);
        $otherLead = Lead::create(['name' => 'Other lead', 'lead_type' => 'family', 'status' => 'new', 'assigned_admin_id' => $this->admin->id]);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $record = CaregiverCrmRecord::create(['caregiver_user_id' => $caregiver->id, 'assigned_admin_id' => $this->staff->id, 'notes' => 'Keep this note', 'next_follow_up_at' => now()->addDay()]);
        FamilyAcquisitionSetting::create(['id' => 1, 'alerts_enabled' => true, 'new_lead_alert_emails' => "staff-alias@example.test\nops@example.test", 'escalation_alert_emails' => $this->staff->email]);
        DB::table('sessions')->insert(['id' => 'staff-session', 'user_id' => $this->staff->id, 'payload' => '', 'last_activity' => time()]);
        DB::table('sessions')->insert(['id' => 'other-session', 'user_id' => $this->admin->id, 'payload' => '', 'last_activity' => time()]);
        DB::table('password_reset_tokens')->insert(['email' => $this->staff->email, 'token' => 'old-token']);

        $report = $this->offboard();
        $this->assertSame(1, $report['lead_assignments']);
        $this->assertNull($lead->fresh()->assigned_admin_id);
        $this->assertNotNull($lead->fresh()->next_follow_up_at);
        $this->assertSame($this->staff->id, $history->fresh()->actor_user_id);
        $this->assertSame($this->admin->id, $otherLead->fresh()->assigned_admin_id);
        $this->assertNull($record->fresh()->assigned_admin_id);
        $this->assertSame('Keep this note', $record->fresh()->notes);
        $this->assertSame(1, $record->fresh()->version);
        $this->assertDatabaseMissing('sessions', ['id' => 'staff-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $this->staff->email]);
        $this->assertSame(['ops@example.test'], FamilyAcquisitionSetting::find(1)->newLeadRecipients());
        $this->assertSame(['ops@example.test'], FamilyAcquisitionSetting::find(1)->escalationRecipients());
        $revoked = $this->staff->fresh();
        $this->assertSame('offboarded', $revoked->role);
        $this->assertFalse($revoked->canAccessCrm());
        $this->assertFalse($revoked->isContentTeamMember());
        $this->assertNull($revoked->remember_token);
        $this->assertFalse($revoked->sms_opt_in);

        $again = $this->offboard();
        $this->assertTrue($again['already_revoked']);
        $this->assertSame(0, $again['lead_assignments']);
        $this->assertDatabaseCount('staff_access_blocks', 3);
        $this->assertSame(1, LeadActivity::where('type', 'staff_offboarding')->count());
    }

    public function test_login_sessions_remember_me_and_password_recovery_are_denied(): void
    {
        $provider = Auth::getProvider();
        $this->assertNotNull($provider->retrieveByToken($this->staff->id, 'old-remember-token'));
        $this->offboard();
        $this->assertNull($provider->retrieveById($this->staff->id));
        $this->assertNull($provider->retrieveByToken($this->staff->id, 'old-remember-token'));
        $this->assertFalse(Auth::attempt(['email' => $this->staff->email, 'password' => 'password']));
        Notification::fake();
        $this->assertSame(Password::INVALID_USER, Password::sendResetLink(['email' => $this->staff->email]));
        Notification::assertNothingSent();
        // A stale authenticated model must also fail on a web request.
        $this->actingAs($this->staff)->get('/admin/crm')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertTrue(Auth::attempt(['email' => $this->admin->email, 'password' => 'password']));
    }

    public function test_alias_cannot_be_registered_and_offboarded_account_cannot_be_repromoted(): void
    {
        $this->offboard();
        foreach ([fn () => User::factory()->create(['email' => 'STAFF-ALIAS@example.test']),
            fn () => $this->staff->fresh()->update(['role' => 'sdr'])] as $attempt) {
            try {
                $attempt();
                $this->fail('Blocked identity was accepted.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        $this->assertDatabaseCount('users', 2);
    }

    public function test_alias_owned_by_another_account_aborts_everything(): void
    {
        User::factory()->create(['email' => 'staff-alias@example.test']);
        try {
            $this->offboard();
            $this->fail('A different account was accepted as an alias.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('staff_access_blocks', 0);
            $this->assertNull($this->staff->fresh()->access_revoked_at);
        }
    }

    public function test_shared_phone_and_admin_targets_are_rejected_without_changes(): void
    {
        $other = User::factory()->create(['phone' => '(919) 555-0123']);
        try {
            $this->offboard();
            $this->fail('A shared phone must be reviewed first.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('staff_access_blocks', 0);
        }
        $other->update(['phone' => null]);
        $this->artisan('staff:offboard', ['user' => $this->admin->id, '--email' => $this->admin->email,
            '--by' => $this->admin->id, '--apply' => true, '--reason' => 'A rejected administrator offboarding attempt'])
            ->assertFailed();
        $this->assertDatabaseCount('staff_access_blocks', 0);
    }

    public function test_pending_notifications_are_suppressed_and_user_leaves_active_directory(): void
    {
        $delivery = \App\Models\MarketplaceNotificationDelivery::create([
            'user_id' => $this->staff->id, 'event_key' => 'support_ticket_reply', 'channel' => 'email', 'status' => 'pending',
        ]);
        $this->offboard();
        $this->assertSame('suppressed', $delivery->fresh()->status);
        $notice = new \App\Notifications\MarketplaceEventNotification('support_ticket_reply', 'Old queued notice', 'Body', null, ['mail']);
        $this->assertFalse($notice->shouldSend($this->staff, 'mail'));
        $this->assertFalse($notice->shouldSend($this->staff, 'database'));
        \Livewire\Livewire::actingAs($this->admin)->test(\App\Livewire\Admin\UsersIndex::class)
            ->assertDontSee($this->staff->email)
            ->set('role', 'offboarded')->assertSee($this->staff->email)
            ->call('loginAs', $this->staff->id)->assertHasErrors('loginAs');
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_content_api_and_oauth_credentials_are_revoked(): void
    {
        $issued = app(ContentApiTokenManager::class)->issue($this->staff, 'Staff token', ['content:read'], now()->addDay(), $this->admin);
        $client = DB::table('content_mcp_oauth_clients')->insertGetId(['client_id' => 'test-client', 'name' => 'Test', 'redirect_uris' => '[]', 'expires_at' => now()->addDay()]);
        $base = ['client_id' => $client, 'user_id' => $this->staff->id, 'scopes' => '[]', 'resource' => 'test', 'expires_at' => now()->addDay()];
        DB::table('content_mcp_oauth_authorization_codes')->insert($base + ['code_hash' => str_repeat('a', 64), 'redirect_uri' => 'https://example.test/callback', 'code_challenge' => 'test']);
        $access = DB::table('content_mcp_oauth_access_tokens')->insertGetId($base + ['public_id' => Str::uuid(), 'family_id' => Str::uuid(), 'token_prefix' => 'test', 'token_hash' => str_repeat('b', 64)]);
        DB::table('content_mcp_oauth_refresh_tokens')->insert($base + ['public_id' => Str::uuid(), 'family_id' => Str::uuid(), 'access_token_id' => $access, 'token_hash' => str_repeat('c', 64)]);
        $this->offboard();
        $this->assertNotNull($issued['token']->fresh()->revoked_at);
        $this->assertNull(app(ContentApiTokenManager::class)->authenticate($issued['plain_text_token']));
        $this->assertNotNull(DB::table('content_mcp_oauth_access_tokens')->value('revoked_at'));
        $this->assertNotNull(DB::table('content_mcp_oauth_refresh_tokens')->value('revoked_at'));
        $this->assertNotNull(DB::table('content_mcp_oauth_authorization_codes')->value('used_at'));
    }

    public function test_incoming_sms_alerts_exclude_departed_staff(): void
    {
        Mail::fake();
        $this->offboard();
        $message = SmsMessage::create(['direction' => 'incoming', 'from_phone' => '+19195550999', 'to_phone' => '+19195550888', 'body' => 'A new incoming text', 'status' => 'received']);
        (new SendIncomingSmsAlert($message->id))->handle();
        Mail::assertSent(IncomingSmsMail::class, fn ($mail) => $mail->hasTo($this->admin->email) && ! $mail->hasTo($this->staff->email));
    }

    public function test_transport_suppression_handles_old_mail_to_cc_bcc_and_keeps_other_recipients(): void
    {
        $this->offboard();
        $sent = Mail::raw('Existing queued mail', function ($message) {
            $message->to([$this->staff->email, 'ops@example.test'])
                ->cc('staff-alias@example.test')->bcc('STAFF-ALIAS@example.test')
                ->replyTo('staff-alias@example.test')->subject('Test');
        });
        $this->assertNotNull($sent);
        $email = $sent->getSymfonySentMessage()->getOriginalMessage();
        $this->assertSame(['ops@example.test'], array_map(fn ($a) => $a->getAddress(), $email->getTo()));
        $this->assertSame([], $email->getCc());
        $this->assertSame([], $email->getBcc());
        $this->assertSame([], $email->getReplyTo());
        $this->assertNull(Mail::raw('Old mail', fn ($message) => $message->to($this->staff->email)->subject('Test')));
    }

    public function test_sms_is_blocked_before_any_provider_request(): void
    {
        Http::fake();
        $this->offboard();
        $this->assertTrue(StaffAccess::blocksPhone('(919) 555-0123'));
        try {
            app(TwilioSmsClient::class)->sendMessage('(919) 555-0123', 'Should never send', '+19195550999');
            $this->fail('Blocked SMS was accepted.');
        } catch (\App\Exceptions\Messaging\TwilioSmsRejectedException) {
            Http::assertNothingSent();
        }
    }

    private function offboard(): array
    {
        return app(StaffOffboardingService::class)->offboard($this->admin, $this->staff->id, $this->staff->email,
            ['staff-alias@example.test'], 'Confirmed staff departure; return work to the queues.');
    }
}
