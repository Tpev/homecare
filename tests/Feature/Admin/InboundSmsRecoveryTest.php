<?php

namespace Tests\Feature\Admin;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\SmsMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InboundSmsRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private const SID = 'SMaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const ACCOUNT = 'ACbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.twilio.bypass' => false, 'services.twilio.account_sid' => self::ACCOUNT,
            'services.twilio.auth_token' => 'test-token', 'services.twilio.sms_from' => '+19844004008']);
        Http::preventStrayRequests();
        Mail::fake();
        Queue::fake();
    }

    public function test_recovery_restores_inbox_and_crm_once_with_original_time_without_sending_messages(): void
    {
        $lead = $this->lead();
        Http::fake([$this->endpoint().'.json' => Http::response($this->payload())]);

        $this->artisan('sms:recover-inbound', ['messageSid' => self::SID])->expectsOutputToContain('Recovered:')->assertSuccessful();
        $message = SmsMessage::sole();
        $this->assertSame('Message received during deployment.', $message->body);
        $this->assertSame('2026-10-30 13:25:00', $message->received_at->utc()->format('Y-m-d H:i:s'));
        $this->assertTrue($message->created_at->equalTo($message->received_at));
        $this->assertSame($lead->id, $message->lead_id);
        $this->assertSame($message->id, LeadActivity::sole()->sms_message_id);

        $this->artisan('sms:recover-inbound', ['messageSid' => self::SID])->expectsOutputToContain('Already present:')->assertSuccessful();
        $this->assertDatabaseCount('sms_messages', 1);
        $this->assertDatabaseCount('lead_activities', 1);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.sms.index'))
            ->assertOk()->assertSee('Message received during deployment.');
        Http::assertSentCount(2);
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
    }

    public function test_recovery_preserves_existing_message_when_a_delayed_webhook_already_saved_it(): void
    {
        $existing = SmsMessage::create(['twilio_sid' => self::SID, 'twilio_account_sid' => self::ACCOUNT,
            'direction' => 'incoming', 'status' => 'received', 'from_phone' => '+19843104633',
            'to_phone' => '+19844004008', 'body' => 'Existing body', 'received_at' => now()->subMinute(),
            'inbound_notified_at' => now()]);
        Http::fake([$this->endpoint().'.json' => Http::response($this->payload())]);

        $this->artisan('sms:recover-inbound', ['messageSid' => self::SID])->assertSuccessful();
        $this->assertSame('Existing body', $existing->fresh()->body);
        $this->assertDatabaseCount('sms_messages', 1);
        Queue::assertNothingPushed();
    }

    public function test_attachments_are_recovered_with_fixed_twilio_urls(): void
    {
        $mediaSid = 'MEcccccccccccccccccccccccccccccccc';
        Http::fake([
            $this->endpoint().'.json' => Http::response($this->payload(['num_media' => '1', 'body' => ''])),
            $this->endpoint().'/Media.json*' => Http::response(['media_list' => [
                ['sid' => $mediaSid, 'account_sid' => self::ACCOUNT, 'parent_sid' => self::SID,
                    'content_type' => 'image/jpeg', 'uri' => 'https://untrusted.example/ignored'],
            ], 'next_page_uri' => null]),
        ]);
        $this->artisan('sms:recover-inbound', ['messageSid' => self::SID])->assertSuccessful();
        $this->assertSame([['url' => $this->endpoint().'/Media/'.$mediaSid, 'content_type' => 'image/jpeg']], SmsMessage::sole()->media);
        $this->assertSame(1, SmsMessage::sole()->num_media);
        Http::assertSentCount(2);
    }

    public function test_missing_attachments_abort_before_importing_anything(): void
    {
        Http::fake([
            $this->endpoint().'.json' => Http::response($this->payload(['num_media' => '1'])),
            $this->endpoint().'/Media.json*' => Http::response(['media_list' => [], 'next_page_uri' => null]),
        ]);
        $this->artisan('sms:recover-inbound', ['messageSid' => self::SID])->assertFailed();
        $this->assertDatabaseCount('sms_messages', 0);
    }

    public static function invalidMessages(): array
    {
        return [
            'outgoing' => [['direction' => 'outbound-api']],
            'wrong account' => [['account_sid' => 'ACdddddddddddddddddddddddddddddddd']],
            'wrong message' => [['sid' => 'SMdddddddddddddddddddddddddddddddd']],
            'another app number' => [['to' => '+19195559999']],
            'redacted' => [['body' => '']],
            'missing timestamp' => [['date_created' => null]],
            'invalid timestamp' => [['date_created' => 'not-a-timestamp']],
            'invalid attachment count' => [['num_media' => '-1']],
        ];
    }

    #[DataProvider('invalidMessages')]
    public function test_unusable_provider_records_are_rejected_without_writes(array $overrides): void
    {
        Http::fake([$this->endpoint().'.json' => Http::response($this->payload($overrides))]);
        $this->artisan('sms:recover-inbound', ['messageSid' => self::SID])->assertFailed();
        $this->assertDatabaseCount('sms_messages', 0);
        $this->assertDatabaseCount('lead_activities', 0);
        Queue::assertNothingPushed();
    }

    public function test_provider_failure_and_invalid_sid_do_not_import_or_send_anything(): void
    {
        Http::fake([$this->endpoint().'.json' => Http::response(['message' => 'Not found'], 404)]);
        $this->artisan('sms:recover-inbound', ['messageSid' => self::SID])->assertFailed();
        $this->artisan('sms:recover-inbound', ['messageSid' => '../invalid'])->assertFailed();
        Http::assertSentCount(1);
        $this->assertDatabaseCount('sms_messages', 0);
        Queue::assertNothingPushed();
    }

    public function test_old_reply_does_not_get_attached_to_a_newer_shared_phone_conversation(): void
    {
        $first = $this->lead();
        $second = $this->lead();
        foreach ([[$first, '2026-10-30 12:00:00'], [$second, '2026-10-30 14:00:00']] as [$lead, $time]) {
            $message = SmsMessage::create(['lead_id' => $lead->id, 'direction' => 'outgoing', 'status' => 'sent',
                'twilio_sid' => 'SM_existing_'.$lead->id, 'from_phone' => '+19844004008', 'to_phone' => '+19843104633', 'body' => 'Hello']);
            $message->forceFill(['created_at' => Carbon::parse($time, 'UTC')->setTimezone(config('app.timezone'))])->save();
        }
        Http::fake([$this->endpoint().'.json' => Http::response($this->payload())]);
        $this->artisan('sms:recover-inbound', ['messageSid' => self::SID])->assertSuccessful();
        $this->assertSame($first->id, SmsMessage::where('twilio_sid', self::SID)->sole()->lead_id);
    }

    private function lead(): Lead
    {
        return Lead::withoutEvents(fn () => Lead::create(['lead_type' => Lead::TYPE_FAMILY, 'name' => 'Test family',
            'phone' => '+19843104633', 'status' => 'new', 'source' => 'manual_crm']));
    }

    private function endpoint(): string
    {
        return 'https://api.twilio.com/2010-04-01/Accounts/'.self::ACCOUNT.'/Messages/'.self::SID;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['sid' => self::SID, 'account_sid' => self::ACCOUNT, 'direction' => 'inbound',
            'status' => 'received', 'from' => '+19843104633', 'to' => '+19844004008',
            'body' => 'Message received during deployment.', 'num_media' => '0',
            'date_created' => 'Fri, 30 Oct 2026 13:25:00 +0000'], $overrides);
    }
}
