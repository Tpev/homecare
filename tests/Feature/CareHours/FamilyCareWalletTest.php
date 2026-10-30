<?php

namespace Tests\Feature\CareHours;

use App\Models\CareBooking;
use App\Models\CareHourAccount;
use App\Models\CareHourAllocation;
use App\Models\CareHourBookingFunding;
use App\Models\CareHourGift;
use App\Models\CareHourLedgerEntry;
use App\Models\CareHourPaymentOperation;
use App\Models\CareHourPurchase;
use App\Models\CareRecipient;
use App\Models\CareRecipientProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\CareHourLedgerService;
use App\Services\CareHours\PrepaidBookingFundingService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FamilyCareWalletTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['care_hours.enabled' => true, 'care_hours.enrollment_enabled' => true, 'care_hours.pilot_only' => false]);
    }

    public function test_family_wallet_reads_never_enroll_and_first_purchase_needs_no_recipient(): void
    {
        $owner = $this->owner();
        $identity = app(CareHourIdentityService::class);
        $this->assertNull($identity->walletFor($owner));
        $this->assertCount(0, $identity->accountsFor($owner));
        $this->assertDatabaseCount('care_hour_accounts', 0);
        $this->assertDatabaseCount('care_hour_family_wallets', 0);

        $wallet = $identity->ensureFamilyWallet($owner);
        $this->assertSame($wallet->id, $identity->ensureFamilyWallet($owner)->id);
        $this->assertSame([$wallet->id], $identity->accountsFor($owner)->modelKeys());
        $this->assertNull($wallet->care_recipient_profile_id);
        $this->assertDatabaseCount('care_hour_accounts', 1);
        $this->assertDatabaseCount('care_hour_family_wallets', 1);
        $this->assertDatabaseCount('care_recipient_profiles', 0);
        $this->assertDatabaseCount('care_hour_recipient_links', 0);
    }

    public function test_legacy_accounts_are_grouped_without_repointing_any_original_money_records(): void
    {
        $owner = $this->owner();
        $a = $this->source($owner, 'Colette');
        $b = $this->source($owner, 'Old care request');
        $pa = $this->credit($a, 600, 60, 1100);
        $pb = $this->credit($b, 300, 0, 300);
        $before = DB::table('care_hour_ledger_entries')->orderBy('id')->get()->toJson();
        $wallet = app(CareHourIdentityService::class)->ensureFamilyWallet($owner);

        $this->assertSame($a->id, $wallet->id);
        $this->assertSame([$a->id, $b->id], $wallet->familySourceIds());
        $this->assertSame(960, $wallet->balance()['available_minutes']);
        $this->assertSame($a->id, $pa->fresh()->care_hour_account_id);
        $this->assertSame($b->id, $pb->fresh()->care_hour_account_id);
        $this->assertSame([$a->id, $b->id], DB::table('care_hour_lots')->orderBy('id')->pluck('care_hour_account_id')->all());
        $this->assertSame($before, DB::table('care_hour_ledger_entries')->orderBy('id')->get()->toJson());
        $this->assertCount(2, app(CareHourLedgerService::class)->history($wallet)->get());
        $this->assertDatabaseCount('care_hour_accounts', 2);
    }

    public function test_profiles_and_old_requests_reuse_wallet_without_merging_person_records(): void
    {
        $owner = $this->owner();
        $identity = app(CareHourIdentityService::class);
        $wallet = $identity->ensureFamilyWallet($owner);
        $profile = CareRecipientProfile::create(['family_account_id' => $wallet->family_account_id, 'full_name' => 'Another person', 'status' => 'draft']);
        $booking = $this->booking($owner);
        $recipient = CareRecipient::create(['care_request_id' => $booking->care_request_id, 'full_name' => 'Colette', 'relationship_to_family' => 'Mother']);

        $this->assertSame($wallet->id, $identity->enrollProfile($profile, $owner)->id);
        $this->assertSame($wallet->id, $identity->enroll($recipient, $owner)->id);
        $this->assertSame($wallet->id, $identity->forBooking($booking)->id);
        $this->assertNull($wallet->fresh()->care_recipient_profile_id);
        $this->assertDatabaseCount('care_hour_recipient_links', 0);
        $this->assertSame('Another person', $profile->fresh()->full_name);
        $this->assertSame('Colette', $recipient->fresh()->full_name);
    }

    public function test_committed_booking_keeps_historical_source_identity_after_wallet_setup(): void
    {
        $owner = $this->owner();
        $a = $this->source($owner, 'First source');
        $b = $this->source($owner, 'Second source');
        $booking = $this->booking($owner);
        $funding = CareHourBookingFunding::create(['care_booking_id' => $booking->id, 'care_hour_account_id' => $b->id,
            'selected_by_user_id' => $owner->id, 'status' => 'selected', 'committed_at' => now()]);
        $identity = app(CareHourIdentityService::class);

        $this->assertSame($a->id, $identity->ensureFamilyWallet($owner)->id);
        $this->assertSame($b->id, $identity->forBooking($booking)->id);
        $this->assertSame($b->id, $funding->fresh()->care_hour_account_id);
    }

    public function test_pooled_reservations_settlement_and_correction_keep_exact_source_fees_and_ranges(): void
    {
        $owner = $this->owner();
        $a = $this->source($owner, 'First source');
        $b = $this->source($owner, 'Second source');
        $pa = $this->credit($a, 600, 60, 1100);
        $pb = $this->credit($b, 300, 0, 300);
        $wallet = app(CareHourIdentityService::class)->ensureFamilyWallet($owner);
        $ledger = app(CareHourLedgerService::class);
        $first = $this->booking($owner);
        $second = $this->booking($owner);

        $this->assertSame(720, $ledger->reserve($wallet, $first, 720));
        $this->assertSame([$a->id, $b->id], $ledger->allocationsFor($first)->pluck('care_hour_account_id')->all());
        $this->assertSame(240, $ledger->reserve($b, $second, 300));
        $this->assertSame(0, $wallet->balance()['available_minutes']);
        $ledger->release($second);
        $this->assertSame(240, $wallet->balance()['available_minutes']);

        $result = $ledger->settle($first, 690);
        $this->assertSame(690, $result['used_minutes']);
        $this->assertSame(1130, $result['processing_fee_cents']);
        $corrected = $ledger->settle($first, 630);
        $this->assertSame(1050, $corrected['processing_fee_cents']);
        $this->assertSame(330, $ledger->reserve($wallet, $second, 330));
        $rest = $ledger->settle($second, 330);
        $this->assertSame(350, $rest['processing_fee_cents']);
        $this->assertSame(1400, $corrected['processing_fee_cents'] + $rest['processing_fee_cents']);
        $this->assertSame(['available_minutes' => 0, 'reserved_minutes' => 0, 'used_minutes' => 960, 'held_minutes' => 0], $wallet->balance());
        $this->assertSame($a->id, $pa->fresh()->care_hour_account_id);
        $this->assertSame($b->id, $pb->fresh()->care_hour_account_id);
        foreach (CareHourAllocation::with('lot')->get() as $allocation) {
            $this->assertSame($allocation->lot->care_hour_account_id, $allocation->care_hour_account_id);
        }
        foreach (CareHourLedgerEntry::whereNotNull('care_hour_lot_id')->get() as $entry) {
            $this->assertSame((int) DB::table('care_hour_lots')->where('id', $entry->care_hour_lot_id)->value('care_hour_account_id'), $entry->care_hour_account_id);
        }
        $count = CareHourLedgerEntry::count();
        $ledger->settle($second, 330);
        $ledger->release($second);
        $this->assertSame($count, CareHourLedgerEntry::count());
    }

    public function test_hold_cancellation_and_reversal_are_limited_to_the_original_purchase(): void
    {
        $owner = $this->owner();
        $a = $this->source($owner, 'First source');
        $b = $this->source($owner, 'Second source');
        $pa = $this->credit($a, 300);
        $this->credit($b, 600);
        $ledger = app(CareHourLedgerService::class);
        $booking = $this->booking($owner);
        $ledger->reserve($a, $booking, 360);
        $ledger->holdPurchase($pa, 'Provider review');
        $this->assertSame(['available_minutes' => 540, 'reserved_minutes' => 360, 'used_minutes' => 0, 'held_minutes' => 0], $a->balance());
        $ledger->release($booking);
        $this->assertSame(['available_minutes' => 600, 'reserved_minutes' => 0, 'used_minutes' => 0, 'held_minutes' => 300], $a->balance());
        $ledger->releasePurchaseHold($pa, 'Provider confirmed');
        $this->assertSame(900, $a->balance()['available_minutes']);
        $ledger->reversePurchase($pa, 'Provider reversed');
        $this->assertSame(600, $a->balance()['available_minutes']);
        $this->assertSame($a->id, $pa->fresh()->care_hour_account_id);
        $this->assertSame($b->id, DB::table('care_hour_lots')->where('status', 'available')->sole()->care_hour_account_id);
    }

    public function test_other_families_currencies_and_inactive_sources_cannot_be_spent(): void
    {
        $owner = $this->owner();
        $wallet = $this->source($owner, 'Wallet');
        $inactive = $this->source($owner, 'Paused source');
        $eur = $this->source($owner, 'Euro source', 'eur');
        $foreign = $this->source($this->owner(), 'Other family');
        $this->credit($wallet, 60);
        $this->credit($inactive, 120);
        $this->credit($eur, 180);
        $this->credit($foreign, 240);
        $inactive->update(['active' => false]);
        $ledger = app(CareHourLedgerService::class);
        $booking = $this->booking($owner);

        $this->assertSame(['available_minutes' => 60, 'reserved_minutes' => 0, 'used_minutes' => 0, 'held_minutes' => 120], $wallet->balance());
        $this->assertSame(60, $ledger->reserve($wallet, $booking, 300));
        $this->assertSame([$wallet->id], $ledger->allocationsFor($booking)->pluck('care_hour_account_id')->all());
        $this->expectException(ValidationException::class);
        $ledger->reserve($foreign, $booking, 60);
    }

    public function test_paid_gift_can_establish_family_wallet_while_sales_are_paused_without_creating_a_profile(): void
    {
        $owner = $this->owner();
        config(['care_hours.enabled' => false, 'care_hours.enrollment_enabled' => false, 'care_hours.pilot_only' => true, 'care_hours.pilot_emails' => []]);
        $purchase = CareHourPurchase::create(['public_id' => (string) Str::uuid(), 'request_key' => (string) Str::uuid(),
            'buyer_email' => 'buyer@example.test', 'buyer_name' => 'Gift buyer', 'paid_minutes' => 600, 'bonus_minutes' => 0,
            'total_minutes' => 600, 'amount_cents' => 31000, 'currency' => 'usd', 'status' => 'paid',
            'terms_version' => 'test', 'quote' => [], 'terms_accepted_at' => now(), 'paid_at' => now(), 'livemode' => false,
            'stripe_payment_intent_id' => 'pi_local_gift', 'stripe_charge_id' => 'ch_local_gift', 'success_url' => '/ok', 'cancel_url' => '/cancel']);
        $gift = CareHourGift::create(['care_hour_purchase_id' => $purchase->id, 'ready_at' => now(), 'welcome_bonus_minutes' => 60]);
        $wallet = app(CareHourIdentityService::class)->enrollSelfForGift($owner, $gift);

        $this->assertSame(app(FamilyAccountContext::class)->account($owner)->id, $wallet->family_account_id);
        $this->assertDatabaseCount('care_recipient_profiles', 0);
        $this->assertDatabaseCount('care_hour_accounts', 1);
        $this->assertDatabaseCount('care_hour_lots', 0);
        $this->assertNull($purchase->fresh()->care_hour_account_id);
    }

    public function test_a_foreign_currency_account_cannot_fund_a_usd_visit_even_in_the_same_family(): void
    {
        $owner = $this->owner();
        $eur = $this->source($owner, 'Euro source', 'eur');
        $this->credit($eur, 120);
        $this->expectException(ValidationException::class);
        app(CareHourLedgerService::class)->reserve($eur, $this->booking($owner), 60);
    }

    public function test_failed_shortfall_and_cancellation_release_pooled_credit_once_in_either_order(): void
    {
        foreach ([true, false] as $failureFirst) {
            $owner = $this->owner();
            $wallet = $this->source($owner, 'First source');
            $second = $this->source($owner, 'Second source');
            $this->credit($wallet, 30);
            $this->credit($second, 30);
            $booking = $this->booking($owner);
            $service = app(PrepaidBookingFundingService::class);
            $service->selectForBooking($booking, $wallet, $owner);
            $funding = $service->prepare($booking);
            $operation = CareHourPaymentOperation::create([
                'public_id' => (string) Str::uuid(), 'operation_key' => (string) Str::uuid(),
                'care_hour_booking_funding_id' => $funding->id, 'kind' => 'shortfall',
                'status' => 'checkout', 'amount_cents' => 3100, 'currency' => 'usd',
                'metadata' => ['funding_revision' => $funding->revision],
            ]);
            $this->assertSame(60, $wallet->balance()['reserved_minutes']);
            if ($failureFirst) {
                $service->failShortfall($operation->public_id);
            }
            $booking->update(['status' => 'cancelled']);
            $service->release($booking);
            $service->failShortfall($operation->public_id);
            $service->release($booking);

            $this->assertSame('released', $funding->fresh()->status);
            $this->assertSame(0, $funding->fresh()->reserved_minutes);
            $this->assertSame(60, $wallet->balance()['available_minutes']);
            $this->assertSame(0, $wallet->balance()['reserved_minutes']);
            $this->assertSame(2, app(CareHourLedgerService::class)->history($wallet)->where('type', 'release')->count());
        }
    }

    public function test_a_paused_source_retains_existing_reservations_but_supplies_no_additional_minutes(): void
    {
        $owner = $this->owner();
        $source = $this->source($owner, 'Original source');
        $this->credit($source, 120, 0, 120);
        $booking = $this->booking($owner);
        $ledger = app(CareHourLedgerService::class);
        $ledger->reserve($source, $booking, 60);
        $source->update(['active' => false]);

        $this->assertSame(60, $ledger->reserve($source, $booking, 120));
        $this->assertSame(60, $ledger->settle($booking, 120)['used_minutes']);
        $this->assertSame(['available_minutes' => 0, 'reserved_minutes' => 0, 'used_minutes' => 60, 'held_minutes' => 60], $source->balance());
    }

    public function test_cross_family_profile_cannot_be_used_to_enroll_a_wallet(): void
    {
        $owner = $this->owner();
        $other = $this->owner();
        $profile = CareRecipientProfile::create(['family_account_id' => app(FamilyAccountContext::class)->account($other)->id,
            'full_name' => 'Private recipient', 'status' => 'active']);
        $this->expectException(AuthorizationException::class);
        app(CareHourIdentityService::class)->enrollProfile($profile, $owner);
    }

    private function owner(): User
    {
        $owner = User::factory()->create(['role' => 'family']);
        app(FamilyAccountContext::class)->account($owner);

        return $owner;
    }

    private function source(User $owner, string $name, string $currency = 'usd'): CareHourAccount
    {
        return CareHourAccount::create(['family_account_id' => app(FamilyAccountContext::class)->account($owner)->id,
            'display_name' => $name, 'currency' => $currency]);
    }

    private function credit(CareHourAccount $account, int $paid, int $bonus = 0, int $fee = 0): CareHourPurchase
    {
        $purchase = CareHourPurchase::create(['public_id' => (string) Str::uuid(), 'request_key' => (string) Str::uuid(),
            'care_hour_account_id' => $account->id, 'family_account_id' => $account->family_account_id,
            'buyer_email' => 'buyer@example.test', 'buyer_name' => 'Family', 'paid_minutes' => $paid, 'bonus_minutes' => $bonus,
            'total_minutes' => $paid + $bonus, 'amount_cents' => (int) ($paid * 3100 / 60), 'currency' => $account->currency,
            'status' => 'paid', 'processing_fee_cents' => $fee, 'terms_version' => 'test', 'quote' => [],
            'terms_accepted_at' => now(), 'success_url' => '/ok', 'cancel_url' => '/cancel']);
        app(CareHourLedgerService::class)->issuePurchase($purchase);

        return $purchase;
    }

    private function booking(User $owner): CareBooking
    {
        $family = app(FamilyAccountContext::class)->account($owner);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $request = CareRequest::create(['family_account_id' => $family->id, 'family_user_id' => $owner->id,
            'title' => 'Care', 'status' => 'filled', 'address_line1' => '123 Test St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
            'request_type' => 'one_time', 'requested_start_at' => now()->addDay(), 'requested_end_at' => now()->addDay()->addHours(2)]);
        $application = CareRequestApplication::create(['care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id,
            'status' => 'hired', 'proposed_rate' => 27]);

        return CareBooking::create(['care_request_id' => $request->id, 'care_request_application_id' => $application->id,
            'family_account_id' => $family->id, 'family_user_id' => $owner->id, 'caregiver_user_id' => $caregiver->id,
            'status' => 'scheduled', 'scheduled_start_at' => $request->requested_start_at,
            'scheduled_end_at' => $request->requested_end_at, 'expected_minutes' => 120]);
    }
}
