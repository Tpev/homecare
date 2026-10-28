<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\LeadsIndex;
use App\Livewire\Admin\LeadSmsComposer;
use App\Models\Lead;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\Messaging\LeadSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrmSmsRetrySafetyTest extends TestCase
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
            'services.twilio.messaging_service_sid' => null,
            'services.twilio.status_callback_url' => null,
            'services.twilio.webhook_base_url' => null,
        ]);
    }

    public function test_timeout_keeps_one_attempt_and_blocks_retry_after_a_new_composer_or_request_key(): void
    {
        [$actor, $lead] = $this->fixture();
        $accepted = 0;
        Http::fake(function () use (&$accepted) {
            $accepted++;
            throw new ConnectionException('The provider accepted the text but its response timed out.');
        });
        $composer = Livewire::actingAs($actor)->test(LeadSmsComposer::class, ['leadId' => $lead->id])
            ->call('startMessage')->set('smsBody', 'Can we call today?');
        $key = $composer->get('requestKey');
        $composer->call('sendMessage')->assertHasNoErrors()->assertSet('requestKey', $key)
            ->assertSee('Awaiting confirmation.')->call('sendMessage')->assertHasNoErrors();
        $message = SmsMessage::sole();
        $this->assertSame(SmsMessage::STATUS_CONFIRMATION_PENDING, $message->status);
        $this->assertNull($message->twilio_sid);
        $this->assertNull($lead->fresh()->last_contacted_at);

        $reloaded = Livewire::actingAs($actor)->test(LeadSmsComposer::class, ['leadId' => $lead->id])
            ->call('startMessage')->set('smsBody', 'Can we call today?')->call('sendMessage')
            ->assertSet('requestKey', $key)->assertSet('pendingMessageId', $message->id);
        $reloaded->call('startMessage')->assertSet('requestKey', $key);
        $this->assertSame(1, $accepted);
        $this->assertDatabaseCount('sms_messages', 1);
        $this->assertDatabaseCount('lead_activities', 1);
        Livewire::actingAs($actor)->test(LeadsIndex::class)->call('openLead', $lead->id)
            ->assertSee('Delivery: Awaiting confirmation')->assertDontSee('confirmation_pending');
        $this->actingAs($actor)->get(route('admin.sms.index'))->assertOk()->assertSee('AWAITING CONFIRMATION');
    }

    public static function uncertainResponses(): array
    {
        return [
            'server error' => [500, ['message' => 'Server unavailable']],
            'gateway error' => [502, ['message' => 'Gateway timeout']],
            'request timeout' => [408, ['message' => 'Request timeout']],
            'unclassified conflict' => [409, ['message' => 'Conflict']],
            'accepted response missing identifier' => [201, ['status' => 'queued']],
            'invalid accepted response' => [201, 'not-json'],
        ];
    }

    #[DataProvider('uncertainResponses')]
    public function test_uncertain_response_never_permits_a_replacement_send(int $status, array|string $payload): void
    {
        [$actor, $lead] = $this->fixture();
        Http::fake(['api.twilio.com/*' => Http::response($payload, $status)]);
        $service = app(LeadSmsService::class);
        $original = $service->send($lead, $actor, 'Hello', (string) Str::uuid());
        $retry = $service->send($lead, $actor, 'Hello', (string) Str::uuid());
        $this->assertSame($original->id, $retry->id);
        $this->assertSame(SmsMessage::STATUS_CONFIRMATION_PENDING, $retry->status);
        Http::assertSentCount(1);
        $this->assertDatabaseCount('sms_messages', 1);
    }

    public static function rejectedResponses(): array
    {
        return [['invalid request', 400], ['rate limit', 429]];
    }

    #[DataProvider('rejectedResponses')]
    public function test_definite_rejection_allows_a_new_attempt(string $reason, int $status): void
    {
        [$actor, $lead] = $this->fixture();
        Http::fakeSequence()->push(['message' => $reason], $status)->push(['sid' => 'SM_retry', 'status' => 'queued'], 201);
        $composer = Livewire::actingAs($actor)->test(LeadSmsComposer::class, ['leadId' => $lead->id])
            ->call('startMessage')->set('smsBody', 'Hello');
        $key = $composer->get('requestKey');
        $composer->call('sendMessage')->assertHasErrors('send')->assertSet('pendingMessageId', null);
        $this->assertNotSame($key, $composer->get('requestKey'));
        $composer->call('sendMessage')->assertHasNoErrors()->assertSet('showComposer', false);
        $this->assertSame(['failed', 'queued'], SmsMessage::orderBy('id')->pluck('status')->all());
        Http::assertSentCount(2);
    }

    public function test_late_signed_callback_resolves_the_original_attempt_without_resending(): void
    {
        [$actor, $lead] = $this->fixture();
        $callback = '';
        $accepted = 0;
        Http::fake(function ($request) use (&$callback, &$accepted) {
            $callback = $request['StatusCallback'];
            $accepted++;
            throw new ConnectionException('Response timed out.');
        });
        $composer = Livewire::actingAs($actor)->test(LeadSmsComposer::class, ['leadId' => $lead->id])
            ->call('startMessage')->set('smsBody', 'Hello')->call('sendMessage');
        $message = SmsMessage::sole();
        $this->postDelivery($callback, 'delivered')->assertOk();
        $composer->call('refreshPendingMessage')->assertSet('pendingMessageId', null)->assertSet('showComposer', false);
        $this->assertSame('delivered', $message->fresh()->status);
        $this->assertNull($message->fresh()->error_message);
        $this->assertSame('SM_confirmed', $message->fresh()->twilio_sid);
        $this->assertSame($message->id, app(LeadSmsService::class)->send($lead, $actor, 'Hello', $message->request_key)->id);
        $this->assertSame(1, $accepted);
        $this->assertDatabaseCount('sms_messages', 1);
    }

    public function test_confirmed_delivery_failure_after_timeout_allows_an_explicit_retry(): void
    {
        [$actor, $lead] = $this->fixture();
        $callback = '';
        $attempts = 0;
        Http::fake(function ($request) use (&$callback, &$attempts) {
            if (++$attempts > 1) {
                return Http::response(['sid' => 'SM_new_attempt', 'status' => 'queued'], 201);
            }
            $callback = $request['StatusCallback'];
            throw new ConnectionException('Response timed out.');
        });
        $composer = Livewire::actingAs($actor)->test(LeadSmsComposer::class, ['leadId' => $lead->id])
            ->call('startMessage')->set('smsBody', 'Hello')->call('sendMessage');
        $key = $composer->get('requestKey');
        $this->postDelivery($callback, 'undelivered')->assertOk();
        $composer->call('refreshPendingMessage')->assertHasErrors('send')->assertSet('pendingMessageId', null);
        $this->assertNotSame($key, $composer->get('requestKey'));
        $composer->call('sendMessage')->assertHasNoErrors();
        $this->assertSame(['undelivered', 'queued'], SmsMessage::orderBy('id')->pluck('status')->all());
        $this->assertSame(2, $attempts);
    }

    public function test_late_confirmation_records_contact_without_replacing_a_more_recent_contact(): void
    {
        [$actor, $lead] = $this->fixture();
        Http::fake(['api.twilio.com/*' => Http::response(['message' => 'Server error'], 500)]);
        $this->travelTo(now()->startOfSecond());
        $originalContact = now();
        $message = app(LeadSmsService::class)->send($lead, $actor, 'Hello', (string) Str::uuid());
        $url = route('webhooks.twilio.sms.status').'?crm_message='.$message->id;
        $this->travel(10)->minutes();
        $this->postDelivery($url, 'queued')->assertOk();
        $this->assertTrue($lead->fresh()->last_contacted_at->equalTo($originalContact));

        $newerContact = now();
        $lead->update(['last_contacted_at' => $newerContact]);
        $this->travel(10)->minutes();
        $this->postDelivery($url, 'delivered')->assertOk();
        $this->assertTrue($lead->fresh()->last_contacted_at->equalTo($newerContact));
        Http::assertSentCount(1);
    }

    public function test_callback_before_transport_error_preserves_confirmed_acceptance(): void
    {
        [$actor, $lead] = $this->fixture();
        Http::fake(function ($request) {
            $this->postDelivery($request['StatusCallback'], 'delivered')->assertOk();
            throw new ConnectionException('Response timed out after callback.');
        });
        $message = app(LeadSmsService::class)->send($lead, $actor, 'Hello', (string) Str::uuid());
        $this->assertSame('delivered', $message->status);
        $this->assertNull($message->error_message);
        $this->assertFalse($message->awaitingProviderConfirmation());
    }

    public function test_a_second_request_during_the_provider_call_cannot_send_again(): void
    {
        [$actor, $lead] = $this->fixture();
        $accepted = 0;
        Http::fake(function () use ($actor, $lead, &$accepted) {
            $accepted++;
            $duringSend = app(LeadSmsService::class)->send($lead, $actor, 'Hello', (string) Str::uuid());
            $this->assertSame('sending', $duringSend->status);

            return Http::response(['sid' => 'SM_once', 'status' => 'queued'], 201);
        });
        app(LeadSmsService::class)->send($lead, $actor, 'Hello', (string) Str::uuid());
        $this->assertSame(1, $accepted);
        $this->assertDatabaseCount('sms_messages', 1);
    }

    public function test_different_crm_user_or_duplicate_lead_cannot_bypass_an_unknown_attempt(): void
    {
        [$actor, $lead] = $this->fixture();
        Http::fake(['api.twilio.com/*' => Http::response(['message' => 'Server error'], 500)]);
        app(LeadSmsService::class)->send($lead, $actor, 'Hello', (string) Str::uuid());
        $other = User::factory()->create(['role' => 'sdr']);
        $duplicateLead = $lead->replicate();
        $duplicateLead->save();
        foreach ([[$other, $lead], [$actor, $duplicateLead]] as [$sender, $target]) {
            try {
                app(LeadSmsService::class)->send($target, $sender, 'Hello', (string) Str::uuid());
                $this->fail('An unresolved identical text must not be submitted again.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('send', $error->errors());
            }
        }
        Http::assertSentCount(1);
        $this->assertDatabaseCount('sms_messages', 1);
    }

    public function test_request_identity_cannot_be_reused_with_different_content(): void
    {
        [$actor, $lead] = $this->fixture();
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM_once', 'status' => 'queued'], 201)]);
        $key = (string) Str::uuid();
        app(LeadSmsService::class)->send($lead, $actor, 'Original text', $key);
        try {
            app(LeadSmsService::class)->send($lead, $actor, 'Changed text', $key);
            $this->fail('The recorded attempt must keep its original text.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('send', $error->errors());
        }
        $this->assertSame('Original text', SmsMessage::sole()->body);
        Http::assertSentCount(1);
    }

    public function test_invalid_or_mismatched_callback_cannot_clear_unknown_status(): void
    {
        [$actor, $lead] = $this->fixture();
        Http::fake(['api.twilio.com/*' => Http::response(['message' => 'Server error'], 500)]);
        $message = app(LeadSmsService::class)->send($lead, $actor, 'Hello', (string) Str::uuid());
        $url = route('webhooks.twilio.sms.status').'?crm_message='.$message->id;
        $this->post($url, ['MessageSid' => 'SM_fake', 'MessageStatus' => 'delivered'], ['X-Twilio-Signature' => 'bad'])->assertUnauthorized();
        $this->postDelivery($url, 'delivered', ['To' => '+19195559999'])->assertOk();
        $this->assertSame(SmsMessage::STATUS_CONFIRMATION_PENDING, $message->fresh()->status);
        $this->assertNull($message->fresh()->twilio_sid);
    }

    private function fixture(): array
    {
        return [User::factory()->create(['role' => 'admin']), Lead::create([
            'lead_type' => Lead::TYPE_FAMILY, 'name' => 'Sam Example', 'phone' => '919-555-1234',
            'email' => 'sam@example.test', 'status' => 'new', 'source' => 'manual_crm',
        ])];
    }

    private function postDelivery(string $url, string $status, array $extra = [])
    {
        $payload = array_merge(['MessageSid' => 'SM_confirmed', 'MessageStatus' => $status,
            'From' => '+19195550000', 'To' => '+19195551234'], $extra);
        ksort($payload, SORT_STRING);
        $signed = $url;
        foreach ($payload as $key => $value) {
            $signed .= $key.$value;
        }

        return $this->post($url, $payload, ['X-Twilio-Signature' => base64_encode(hash_hmac('sha1', $signed, 'test-secret', true))]);
    }
}
