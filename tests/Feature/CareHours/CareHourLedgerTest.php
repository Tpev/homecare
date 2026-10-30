<?php

namespace Tests\Feature\CareHours;

use App\Models\CareBooking;
use App\Models\CareHourAccount;
use App\Models\CareHourAllocation;
use App\Models\CareHourLedgerEntry;
use App\Models\CareHourPurchase;
use App\Models\CareRecipient;
use App\Models\CareRecipientProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\CareHourLedgerService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CareHourLedgerTest extends TestCase
{
    use RefreshDatabase;

    private CareHourLedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        config(['care_hours.enabled' => true]);
        $this->ledger = app(CareHourLedgerService::class);
    }

    public function test_verified_purchase_issues_once_and_pending_payment_never_issues(): void
    {
        [$account] = $this->account();
        $purchase = $this->purchase($account, 600, 60, 1100);
        $this->ledger->issuePurchase($purchase);
        $this->ledger->issuePurchase($purchase);
        $this->assertSame(660, $this->ledger->balance($account)['available_minutes']);
        $this->assertDatabaseCount('care_hour_lots', 1);
        $this->assertDatabaseCount('care_hour_ledger_entries', 1);
        $pending = $this->purchase($account, 300, 0, null, 'pending');
        $this->expectException(ValidationException::class);
        $this->ledger->issuePurchase($pending);
    }

    public function test_two_bookings_cannot_reserve_the_same_minutes(): void
    {
        [$account, $owner] = $this->account();
        $this->credit($account, 180);
        $first = $this->booking($owner);
        $second = $this->booking($owner);
        $this->assertSame(120, $this->ledger->reserve($account, $first, 120));
        $this->assertSame(60, $this->ledger->reserve($account, $second, 120));
        $this->assertSame(60, $this->ledger->reserve($account, $second, 120));
        $this->assertSame(['available_minutes' => 0, 'reserved_minutes' => 180, 'used_minutes' => 0, 'held_minutes' => 0], $this->ledger->balance($account));
    }

    public function test_cancellation_restores_all_paid_and_bonus_hours_without_a_fee(): void
    {
        [$account, $owner] = $this->account();
        $this->credit($account, 600, 60, 1100);
        $booking = $this->booking($owner);
        $this->ledger->reserve($account, $booking, 660);
        $this->ledger->release($booking);
        $this->ledger->release($booking);
        $this->assertSame(660, $this->ledger->balance($account)['available_minutes']);
        $this->assertSame(0, $this->ledger->balance($account)['used_minutes']);
        $this->assertSame(1, CareHourLedgerEntry::where('type', 'release')->count());
        $this->assertConserved($account);
    }

    public function test_approval_consumes_actual_time_and_releases_surplus_once(): void
    {
        [$account, $owner] = $this->account();
        $this->credit($account, 600, 60, 1100);
        $booking = $this->booking($owner);
        $this->ledger->reserve($account, $booking, 180);
        $result = $this->ledger->settle($booking, 150);
        $this->assertSame(150, $result['used_minutes']);
        $this->assertSame(250, $result['processing_fee_cents']);
        $this->assertSame(510, $this->ledger->balance($account)['available_minutes']);
        $entries = CareHourLedgerEntry::count();
        $this->ledger->settle($booking, 150);
        $this->assertSame($entries, CareHourLedgerEntry::count());
        $this->assertConserved($account);
    }

    public function test_fee_rounding_corrections_and_reuse_recover_exactly_the_original_fee(): void
    {
        [$account, $owner] = $this->account();
        $this->credit($account, 600, 60, 937);
        $a = $this->booking($owner);
        $b = $this->booking($owner);
        $this->ledger->reserve($account, $a, 127);
        $this->ledger->settle($a, 127);
        $corrected = $this->ledger->settle($a, 61);
        $this->ledger->reserve($account, $b, 599);
        $rest = $this->ledger->settle($b, 599);
        $this->assertSame(937, $corrected['processing_fee_cents'] + $rest['processing_fee_cents']);
        $this->assertSame(660, $this->ledger->balance($account)['used_minutes']);
        $ranges = CareHourAllocation::where('status', 'used')->orderBy('start_minute')->get();
        $this->assertSame([0, 61], $ranges->pluck('start_minute')->all());
        $this->assertConserved($account);
    }

    public function test_unknown_actual_fee_is_not_treated_as_zero(): void
    {
        [$account, $owner] = $this->account();
        $purchase = $this->credit($account, 600, 60, null);
        $booking = $this->booking($owner);
        $this->ledger->reserve($account, $booking, 120);
        $this->assertNull($this->ledger->settle($booking, 120)['processing_fee_cents']);
        $this->ledger->updatePurchaseFee($purchase, 1100);
        $this->assertSame(200, $this->ledger->settle($booking, 120)['processing_fee_cents']);
    }

    public function test_holds_isolate_the_affected_purchase_and_restore_its_exact_hours(): void
    {
        [$account, $owner] = $this->account();
        $a = $this->credit($account, 300);
        $this->credit($account, 600);
        $booking = $this->booking($owner);
        $this->ledger->reserve($account, $booking, 60);
        $this->ledger->holdPurchase($a, 'Payment under review');
        $this->assertSame(['available_minutes' => 600, 'reserved_minutes' => 60, 'used_minutes' => 0, 'held_minutes' => 240], $this->ledger->balance($account));
        $this->ledger->release($booking);
        $this->assertSame(300, $this->ledger->balance($account)['held_minutes']);
        $this->ledger->releasePurchaseHold($a, 'Payment confirmed');
        $this->assertSame(900, $this->ledger->balance($account)['available_minutes']);
        $this->assertConserved($account);
    }

    public function test_used_purchase_cannot_be_reversed_and_erase_delivered_care(): void
    {
        [$account, $owner] = $this->account();
        $purchase = $this->credit($account, 300);
        $booking = $this->booking($owner);
        $this->ledger->reserve($account, $booking, 60);
        $this->ledger->settle($booking, 60);
        $this->expectException(ValidationException::class);
        $this->ledger->reversePurchase($purchase, 'Payment reversed by provider');
    }

    public function test_fee_allocation_uses_original_denominator_across_all_purchases(): void
    {
        [$account, $owner] = $this->account();
        $this->credit($account, 600, 60, 1100);
        $this->credit($account, 1200, 0, 1200);
        $booking = $this->booking($owner);
        $this->ledger->reserve($account, $booking, 720);
        $result = $this->ledger->settle($booking, 720);
        $this->assertSame(1160, $result['processing_fee_cents']);
        $this->assertSame(1140, $this->ledger->balance($account)['available_minutes']);
    }

    public function test_pausing_sales_and_future_price_changes_do_not_reduce_entitlements(): void
    {
        [$account, $owner] = $this->account();
        $this->credit($account, 600, 60, 1100);
        config(['care_hours.enabled' => false, 'care_hours.sales_enabled' => false, 'marketplace.family_hourly_rate' => 99]);
        $booking = $this->booking($owner);
        $this->assertSame(120, $this->ledger->reserve($account, $booking, 120));
        $this->assertSame(120, $this->ledger->settle($booking, 120)['used_minutes']);
        $this->assertSame(540, $this->ledger->balance($account)['available_minutes']);
    }

    public function test_another_family_cannot_use_the_account(): void
    {
        [$account] = $this->account();
        [, $other] = $this->account();
        $this->credit($account, 300);
        $this->expectException(ValidationException::class);
        $this->ledger->reserve($account, $this->booking($other), 60);
    }

    public function test_existing_optional_profile_reuses_one_account_without_requiring_profile_completion(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $family = app(FamilyAccountContext::class)->account($owner);
        $profile = CareRecipientProfile::create(['family_account_id' => $family->id, 'full_name' => 'Margaret', 'status' => 'draft']);
        $identity = app(CareHourIdentityService::class);
        $account = $identity->enrollProfile($profile, $owner);
        $booking = $this->booking($owner);
        $recipient = CareRecipient::create(['care_request_id' => $booking->care_request_id, 'full_name' => 'Margaret', 'relationship_to_family' => 'Mother', 'care_recipient_profile_id' => $profile->id]);
        $this->assertSame($account->id, $identity->enroll($recipient, $owner)->id);
        $this->assertSame($account->id, $identity->forBooking($booking->fresh())->id);
        $this->assertDatabaseCount('care_hour_accounts', 1);
    }

    public function test_history_cannot_be_silently_overwritten(): void
    {
        [$account] = $this->account();
        $this->credit($account, 300);
        $this->expectException(\LogicException::class);
        CareHourLedgerEntry::first()->update(['available_delta' => 999]);
    }

    public function test_fresh_enrollment_can_be_used_immediately_and_later_profile_keeps_the_same_balance(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $booking = $this->booking($owner);
        $recipient = CareRecipient::create(['care_request_id' => $booking->care_request_id,
            'full_name' => 'Margaret', 'relationship_to_family' => 'Mother']);
        $identity = app(CareHourIdentityService::class);
        $account = $identity->enroll($recipient, $owner);
        $this->assertTrue($account->active);
        $this->credit($account, 600, 60, 1100);

        $profile = CareRecipientProfile::create(['family_account_id' => $account->family_account_id,
            'full_name' => 'Margaret', 'status' => 'draft']);
        $recipient->update(['care_recipient_profile_id' => $profile->id]);
        $reused = $identity->enrollProfile($profile, $owner);
        $this->assertSame($account->id, $reused->id);
        $this->assertSame(660, $reused->balance()['available_minutes']);
        $this->assertDatabaseCount('care_hour_accounts', 1);
        $this->assertSame($account->id, $identity->forBooking($booking->fresh())->id);
    }

    public function test_family_wallet_shares_credit_without_merging_recipient_records(): void
    {
        [$account, $owner] = $this->account();
        $profile = CareRecipientProfile::create(['family_account_id' => $account->family_account_id,
            'full_name' => 'Margaret', 'status' => 'draft']);
        $identity = app(CareHourIdentityService::class);
        $other = $identity->enrollProfile($profile, $owner);
        $this->assertSame($account->id, $other->id);
        $booking = $this->booking($owner);
        $recipient = CareRecipient::create(['care_request_id' => $booking->care_request_id,
            'full_name' => 'Margaret', 'relationship_to_family' => 'Mother', 'care_recipient_profile_id' => $profile->id]);
        $identity->linkRecipient($account, $recipient, $owner);
        $this->assertSame($profile->id, $recipient->fresh()->care_recipient_profile_id);
        $this->assertNull($account->fresh()->care_recipient_profile_id);
        $this->assertDatabaseCount('care_hour_accounts', 1);
        $this->assertDatabaseCount('care_hour_recipient_links', 0);
    }

    private function account(): array
    {
        $owner = User::factory()->create(['role' => 'family']);
        $family = app(FamilyAccountContext::class)->account($owner);
        $account = CareHourAccount::create(['family_account_id' => $family->id, 'display_name' => 'Margaret']);

        return [$account, $owner];
    }

    private function purchase(CareHourAccount $account, int $paid, int $bonus, ?int $fee, string $status = 'paid'): CareHourPurchase
    {
        return CareHourPurchase::create([
            'public_id' => (string) Str::uuid(), 'request_key' => (string) Str::uuid(),
            'care_hour_account_id' => $account->id, 'family_account_id' => $account->family_account_id,
            'buyer_user_id' => $account->familyAccount->owner_user_id, 'buyer_email' => 'buyer@example.test', 'buyer_name' => 'Family',
            'paid_minutes' => $paid, 'bonus_minutes' => $bonus, 'total_minutes' => $paid + $bonus,
            'amount_cents' => (int) round($paid * 3100 / 60), 'currency' => 'usd', 'status' => $status,
            'processing_fee_cents' => $fee, 'terms_version' => 'test', 'quote' => [], 'terms_accepted_at' => now(),
            'success_url' => '/care-hours', 'cancel_url' => '/care-hours',
        ]);
    }

    private function credit(CareHourAccount $account, int $paid, int $bonus = 0, ?int $fee = 0): CareHourPurchase
    {
        $purchase = $this->purchase($account, $paid, $bonus, $fee);
        $this->ledger->issuePurchase($purchase);

        return $purchase;
    }

    private function booking(User $owner): CareBooking
    {
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $request = CareRequest::create(['family_user_id' => $owner->id, 'title' => 'Care', 'status' => 'filled',
            'address_line1' => '123 Test St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
            'request_type' => 'one_time', 'requested_start_at' => now()->addDay(), 'requested_end_at' => now()->addDay()->addHours(2)]);
        $application = CareRequestApplication::create(['care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id, 'status' => 'hired', 'proposed_rate' => 27]);

        return CareBooking::create(['care_request_id' => $request->id, 'care_request_application_id' => $application->id,
            'family_user_id' => $owner->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'scheduled',
            'scheduled_start_at' => $request->requested_start_at, 'scheduled_end_at' => $request->requested_end_at, 'expected_minutes' => 120]);
    }

    private function assertConserved(CareHourAccount $account): void
    {
        $balance = $this->ledger->balance($account);
        foreach (['available', 'reserved', 'used', 'held'] as $bucket) {
            $this->assertSame($balance[$bucket.'_minutes'], (int) $account->ledgerEntries()->sum($bucket.'_delta'));
        }
    }
}
