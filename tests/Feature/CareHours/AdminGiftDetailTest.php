<?php

namespace Tests\Feature\CareHours;

use App\Models\CareBooking;
use App\Models\CareHourAccount;
use App\Models\CareHourPurchase;
use App\Models\CareRecipient;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\FamilyAccountMember;
use App\Models\User;
use App\Notifications\CareHourVerificationNotification;
use App\Services\CareHours\CareHourGiftService;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\CareHourLedgerService;
use App\Services\CareHours\CareHourPurchaseService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminGiftDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'UTC', 'care_hours.enabled' => true, 'care_hours.sales_enabled' => true,
            'care_hours.enrollment_enabled' => true, 'care_hours.gifts_enabled' => true,
            'care_hours.welcome_enabled' => true, 'care_hours.pilot_only' => false, 'services.stripe.bypass' => true]);
        Notification::fake();
    }

    public function test_detail_is_admin_only_and_rejects_ordinary_purchases(): void
    {
        [$purchase] = $this->gift();
        $url = $this->detailUrl($purchase);
        $this->get($url)->assertRedirect(route('login'));
        foreach (['family', 'caregiver'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get($url)->assertForbidden();
        }
        [$owner, $wallet] = $this->family();
        $ordinary = $this->credit($wallet, $owner, 300);
        $this->actingAs($this->admin())->get($this->detailUrl($ordinary))->assertNotFound();
        $this->get(route('admin.care-hours.gifts.show', (string) Str::uuid()))->assertNotFound();
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_actual_member_claimant_and_exact_time_are_distinct_from_family_owner_without_get_mutations(): void
    {
        [$owner, $wallet] = $this->family();
        $claimant = User::factory()->create(['role' => 'family', 'name' => 'Daniel Bennett', 'email' => 'daniel@example.test']);
        FamilyAccountMember::create(['family_account_id' => $wallet->family_account_id, 'user_id' => $claimant->id,
            'access_level' => 'member', 'status' => 'active', 'joined_at' => now()]);
        [$purchase, $code] = $this->gift(10);
        $this->travelTo(now()->setDate(2026, 10, 30)->setTime(14, 5, 6));
        $purchase = $this->claim($purchase, $code, $wallet, $claimant);
        $claimedAt = $purchase->gift->claimed_at->copy();
        $secrets = [$code, $purchase->gift->code_hash, $purchase->gift->getRawOriginal('encrypted_code'), $purchase->gift->claim_quote_key];
        $before = $this->financialSnapshot();
        config(['care_hours.enabled' => false, 'care_hours.gifts_enabled' => false, 'care_hours.pilot_only' => true]);
        $this->mock(CareHourPurchaseService::class)->shouldNotReceive('refresh');

        $response = $this->actingAs($this->admin())->get($this->detailUrl($purchase))->assertOk()
            ->assertSee('Jane Bennett')->assertSee('jane@example.test')
            ->assertSee('Daniel Bennett')->assertSee('daniel@example.test')->assertSee($owner->name)
            ->assertSee($claimedAt->copy()->timezone('UTC')->format('g:i:s'))->assertSee('UTC')
            ->assertSee('datetime="'.$claimedAt->copy()->timezone('UTC')->toIso8601String().'"', false)
            ->assertHeader('Referrer-Policy', 'no-referrer');
        foreach ($secrets as $secret) {
            $response->assertDontSee($secret, false);
        }
        $response->assertViewHas('detail', function (array $detail) use ($claimant, $owner, $claimedAt): bool {
            $this->assertSame($claimant->id, $detail['claimant']->id);
            $this->assertNotSame($owner->id, $detail['claimant']->id);
            $this->assertTrue($claimedAt->equalTo($detail['purchase']->gift->claimed_at));
            $this->assertSame('UTC', $detail['timezone']);
            $this->assertSame(660, $detail['balance']['issued_minutes']);
            $this->assertSame(660, $detail['balance']['available_minutes']);

            return true;
        });
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame($before, $this->financialSnapshot());
    }

    public function test_unclaimed_gift_shows_no_invented_recipient_or_spendable_credit(): void
    {
        [$purchase, $code] = $this->gift(10);
        $this->actingAs($this->admin())->get($this->detailUrl($purchase))->assertOk()
            ->assertSee('Jane Bennett')->assertSee('Awaiting claim')->assertDontSee($code, false)
            ->assertViewHas('detail', function (array $detail): bool {
                $this->assertNull($detail['claimant']);
                $this->assertNull($detail['purchase']->account);
                $this->assertNull($detail['purchase']->gift->claimed_at);
                $this->assertSame(0, $detail['balance']['issued_minutes']);
                $this->assertSame(0, $detail['balance']['available_minutes']);
                $this->assertSame(0, $detail['visits']->total());

                return true;
            });
        $this->assertDatabaseCount('care_hour_lots', 0);
        $this->assertDatabaseCount('care_hour_accounts', 0);
        $this->assertDatabaseCount('care_recipient_profiles', 0);
    }

    public function test_usage_is_limited_to_this_gift_even_when_visits_mix_several_purchases(): void
    {
        [$owner, $wallet] = $this->family();
        [$purchase, $code] = $this->gift(5);
        $purchase = $this->claim($purchase, $code, $wallet, $owner);
        $this->credit($wallet, $owner, 300);
        $ledger = app(CareHourLedgerService::class);
        $completed = $this->booking($owner, 'Linda Bennett', 'Claire Martin');
        $ledger->reserve($wallet, $completed, 390);
        $ledger->settle($completed, 330);
        $ledger->settle($completed, 180);
        $completed->update(['status' => 'completed', 'worked_minutes' => 180]);
        $upcoming = $this->booking($owner, 'Robert Bennett', 'Helen Taylor');
        $ledger->reserve($wallet, $upcoming, 60);
        $ledger->holdPurchase($purchase, 'Separate another purchase in this test.');
        $unrelated = $this->booking($owner, 'Unrelated visit recipient', 'Unrelated caregiver');
        $ledger->reserve($wallet, $unrelated, 90);
        $ledger->releasePurchaseHold($purchase, 'Restore normal test purchase state.');

        $this->actingAs($this->admin())->get($this->detailUrl($purchase))->assertOk()
            ->assertSee('Linda Bennett')->assertSee('Claire Martin')->assertSee('Robert Bennett')->assertSee('Helen Taylor')
            ->assertDontSee('Unrelated visit recipient')->assertDontSee('Unrelated caregiver')
            ->assertViewHas('detail', function (array $detail) use ($completed, $upcoming): bool {
                $this->assertSame(['issued_minutes' => 300, 'available_minutes' => 60, 'reserved_minutes' => 60,
                    'used_minutes' => 180, 'held_minutes' => 0, 'reversed_minutes' => 0], $detail['balance']);
                $this->assertSame(2, $detail['visits']->total());
                $visits = collect($detail['visits']->items())->keyBy('booking_id');
                $this->assertSame(180, $visits[$completed->id]['used_minutes']);
                $this->assertSame(120, $visits[$completed->id]['returned_minutes']);
                $this->assertSame(0, $visits[$completed->id]['reserved_minutes']);
                $this->assertSame(60, $visits[$upcoming->id]['reserved_minutes']);

                return true;
            });
        $this->assertSame(270, $wallet->balance()['available_minutes']);
    }

    public function test_cancel_rebook_and_time_correction_keep_historical_returns_separate_from_remaining_hours(): void
    {
        [$owner, $wallet] = $this->family();
        [$purchase, $code] = $this->gift(5);
        $purchase = $this->claim($purchase, $code, $wallet, $owner);
        $booking = $this->booking($owner);
        $ledger = app(CareHourLedgerService::class);
        $ledger->reserve($wallet, $booking, 120);
        $ledger->release($booking);
        $ledger->release($booking);
        $ledger->reserve($wallet, $booking, 120);
        $ledger->settle($booking, 60);

        $this->actingAs($this->admin())->get($this->detailUrl($purchase))->assertOk()
            ->assertViewHas('detail', function (array $detail): bool {
                $this->assertSame(240, $detail['balance']['available_minutes']);
                $this->assertSame(60, $detail['balance']['used_minutes']);
                $this->assertSame(0, $detail['balance']['reserved_minutes']);
                $this->assertSame(1, $detail['visits']->total());
                $this->assertSame(180, $detail['visits']->first()['returned_minutes']);
                $this->assertSame(60, $detail['visits']->first()['used_minutes']);

                return true;
            });
    }

    public static function unavailableCredit(): array
    {
        return ['held purchase' => ['held', 300, 0], 'inactive source' => ['inactive', 300, 0], 'reversed purchase' => ['reversed', 0, 300]];
    }

    #[DataProvider('unavailableCredit')]
    public function test_unavailable_gift_credit_never_appears_spendable(string $state, int $held, int $reversed): void
    {
        [$owner, $wallet] = $this->family();
        [$purchase, $code] = $this->gift(5);
        $purchase = $this->claim($purchase, $code, $wallet, $owner);
        if ($state === 'held') {
            app(CareHourLedgerService::class)->holdPurchase($purchase, 'Provider dispute needs review.');
        } elseif ($state === 'reversed') {
            app(CareHourLedgerService::class)->reversePurchase($purchase, 'Confirmed payment reversal.');
        } else {
            $wallet->update(['active' => false]);
        }
        $this->actingAs($this->admin())->get($this->detailUrl($purchase))->assertOk()
            ->assertViewHas('detail', function (array $detail) use ($held, $reversed): bool {
                $this->assertSame(300, $detail['balance']['issued_minutes']);
                $this->assertSame(0, $detail['balance']['available_minutes']);
                $this->assertSame($held, $detail['balance']['held_minutes']);
                $this->assertSame($reversed, $detail['balance']['reversed_minutes']);

                return true;
            });
    }

    public function test_all_funded_visits_remain_reachable_across_pages_without_changing_the_gift_totals(): void
    {
        [$owner, $wallet] = $this->family();
        [$purchase, $code] = $this->gift(10);
        $purchase = $this->claim($purchase, $code, $wallet, $owner);
        $ledger = app(CareHourLedgerService::class);
        $expected = [];
        for ($index = 1; $index <= 17; $index++) {
            $booking = $this->booking($owner, 'Care recipient '.$index);
            $ledger->reserve($wallet, $booking, 5);
            $ledger->settle($booking, 5);
            $expected[] = $booking->id;
        }
        $this->actingAs($this->admin());
        $seen = [];
        $url = $this->detailUrl($purchase);
        do {
            $response = $this->get($url)->assertOk();
            $detail = $response->viewData('detail');
            $this->assertSame(17, $detail['visits']->total());
            $this->assertSame(85, $detail['balance']['used_minutes']);
            $this->assertSame(575, $detail['balance']['available_minutes']);
            foreach ($detail['visits'] as $visit) {
                $seen[] = $visit['booking_id'];
                $this->assertSame(5, $visit['used_minutes']);
            }
            $url = $detail['visits']->nextPageUrl();
        } while ($url !== null);
        sort($expected);
        sort($seen);
        $this->assertSame($expected, $seen);
        $this->get(route('admin.care-hours.gifts.index'))->assertOk()->assertSee($this->detailUrl($purchase), false);
    }

    public function test_missing_historical_claimant_is_not_replaced_with_the_current_family_owner(): void
    {
        [$owner, $wallet] = $this->family();
        [$purchase, $code] = $this->gift(5);
        $purchase = $this->claim($purchase, $code, $wallet, $owner);
        $claimedAt = $purchase->gift->claimed_at;
        $purchase->gift->update(['claimed_by_user_id' => null]);
        $this->actingAs($this->admin())->get($this->detailUrl($purchase))->assertOk()
            ->assertViewHas('detail', function (array $detail) use ($claimedAt): bool {
                $this->assertNull($detail['claimant']);
                $this->assertTrue($claimedAt->equalTo($detail['purchase']->gift->claimed_at));
                $this->assertSame(300, $detail['balance']['available_minutes']);

                return true;
            });
    }

    private function detailUrl(CareHourPurchase $purchase): string
    {
        return route('admin.care-hours.gifts.show', $purchase->public_id);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function family(): array
    {
        $owner = User::factory()->create(['role' => 'family', 'name' => 'Emma Bennett', 'email' => 'emma@example.test']);
        $wallet = app(CareHourIdentityService::class)->ensureFamilyWallet($owner);

        return [$owner, $wallet];
    }

    private function gift(int $hours = 5): array
    {
        $gifts = app(CareHourGiftService::class);
        $verification = $gifts->requestVerification('jane@example.test', 'Jane Bennett');
        $otp = '';
        Notification::assertSentOnDemand(CareHourVerificationNotification::class, function ($notice) use (&$otp) {
            $otp = $notice->code;

            return true;
        });
        $token = $gifts->verify($verification->id, $otp);
        $purchase = $gifts->start($token, $hours, $gifts->quote($hours)['quote_key'], (string) Str::uuid(), url('/ok'), url('/cancel'));
        $purchase = app(CareHourPurchaseService::class)->refresh($purchase);

        return [$purchase, $gifts->codeForBuyer($purchase, null, $token)];
    }

    private function claim(CareHourPurchase $purchase, string $code, CareHourAccount $wallet, User $claimant): CareHourPurchase
    {
        $gifts = app(CareHourGiftService::class);
        $gifts->claim($code, $wallet, $claimant, $gifts->previewForClaim($code, $wallet, $claimant)['quote_key']);

        return $purchase->fresh(['gift']);
    }

    private function credit(CareHourAccount $wallet, User $owner, int $minutes): CareHourPurchase
    {
        $purchase = CareHourPurchase::create(['public_id' => (string) Str::uuid(), 'request_key' => (string) Str::uuid(),
            'care_hour_account_id' => $wallet->id, 'family_account_id' => $wallet->family_account_id,
            'buyer_user_id' => $owner->id, 'buyer_email' => $owner->email, 'buyer_name' => $owner->name,
            'paid_minutes' => $minutes, 'bonus_minutes' => 0, 'total_minutes' => $minutes,
            'amount_cents' => (int) ($minutes * 3100 / 60), 'currency' => 'usd', 'status' => 'paid',
            'processing_fee_cents' => 0, 'terms_version' => 'test', 'quote' => [], 'terms_accepted_at' => now(),
            'success_url' => '/ok', 'cancel_url' => '/cancel']);
        app(CareHourLedgerService::class)->issuePurchase($purchase);

        return $purchase;
    }

    private function booking(User $owner, string $recipient = 'Linda Bennett', string $caregiverName = 'Claire Martin'): CareBooking
    {
        $family = app(FamilyAccountContext::class)->account($owner);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => $caregiverName]);
        $request = CareRequest::create(['family_account_id' => $family->id, 'family_user_id' => $owner->id,
            'title' => 'Care for '.$recipient, 'status' => 'filled', 'address_line1' => '123 Test St',
            'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601', 'request_type' => 'one_time',
            'requested_start_at' => now()->addDay(), 'requested_end_at' => now()->addDay()->addHours(2)]);
        CareRecipient::create(['care_request_id' => $request->id, 'full_name' => $recipient, 'relationship_to_family' => 'Parent']);
        $application = CareRequestApplication::create(['care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id, 'status' => 'hired', 'proposed_rate' => 30]);

        return CareBooking::create(['care_request_id' => $request->id, 'care_request_application_id' => $application->id,
            'family_account_id' => $family->id, 'family_user_id' => $owner->id, 'caregiver_user_id' => $caregiver->id,
            'status' => 'scheduled', 'scheduled_start_at' => $request->requested_start_at,
            'scheduled_end_at' => $request->requested_end_at, 'expected_minutes' => 120]);
    }

    private function financialSnapshot(): array
    {
        $snapshot = [];
        foreach (['care_hour_accounts', 'care_hour_purchases', 'care_hour_gifts', 'care_hour_lots', 'care_hour_allocations',
            'care_hour_ledger_entries', 'care_hour_gift_admin_checks', 'care_booking_payments'] as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $snapshot;
    }
}
