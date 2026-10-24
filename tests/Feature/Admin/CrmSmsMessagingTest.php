<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendIncomingSmsAlert;
use App\Livewire\Admin\FamilyLeadsIndex;
use App\Livewire\Admin\LeadsIndex;
use App\Livewire\Admin\LeadSmsComposer;
use App\Mail\Ops\IncomingSmsMail;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Services\Messaging\LeadSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrmSmsMessagingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::preventStrayRequests();
        config()->set([
            'services.twilio.bypass' => false,
            'services.twilio.account_sid' => 'AC_test',
            'services.twilio.auth_token' => 'test-secret',
            'services.twilio.sms_from' => '+19195550000',
            'services.twilio.messaging_service_sid' => 'MG_test',
            'services.twilio.status_callback_url' => null,
            'services.twilio.webhook_base_url' => null,
        ]);
    }

    public static function crmRoles(): array
    {
        return [['admin'], ['sdr'], ['sales']];
    }

    #[DataProvider('crmRoles')]
    public function test_crm_users_send_to_saved_lead_phone_from_configured_sender(string $role): void
    {
        $actor = User::factory()->create(['role' => $role]);
        $lead = $this->lead();
        $this->fakeSend();
        $composer = Livewire::actingAs($actor)->test(LeadSmsComposer::class, ['leadId' => $lead->id])
            ->call('startMessage')->set('smsBody', 'Hi, when can we call?');
        $lead->update(['phone' => '(984) 555-0123']);

        $composer->call('sendMessage')->assertHasNoErrors()->assertDispatched('lead-sms-updated');
        Http::assertSent(fn ($request) => $request['To'] === '+19845550123'
            && $request['From'] === '+19195550000' && $request['MessagingServiceSid'] === 'MG_test'
            && str_contains($request['StatusCallback'], 'crm_message='));
        $message = SmsMessage::sole();
        $this->assertSame($actor->id, $message->sent_by_user_id);
        $this->assertSame($lead->id, $message->lead_id);
        $this->assertSame('queued', $message->status);
        $this->assertNotNull($lead->fresh()->last_contacted_at);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id, 'sms_message_id' => $message->id, 'body' => 'Hi, when can we call?', 'type' => 'sms',
        ]);
    }

    public function test_templates_can_be_created_edited_selected_personalized_and_deleted(): void
    {
        $actor = User::factory()->create(['role' => 'sdr']);
        $lead = $this->lead();
        $this->fakeSend();
        $component = Livewire::actingAs($actor)->test(LeadSmsComposer::class, ['leadId' => $lead->id])
            ->call('newTemplate')->set('templateName', 'Callback')->set('templateBody', 'Can we call today?')
            ->call('saveTemplate')->assertHasNoErrors();
        $template = SmsTemplate::sole();
        $component->call('editTemplate', $template->id)->set('templateBody', 'What time works for a call?')
            ->call('saveTemplate')->call('startMessage')->set('selectedTemplateId', (string) $template->id)
            ->assertSet('smsBody', 'What time works for a call?')
            ->set('smsBody', 'Hi Sam, what time works for a call?')->call('sendMessage')->assertHasNoErrors()
            ->call('deleteTemplate', $template->id);
        $this->assertDatabaseCount('sms_templates', 0);
        $this->assertSame('Hi Sam, what time works for a call?', SmsMessage::sole()->body);
        $this->assertDatabaseHas('lead_activities', ['body' => 'Hi Sam, what time works for a call?']);
    }

    public function test_invalid_and_blank_templates_and_messages_are_rejected(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $lead = $this->lead(['phone' => 'not a phone']);
        $component = Livewire::actingAs($actor)->test(LeadSmsComposer::class, ['leadId' => $lead->id]);
        $component->set('smsBody', 'Hello')->call('sendMessage')->assertHasErrors('phone');
        $lead->update(['phone' => '9195551234']);
        $component->set('smsBody', '   ')->call('sendMessage')->assertHasErrors('smsBody');
        $component->set('smsBody', str_repeat('a', 1601))->call('sendMessage')->assertHasErrors('smsBody');
        $component->set('templateName', '   ')->set('templateBody', '   ')->call('saveTemplate')
            ->assertHasErrors(['templateName', 'templateBody']);
        Http::assertNothingSent();
        $this->assertDatabaseCount('sms_messages', 0);
    }

    public function test_do_not_contact_and_missing_sender_block_sms(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $lead = $this->lead(['do_not_contact_at' => now()]);
        $component = Livewire::actingAs($actor)->test(LeadSmsComposer::class, ['leadId' => $lead->id])
            ->set('smsBody', 'Hello')->call('sendMessage')->assertHasErrors('send');
        $lead->update(['do_not_contact_at' => null]);
        config()->set('services.twilio.sms_from', '');
        $component->call('sendMessage')->assertHasErrors('send');
        Http::assertNothingSent();
    }

    public function test_only_current_crm_users_can_access_messaging_and_templates(): void
    {
        $lead = $this->lead();
        foreach (['family', 'caregiver'] as $role) {
            Livewire::actingAs(User::factory()->create(['role' => $role]))
                ->test(LeadSmsComposer::class, ['leadId' => $lead->id])->assertForbidden();
        }
        $sdr = User::factory()->create(['role' => 'sdr']);
        $component = Livewire::actingAs($sdr)->test(LeadSmsComposer::class, ['leadId' => $lead->id]);
        $sdr->update(['role' => 'family']);
        $component->call('newTemplate')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_duplicate_send_requests_submit_only_once(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $lead = $this->lead();
        $this->fakeSend();
        $key = (string) Str::uuid();
        $service = app(LeadSmsService::class);
        $one = $service->send($lead, $actor, 'Hello', $key);
        $two = $service->send($lead, $actor, 'Hello', $key);
        $this->assertSame($one->id, $two->id);
        Http::assertSentCount(1);
        $this->assertDatabaseCount('sms_messages', 1);
        $this->assertSame(1, LeadActivity::whereNotNull('sms_message_id')->count());
    }

    public function test_provider_failure_is_visible_in_lead_timeline(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $lead = $this->lead();
        Http::fake(['api.twilio.com/*' => Http::response(['message' => 'Recipient is blocked'], 400)]);
        Livewire::actingAs($actor)->test(LeadSmsComposer::class, ['leadId' => $lead->id])
            ->call('startMessage')->set('smsBody', 'Hello')->call('sendMessage')->assertHasErrors('send');
        $this->assertSame('failed', SmsMessage::sole()->status);
        Livewire::actingAs($actor)->test(LeadsIndex::class)->call('openLead', $lead->id)
            ->assertSee('Delivery: Failed')->assertSee('Recipient is blocked')->assertSee('Hello');
        $this->assertNull($lead->fresh()->last_contacted_at);
    }

    public function test_incoming_sms_links_normalized_phone_notifies_admins_and_sdr_once_and_keeps_timestamp(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $secondAdmin = User::factory()->create(['role' => 'admin']);
        $johnny = User::factory()->create(['role' => 'sdr', 'name' => 'Johnny']);
        $sales = User::factory()->create(['role' => 'sales']);
        $family = User::factory()->create(['role' => 'family']);
        $lead = $this->lead(['phone' => '(919) 555-1234']);
        $payload = $this->incoming(['Body' => 'Please call <script>alert(1)</script> tomorrow.']);
        $this->signedPost('webhooks.twilio.sms', $payload)->assertOk();
        $receivedAt = SmsMessage::sole()->received_at->toISOString();
        $this->travel(2)->minutes();
        $this->signedPost('webhooks.twilio.sms', $payload)->assertOk();
        $message = SmsMessage::sole();
        $this->assertSame($lead->id, $message->lead_id);
        $this->assertSame($receivedAt, $message->received_at->toISOString());
        $this->assertSame(1, LeadActivity::where('sms_message_id', $message->id)->count());
        Mail::assertSent(IncomingSmsMail::class, function ($mail) use ($admin, $secondAdmin, $johnny, $sales, $family, $lead) {
            $html = $mail->render();
            $this->assertStringContainsString('lead='.$lead->id, $html);
            $this->assertStringContainsString('&lt;script&gt;', $html);
            $this->assertStringNotContainsString('<script>', $html);

            return $mail->hasTo($admin->email) && $mail->hasTo($secondAdmin->email) && $mail->hasTo($johnny->email)
                && $mail->hasTo('peverelli.t@gmail.com') && count($mail->to) === 4
                && ! $mail->hasTo($sales->email) && ! $mail->hasTo($family->email);
        });
        Mail::assertSent(IncomingSmsMail::class, 1);
        $this->assertNotNull($message->inbound_notified_at);
    }

    public function test_personal_email_receives_incoming_sms_without_an_account_and_is_not_duplicated_with_one(): void
    {
        $this->signedPost('webhooks.twilio.sms', $this->incoming())->assertOk();

        User::factory()->create(['role' => 'admin', 'email' => 'Peverelli.T@gmail.com']);
        $this->signedPost('webhooks.twilio.sms', $this->incoming(['MessageSid' => 'SM_second_incoming']))->assertOk();

        $this->assertCount(2, Mail::sent(IncomingSmsMail::class, fn ($mail) => $mail->hasTo('peverelli.t@gmail.com') && count($mail->to) === 1
        ));
        Mail::assertSent(IncomingSmsMail::class, 2);
        $this->assertSame(2, SmsMessage::whereNotNull('inbound_notified_at')->count());
    }

    public function test_incoming_email_contains_conversation_and_attachment_details(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $lead = $this->lead();
        $this->fakeSend();
        app(LeadSmsService::class)->send($lead, $actor, 'Could you send the schedule?', (string) Str::uuid());
        $this->signedPost('webhooks.twilio.sms', $this->incoming([
            'NumMedia' => '1', 'MediaUrl0' => 'https://api.twilio.com/media/example', 'MediaContentType0' => 'image/jpeg',
        ]))->assertOk();
        Mail::assertSent(IncomingSmsMail::class, function ($mail) {
            $html = $mail->render();
            $this->assertStringContainsString('Could you send the schedule?', $html);
            $this->assertStringContainsString('https://api.twilio.com/media/example', $html);
            $this->assertStringContainsString('+19195551234', $html);

            return true;
        });
    }

    public function test_unknown_and_ambiguous_senders_are_not_attached_to_arbitrary_leads_but_are_emailed(): void
    {
        User::factory()->create(['role' => 'admin']);
        $this->lead();
        $this->lead();
        $this->signedPost('webhooks.twilio.sms', $this->incoming())->assertOk();
        $this->signedPost('webhooks.twilio.sms', $this->incoming(['MessageSid' => 'SM_unknown', 'From' => '+19195559999']))->assertOk();
        $this->assertSame(0, SmsMessage::whereNotNull('lead_id')->count());
        $this->assertSame(0, LeadActivity::whereNotNull('sms_message_id')->count());
        Mail::assertSent(IncomingSmsMail::class, 2);
    }

    public function test_shared_phone_reply_uses_most_recent_crm_conversation(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $first = $this->lead();
        $second = $this->lead();
        Http::fakeSequence()->push(['sid' => 'SM_first', 'status' => 'queued'], 201)
            ->push(['sid' => 'SM_second', 'status' => 'queued'], 201);
        app(LeadSmsService::class)->send($first, $actor, 'First', (string) Str::uuid());
        app(LeadSmsService::class)->send($second, $actor, 'Second', (string) Str::uuid());
        $this->signedPost('webhooks.twilio.sms', $this->incoming())->assertOk();
        $this->assertSame($second->id, SmsMessage::where('direction', 'incoming')->sole()->lead_id);
    }

    public function test_delivery_callbacks_update_both_crm_timelines_without_duplicate_notes_or_status_regression(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $lead = $this->lead();
        $this->fakeSend();
        $sms = app(LeadSmsService::class)->send($lead, $actor, 'Hello from CRM', (string) Str::uuid());
        $payload = ['MessageSid' => $sms->twilio_sid, 'MessageStatus' => 'delivered'];
        $this->post(route('webhooks.twilio.sms.status'), $payload, ['X-Twilio-Signature' => 'bad'])->assertUnauthorized();
        $this->assertSame('queued', $sms->fresh()->status);
        $this->signedPost('webhooks.twilio.sms.status', $payload)->assertOk();
        $this->signedPost('webhooks.twilio.sms.status', array_merge($payload, ['MessageStatus' => 'sent']))->assertOk();
        $this->signedPost('webhooks.twilio.sms.status', $payload)->assertOk();
        $this->assertSame('delivered', $sms->fresh()->status);
        $this->assertSame(1, LeadActivity::where('sms_message_id', $sms->id)->count());
        foreach ([LeadsIndex::class, FamilyLeadsIndex::class] as $component) {
            Livewire::actingAs($actor)->test($component)->call('openLead', $lead->id)
                ->assertSee('Send SMS')->assertSee('Hello from CRM')->assertSee('Delivery: Delivered');
        }
    }

    public function test_callback_arriving_before_send_response_is_correlated_and_not_overwritten(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $lead = $this->lead();
        Http::fake(function ($request) {
            $payload = ['MessageSid' => 'SM_early', 'MessageStatus' => 'undelivered',
                'From' => $request['From'], 'To' => $request['To'], 'ErrorCode' => '30003', 'ErrorMessage' => 'Unreachable'];
            $url = $request['StatusCallback'];
            $this->post($url, $payload, ['X-Twilio-Signature' => $this->signature($url, $payload)])->assertOk();

            return Http::response(['sid' => 'SM_early', 'status' => 'queued'], 201);
        });
        $sms = app(LeadSmsService::class)->send($lead, $actor, 'Hello', (string) Str::uuid());
        $this->assertSame('undelivered', $sms->status);
        $this->assertSame('30003', $sms->error_code);
        $this->assertSame('Unreachable', $sms->error_message);
    }

    public function test_email_job_is_queued_and_a_completed_job_does_not_send_twice(): void
    {
        User::factory()->create(['role' => 'sdr']);
        Queue::fake();
        $this->signedPost('webhooks.twilio.sms', $this->incoming())->assertOk();
        Queue::assertPushed(SendIncomingSmsAlert::class);
        $job = new SendIncomingSmsAlert(SmsMessage::sole()->id);
        $job->handle();
        $job->handle();
        Mail::assertSent(IncomingSmsMail::class, 1);
    }

    public function test_lead_phone_index_tracks_edits_and_crm_email_link_opens_selected_lead(): void
    {
        $actor = User::factory()->create(['role' => 'sdr']);
        $lead = $this->lead(['lead_type' => Lead::TYPE_REFERRAL]);
        $lead->update(['phone' => '984-555-9876']);
        $this->assertSame('+19845559876', $lead->fresh()->sms_phone);
        $this->actingAs($actor)->get(route('admin.crm.index', ['pipeline' => 'referral', 'lead' => $lead->id]))
            ->assertOk()->assertSee('Send SMS')->assertSee('Manage SMS templates');
    }

    public function test_phone_matching_also_works_for_quiet_imports_and_ignores_invalid_long_numbers(): void
    {
        $lead = Lead::withoutEvents(fn () => $this->lead());
        $this->assertSame('+19195551234', $lead->sms_phone);
        $lead->phone = '(984) 555-9876';
        $lead->saveQuietly();
        $this->assertSame('+19845559876', $lead->fresh()->sms_phone);
        $lead->update(['phone' => str_repeat('9', 40)]);
        $this->assertNull($lead->fresh()->sms_phone);
    }

    public function test_failed_notification_can_retry_without_losing_incoming_text_or_duplicating_notes(): void
    {
        User::factory()->create(['role' => 'admin']);
        $this->lead();
        Queue::fake();
        $this->signedPost('webhooks.twilio.sms', $this->incoming())->assertOk();
        $message = SmsMessage::sole();
        $job = new SendIncomingSmsAlert($message->id);
        $mailFake = Mail::getFacadeRoot();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Mail unavailable'));
        try {
            $job->handle();
            $this->fail('The queue should retry a mail failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Mail unavailable', $exception->getMessage());
        }
        $this->assertNull($message->fresh()->inbound_notified_at);
        Mail::swap($mailFake);
        $job->handle();
        $job->handle();
        Mail::assertSent(IncomingSmsMail::class, 1);
        $this->assertSame(1, LeadActivity::where('sms_message_id', $message->id)->count());
    }

    public function test_migration_can_roll_back_and_backfill_existing_lead_phone_numbers(): void
    {
        $lead = $this->lead();
        $migration = require database_path('migrations/2026_10_23_120000_add_crm_sms_messaging.php');
        $migration->down();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('leads', 'sms_phone'));
        $migration->up();
        $this->assertSame('+19195551234', $lead->fresh()->sms_phone);
    }

    private function lead(array $attributes = []): Lead
    {
        return Lead::create(array_merge(['lead_type' => Lead::TYPE_FAMILY, 'name' => 'Sam Example',
            'phone' => '919-555-1234', 'email' => 'sam@example.test', 'status' => 'new', 'source' => 'manual_crm'], $attributes));
    }

    private function fakeSend(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM_outgoing', 'status' => 'queued', 'account_sid' => 'AC_test'], 201)]);
    }

    private function incoming(array $overrides = []): array
    {
        return array_merge(['MessageSid' => 'SM_incoming', 'AccountSid' => 'AC_test', 'From' => '+19195551234',
            'To' => '+19195550000', 'Body' => 'Tomorrow works.', 'NumMedia' => '0', 'SmsStatus' => 'received'], $overrides);
    }

    private function signedPost(string $route, array $payload)
    {
        $url = route($route);

        return $this->post($url, $payload, ['X-Twilio-Signature' => $this->signature($url, $payload)]);
    }

    private function signature(string $url, array $payload): string
    {
        ksort($payload, SORT_STRING);
        foreach ($payload as $key => $value) {
            $url .= $key.$value;
        }

        return base64_encode(hash_hmac('sha1', $url, 'test-secret', true));
    }
}
