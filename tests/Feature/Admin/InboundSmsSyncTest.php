<?php

namespace Tests\Feature\Admin;

use App\Models\Lead;
use App\Models\SmsMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InboundSmsSyncTest extends TestCase
{
    use RefreshDatabase;

    private const ACCOUNT = 'ACbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private const EXISTING = 'SMaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const MISSING = 'SMcccccccccccccccccccccccccccccccc';

    private const SECOND = 'SMdddddddddddddddddddddddddddddddd';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-30 22:00:00', 'UTC'));
        config(['services.twilio.bypass' => false, 'services.twilio.account_sid' => self::ACCOUNT,
            'services.twilio.auth_token' => 'test-token', 'services.twilio.sms_from' => '+19844004008']);
        Http::preventStrayRequests();
        Mail::fake();
        Queue::fake();
    }

    public function test_sync_recovers_paginated_messages_once_with_original_time_and_no_outbound_activity(): void
    {
        $lead = Lead::withoutEvents(fn () => Lead::create(['lead_type' => Lead::TYPE_FAMILY,
            'name' => 'Test family', 'phone' => '+19843104633', 'status' => 'new', 'source' => 'manual_crm']));
        SmsMessage::create(['twilio_sid' => self::EXISTING, 'twilio_account_sid' => self::ACCOUNT,
            'direction' => 'incoming', 'status' => 'received', 'body' => 'Keep this body',
            'from_phone' => '+19843104633', 'to_phone' => '+19844004008']);
        $next = '/2010-04-01/Accounts/'.self::ACCOUNT.'/Messages.json?PageToken=next';
        Http::fake([
            $this->endpoint().'?PageToken=next' => Http::response(['messages' => [
                $this->payload(self::MISSING), $this->payload(self::SECOND),
            ], 'next_page_uri' => null]),
            $this->endpoint().'?*' => Http::response(['messages' => [
                $this->payload(self::EXISTING), $this->payload(self::MISSING),
                $this->payload(self::SECOND, ['direction' => 'outbound-api']),
                $this->payload(self::SECOND, ['to' => '+19195559999']),
                $this->payload(self::SECOND, ['date_created' => 'Mon, 19 Oct 2026 13:25:00 +0000']),
            ], 'next_page_uri' => $next]),
            $this->endpoint(self::MISSING) => Http::response($this->payload(self::MISSING)),
            $this->endpoint(self::SECOND) => Http::response($this->payload(self::SECOND)),
        ]);

        $this->artisan('sms:sync-inbound', ['--days' => 7])
            ->expectsOutputToContain('2 recovered, 1 already present, 0 errors.')->assertSuccessful();
        $message = SmsMessage::where('twilio_sid', self::MISSING)->sole();
        $this->assertSame($lead->id, $message->lead_id);
        $this->assertSame('2026-10-30 13:25:00', $message->received_at->utc()->format('Y-m-d H:i:s'));
        $this->assertTrue($message->created_at->equalTo($message->received_at));
        $this->assertSame('Keep this body', SmsMessage::where('twilio_sid', self::EXISTING)->sole()->body);
        $this->assertDatabaseCount('lead_activities', 2);
        Http::assertSent(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['To'] ?? null) === '+19844004008'
                && ($query['DateSent>='] ?? null) === '2026-10-23' && (int) ($query['PageSize'] ?? 0) === 100;
        });
        Http::assertSentCount(4);

        $this->artisan('sms:sync-inbound')->expectsOutputToContain('0 recovered, 3 already present, 0 errors.')->assertSuccessful();
        Http::assertSentCount(6);
        $this->assertDatabaseCount('sms_messages', 3);
        $this->assertDatabaseCount('lead_activities', 2);
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
    }

    public function test_dry_run_lists_missing_sids_without_fetching_details_or_writing(): void
    {
        Http::fake([$this->endpoint().'?*' => Http::response(['messages' => [$this->payload(self::MISSING)], 'next_page_uri' => null])]);
        $this->artisan('sms:sync-inbound', ['--dry-run' => true])
            ->expectsOutputToContain('Would recover: '.self::MISSING)
            ->expectsOutputToContain('1 missing, 0 already present, 0 errors. No messages imported.')->assertSuccessful();
        $this->assertDatabaseCount('sms_messages', 0);
        Http::assertSentCount(1);
        Queue::assertNothingPushed();
    }

    public function test_one_failed_message_does_not_prevent_other_messages_from_being_recovered(): void
    {
        Http::fake([
            $this->endpoint().'?*' => Http::response(['messages' => [$this->payload(self::MISSING), $this->payload(self::SECOND)], 'next_page_uri' => null]),
            $this->endpoint(self::MISSING) => Http::response(['message' => 'Unavailable'], 503),
            $this->endpoint(self::SECOND) => Http::response($this->payload(self::SECOND)),
        ]);
        $this->artisan('sms:sync-inbound')->expectsOutputToContain('1 recovered, 0 already present, 1 errors.')->assertFailed();
        $this->assertSame(self::SECOND, SmsMessage::sole()->twilio_sid);
        Queue::assertNothingPushed();
    }

    public static function unsafeNextPages(): array
    {
        return [
            ['https://untrusted.example/messages'],
            ['//untrusted.example/messages'],
            ['/2010-04-01/Accounts/ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/Messages.json?PageToken=x'],
        ];
    }

    #[DataProvider('unsafeNextPages')]
    public function test_unexpected_pagination_urls_are_rejected_without_sending_credentials(string $next): void
    {
        Http::fake([$this->endpoint().'?*' => Http::response(['messages' => [], 'next_page_uri' => $next])]);
        $this->artisan('sms:sync-inbound')->expectsOutputToContain('unexpected pagination URL')->assertFailed();
        Http::assertSentCount(1);
        $this->assertDatabaseCount('sms_messages', 0);
    }

    public function test_page_limit_and_provider_failures_are_reported_as_incomplete(): void
    {
        Http::fakeSequence()->push(['messages' => [], 'next_page_uri' => '/2010-04-01/Accounts/'.self::ACCOUNT.'/Messages.json?PageToken=next'])
            ->push(['message' => 'Unavailable'], 503);
        $this->artisan('sms:sync-inbound', ['--max-pages' => 1])->expectsOutputToContain('page limit was reached')->assertFailed();
        $this->artisan('sms:sync-inbound')->expectsOutputToContain('Sync incomplete:')->assertFailed();
        Http::assertSentCount(2);
        $this->assertDatabaseCount('sms_messages', 0);
    }

    public function test_repeated_page_is_detected_and_does_not_loop(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['messages' => [],
            'next_page_uri' => '/2010-04-01/Accounts/'.self::ACCOUNT.'/Messages.json?PageToken=loop'])]);
        $this->artisan('sms:sync-inbound')->expectsOutputToContain('repeated a message page')->assertFailed();
        Http::assertSentCount(2);
    }

    public static function invalidListings(): array
    {
        return [
            [['account_sid' => 'ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']],
            [['date_created' => 'not-a-timestamp']],
            [['date_created' => null]],
            [['sid' => '../invalid']],
        ];
    }

    #[DataProvider('invalidListings')]
    public function test_invalid_list_records_fail_without_importing(array $overrides): void
    {
        Http::fake([$this->endpoint().'?*' => Http::response(['messages' => [$this->payload(self::MISSING, $overrides)], 'next_page_uri' => null])]);
        $this->artisan('sms:sync-inbound')->assertFailed();
        Http::assertSentCount(1);
        $this->assertDatabaseCount('sms_messages', 0);
    }

    public function test_invalid_bounds_or_missing_credentials_do_not_call_twilio(): void
    {
        Http::fake();
        foreach ([['--days' => 0], ['--days' => 31], ['--max-pages' => 0], ['--max-pages' => 101]] as $options) {
            $this->artisan('sms:sync-inbound', $options)->assertFailed();
        }
        config(['services.twilio.auth_token' => '']);
        $this->artisan('sms:sync-inbound')->assertFailed();
        Http::assertNothingSent();
    }

    private function endpoint(?string $sid = null): string
    {
        return 'https://api.twilio.com/2010-04-01/Accounts/'.self::ACCOUNT.'/Messages'.($sid ? '/'.$sid : '').'.json';
    }

    private function payload(string $sid, array $overrides = []): array
    {
        return array_merge(['sid' => $sid, 'account_sid' => self::ACCOUNT, 'direction' => 'inbound',
            'status' => 'received', 'from' => '+19843104633', 'to' => '+19844004008',
            'body' => 'A missed reply. ', 'num_media' => '0', 'date_created' => 'Fri, 30 Oct 2026 13:25:00 +0000'], $overrides);
    }
}
