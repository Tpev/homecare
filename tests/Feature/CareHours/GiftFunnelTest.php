<?php

namespace Tests\Feature\CareHours;

use App\Jobs\SendGiftOpsNotification;
use App\Models\CareHourAccount;
use App\Models\CareHourGiftEvent;
use App\Models\CareHourPurchase;
use App\Models\User;
use App\Notifications\CareHourVerificationNotification;
use App\Services\CareHours\CareHourGiftService;
use App\Services\CareHours\CareHourPaymentGateway;
use App\Services\CareHours\CareHourPurchaseService;
use App\Services\CareHours\CareHourWebhookService;
use App\Services\CareHours\GiftFunnelRecorder;
use App\Services\CareHours\GiftFunnelReport;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Ops\GiftOpsDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class GiftFunnelTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://hooks.slack.com/services/T000/B000/testing-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['care_hours.enabled' => true, 'care_hours.gifts_enabled' => true, 'care_hours.sales_enabled' => true,
            'services.slack.ops.enabled' => true, 'services.slack.ops.webhook_url' => self::WEBHOOK]);
        Notification::fake();
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake([self::WEBHOOK => Http::response('ok')]);
    }

    public function test_page_loads_and_sessions_are_separate_and_staff_bots_head_and_prefetch_are_excluded(): void
    {
        $visitor = (string) Str::uuid();
        $this->withSession(['care_hours.gift_funnel_visitor' => $visitor])->get('/care-gift-cards')->assertOk();
        $this->get('/care-gift-cards')->assertOk();
        $this->get('/care-hours/gifts')->assertOk();
        $this->head('/care-gift-cards')->assertOk();
        $this->withHeader('User-Agent', 'Googlebot')->get('/care-gift-cards')->assertOk();
        $this->withHeaders(['User-Agent' => 'Browser', 'Sec-Purpose' => 'prefetch'])->get('/care-gift-cards')->assertOk();
        $this->flushHeaders();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/care-gift-cards')->assertOk();
        $this->assertSame(2, CareHourGiftEvent::where('event', 'landing_view')->count());
        $this->assertSame(1, CareHourGiftEvent::where('event', 'landing_view')->distinct()->count('visitor_id'));
        $this->assertSame($visitor, CareHourGiftEvent::where('event', 'shop_view')->value('visitor_id'));
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_verified_http_journey_deduplicates_checkout_and_never_records_secret_codes(): void
    {
        $visitor = (string) Str::uuid();
        $this->withSession(['care_hours.gift_funnel_visitor' => $visitor])->get('/care-hours/gifts')->assertOk();
        $this->post(route('care-hours.gifts.verify'), ['name' => 'Buyer', 'email' => 'invalid'])->assertSessionHasErrors();
        $this->assertSame(0, CareHourGiftEvent::where('event', 'verification_requested')->count());
        $this->post(route('care-hours.gifts.verify'), ['name' => 'Buyer', 'email' => 'private-buyer@example.test', 'hours' => 10])->assertRedirect();
        $this->post(route('care-hours.gifts.confirm'), ['code' => '000000'])->assertSessionHasErrors();
        $this->assertSame(0, CareHourGiftEvent::where('event', 'verification_completed')->count());
        $code = $this->code();
        $this->post(route('care-hours.gifts.confirm'), ['code' => $code])->assertRedirect();
        $payload = ['hours' => 10, 'quotes' => [10 => app(CareHourGiftService::class)->quote(10)['quote_key']], 'request_key' => (string) Str::uuid(), 'terms' => 1];
        $this->post(route('care-hours.gifts.checkout'), $payload)->assertRedirect();
        $this->post(route('care-hours.gifts.checkout'), $payload)->assertRedirect();
        foreach (['verification_requested', 'verification_completed', 'checkout_submitted', 'checkout_started'] as $event) {
            $this->assertSame(1, CareHourGiftEvent::where('event', $event)->count(), $event);
            $this->assertSame($visitor, CareHourGiftEvent::where('event', $event)->value('visitor_id'));
        }
        $this->assertSame(0, CareHourGiftEvent::where('event', 'payment_confirmed')->count());
        Queue::assertPushed(SendGiftOpsNotification::class, 1);
        $json = CareHourGiftEvent::all()->toJson();
        $this->assertStringNotContainsString('private-buyer@example.test', $json);
        $this->assertStringNotContainsString('"code"', $json);
        $this->assertStringNotContainsString('token_hash', $json);
    }

    public function test_webhook_and_reconciliation_record_one_paid_event_and_claim_keeps_the_buyer_identity(): void
    {
        $visitor = (string) Str::uuid();
        [$purchase, $token] = $this->gift($visitor);
        $object = ['id' => $purchase->stripe_checkout_session_id, 'metadata' => ['care_hour_domain' => 'purchase', 'care_hour_purchase_id' => $purchase->public_id]];
        app(CareHourWebhookService::class)->handle('checkout.session.completed', $object, false);
        app(CareHourWebhookService::class)->handle('checkout.session.completed', $object, false);
        $purchase = app(CareHourPurchaseService::class)->refresh($purchase);
        $this->assertSame(1, CareHourGiftEvent::where('event', 'payment_confirmed')->count());
        $owner = User::factory()->create(['role' => 'family']);
        $family = app(FamilyAccountContext::class)->account($owner);
        $account = CareHourAccount::create(['family_account_id' => $family->id, 'display_name' => 'Recipient', 'active' => true, 'currency' => 'usd']);
        $gifts = app(CareHourGiftService::class);
        $code = $gifts->codeForBuyer($purchase, null, $token);
        $quote = $gifts->previewForClaim($code, $account, $owner);
        $gifts->claim($code, $account, $owner, $quote['quote_key']);
        $gifts->claim($code, $account, $owner, $quote['quote_key']);
        $claimed = CareHourGiftEvent::where('event', 'gift_claimed')->sole();
        $this->assertSame($visitor, $claimed->visitor_id);
        $this->assertSame($purchase->id, $claimed->care_hour_purchase_id);
        Queue::assertPushed(SendGiftOpsNotification::class, 3);
        $payload = json_encode(app(GiftOpsDelivery::class)->payload($claimed));
        $this->assertStringContainsString('Gift card claimed', $payload);
        $this->assertStringContainsString($purchase->public_id, $payload);
        $this->assertStringNotContainsString($code, $payload);
        $this->assertStringNotContainsString($purchase->buyer_email, $payload);
    }

    public function test_unpaid_checkout_does_not_count_as_a_purchase_and_card_declines_are_recorded_once(): void
    {
        [$purchase] = $this->gift();
        $gateway = Mockery::mock(CareHourPaymentGateway::class);
        $gateway->shouldReceive('retrieveCheckout')->andReturn([
            'id' => $purchase->stripe_checkout_session_id, 'mode' => 'payment', 'status' => 'open', 'payment_status' => 'unpaid',
            'amount_total' => $purchase->amount_cents, 'currency' => 'usd', 'livemode' => false, 'payment_intent' => null,
            'metadata' => ['care_hour_domain' => 'purchase', 'care_hour_purchase_id' => $purchase->public_id],
        ]);
        $gateway->shouldReceive('financials')->andReturn(['status' => 'requires_payment_method', 'livemode' => false]);
        $this->app->instance(CareHourPaymentGateway::class, $gateway);
        app(CareHourPurchaseService::class)->refresh($purchase);
        $this->assertSame(0, CareHourGiftEvent::where('event', 'payment_confirmed')->count());
        $object = ['id' => 'pi_failed', 'metadata' => ['care_hour_domain' => 'purchase', 'care_hour_purchase_id' => $purchase->public_id]];
        app(CareHourWebhookService::class)->handle('payment_intent.payment_failed', $object, false);
        app(CareHourWebhookService::class)->handle('payment_intent.payment_failed', $object, false);
        $this->assertSame(1, CareHourGiftEvent::where('event', 'payment_failed')->count());
        $this->assertSame('pending', $purchase->fresh()->status);
    }

    public function test_recovered_checkout_is_counted_but_an_old_receipt_is_not_a_new_checkout(): void
    {
        [$purchase] = $this->gift();
        CareHourGiftEvent::where('event', 'checkout_started')->delete();
        app(CareHourPurchaseService::class)->refresh($purchase);
        $this->assertSame(1, CareHourGiftEvent::where('event', 'checkout_started')->count());
        CareHourGiftEvent::where('care_hour_purchase_id', $purchase->id)->delete();
        app(CareHourPurchaseService::class)->refresh($purchase);
        $this->assertSame(0, CareHourGiftEvent::where('care_hour_purchase_id', $purchase->id)->count());
    }

    public function test_notifications_use_existing_transport_once_and_ambiguous_delivery_is_not_replayed(): void
    {
        [$purchase] = $this->gift();
        $event = CareHourGiftEvent::where('event', 'checkout_started')->sole();
        $delivery = app(GiftOpsDelivery::class);
        $delivery->deliver($event->id);
        $delivery->deliver($event->id);
        Http::assertSentCount(1);
        $this->assertSame('sent', $event->fresh()->slack_status);
        app(CareHourPurchaseService::class)->markFailed($purchase);
        $failed = CareHourGiftEvent::where('event', 'payment_failed')->sole();
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake([self::WEBHOOK => fn () => throw new ConnectionException('Provider response lost')]);
        $delivery->deliver($failed->id);
        $delivery->deliver($failed->id);
        $this->assertSame('uncertain', $failed->fresh()->slack_status);
        $this->assertSame(1, $failed->fresh()->slack_attempts);
        $this->artisan('ops:gifts:dispatch --status')->assertSuccessful();
    }

    public function test_rejected_delivery_retries_and_rollback_does_not_queue_an_alert(): void
    {
        [$purchase] = $this->gift();
        $event = CareHourGiftEvent::where('event', 'checkout_started')->sole();
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake([self::WEBHOOK => Http::sequence()->push('rate_limited', 429)->push('ok')]);
        $retried = false;
        try {
            app(GiftOpsDelivery::class)->deliver($event->id);
        } catch (\RuntimeException $exception) {
            $this->assertSame('Gift Ops delivery will retry.', $exception->getMessage());
            $retried = true;
            $this->assertSame('pending', $event->fresh()->slack_status);
        }
        $this->assertTrue($retried, 'A rejected delivery must request a retry.');
        $this->travel(11)->seconds();
        app(GiftOpsDelivery::class)->deliver($event->id);
        $this->assertSame('sent', $event->fresh()->slack_status);
        Queue::fake();
        try {
            DB::transaction(function () use ($purchase): void {
                app(GiftFunnelRecorder::class)->purchase('gift_claimed', $purchase);
                throw new \RuntimeException('Rollback');
            });
        } catch (\RuntimeException) {
            $this->assertSame(0, CareHourGiftEvent::where('event', 'gift_claimed')->count());
        }
        Queue::assertNothingPushed();
    }

    public function test_disabled_slack_and_stalled_delivery_do_not_send_or_blindly_replay(): void
    {
        config(['services.slack.ops.enabled' => false]);
        [$purchase] = $this->gift();
        $event = CareHourGiftEvent::where('event', 'checkout_started')->sole();
        $this->assertSame('disabled', $event->slack_status);
        Queue::assertNothingPushed();
        app(GiftOpsDelivery::class)->deliver($event->id);
        $this->artisan('ops:gifts:dispatch')->assertSuccessful();
        Http::assertNothingSent();
        config(['services.slack.ops.enabled' => true]);
        $event->update(['slack_status' => 'sending', 'sending_at' => now()->subMinutes(6)]);
        $this->artisan('ops:gifts:dispatch')->assertSuccessful();
        $this->assertSame('uncertain', $event->fresh()->slack_status);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_report_handles_paris_dst_and_tracks_cohort_outcomes_after_the_selected_period(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00:00', 'UTC'));
        [$purchase] = $this->gift();
        $purchase->update(['livemode' => true]);
        CareHourGiftEvent::where('care_hour_purchase_id', $purchase->id)->delete();
        $visitor = (string) Str::uuid();
        $other = (string) Str::uuid();
        $this->event('landing_view', $visitor, '2026-10-24 21:59:59');
        $this->event('landing_view', $visitor, '2026-10-24 22:00:00');
        $this->event('landing_view', $visitor, '2026-10-25 22:59:59');
        $this->event('landing_view', $other, '2026-10-25 23:00:00');
        $this->event('shop_view', $visitor, '2026-10-25 10:00:00');
        $this->event('checkout_started', $visitor, '2026-10-26 10:00:00', $purchase);
        $this->event('payment_confirmed', $visitor, '2026-10-26 11:00:00', $purchase);
        $report = app(GiftFunnelReport::class)->forRequest(Request::create('/admin/care-hours/gifts', 'GET', ['funnel_from' => '2026-10-25', 'funnel_to' => '2026-10-25']));
        $rows = collect($report['rows'])->keyBy('event');
        $this->assertSame(2, $rows['landing_view']['events']);
        $this->assertSame(1, $rows['landing_view']['visitors']);
        $this->assertSame(0, $rows['payment_confirmed']['events']);
        $this->assertSame([1, 1, 1, 0], array_column($report['cohort'], 'count'));
        $this->assertSame(100.0, $report['cohort'][2]['from_previous']);
        $purchase->update(['livemode' => false]);
        $testReport = app(GiftFunnelReport::class)->forRequest(Request::create('/', 'GET', ['funnel_from' => '2026-10-25', 'funnel_to' => '2026-10-26']));
        $this->assertSame(1, $testReport['testPurchases']);
        $this->assertSame([1, 0, 0, 0], array_column($testReport['cohort'], 'count'));
    }

    public function test_admin_funnel_access_date_validation_and_missing_migration_fallback(): void
    {
        $this->get(route('admin.care-hours.gifts.index'))->assertRedirect();
        $this->actingAs(User::factory()->create(['role' => 'family']))->get(route('admin.care-hours.gifts.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.care-hours.gifts.index'))->assertOk()
            ->assertSee('Gift-card funnel')->assertSee('N/A')->assertSee('Tracking began');
        $this->get(route('admin.care-hours.gifts.index', ['funnel_from' => '2026-11-20', 'funnel_to' => '2026-11-19']))->assertSessionHasErrors('funnel_to');
        Schema::drop('care_hour_gift_events');
        $this->get(route('admin.care-hours.gifts.index'))->assertOk()->assertSee('Gift analytics are not installed yet');
        $this->app['auth']->forgetGuards();
        $this->get('/care-gift-cards')->assertOk();
    }

    private function gift(?string $visitor = null): array
    {
        $gifts = app(CareHourGiftService::class);
        $verification = $gifts->requestVerification(Str::uuid().'@example.test', 'Private Buyer');
        $token = $gifts->verify($verification->id, $this->code());
        $purchase = $gifts->start($token, 10, $gifts->quote(10)['quote_key'], (string) Str::uuid(), url('/ok'), url('/cancel'), $visitor);

        return [$purchase, $token];
    }

    private function code(): string
    {
        $code = '';
        Notification::assertSentOnDemand(CareHourVerificationNotification::class, function ($notice) use (&$code) {
            $code = $notice->code;

            return true;
        });

        return $code;
    }

    private function event(string $event, string $visitor, string $time, ?CareHourPurchase $purchase = null): void
    {
        CareHourGiftEvent::create(['event' => $event, 'dedupe_key' => hash('sha256', Str::uuid()), 'visitor_id' => $visitor,
            'occurred_at' => $time, 'care_hour_purchase_id' => $purchase?->id, 'amount_cents' => $purchase?->amount_cents, 'currency' => $purchase?->currency]);
    }
}
