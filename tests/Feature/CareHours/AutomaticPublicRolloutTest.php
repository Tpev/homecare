<?php

namespace Tests\Feature\CareHours;

use App\Livewire\Family\CreateCareRequestWizard;
use App\Livewire\Family\ManageCareRequest;
use App\Models\CareBookingPayment;
use App\Models\CaregiverProfile;
use App\Models\CareHourAccount;
use App\Models\CareHourPurchase;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Notifications\CareHourVerificationNotification;
use App\Services\CareHours\CareHourAccess;
use App\Services\CareHours\CareHourContributionService;
use App\Services\CareHours\CareHourGiftService;
use App\Services\CareHours\CareHourPurchaseService;
use App\Services\CareHours\PrepaidBookingFundingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AutomaticPublicRolloutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Exercise the actual shipped configuration, overriding the common
        // test fixture's disabled/public simulation without copying its flags.
        config(['care_hours' => require base_path('config/care_hours.php')]);
        $this->assertTrue(config('care_hours.enabled'));
        $this->assertTrue(config('care_hours.gifts_enabled'));
        $this->assertFalse(config('care_hours.pilot_only'));
        $this->assertSame([], config('care_hours.pilot_emails'));
        config(['services.stripe.bypass' => true, 'marketplace.prelaunch_enabled' => false]);
        Notification::fake();
    }

    public function test_shipped_configuration_exposes_purchases_to_all_families_and_guest_gift_buyers(): void
    {
        $pilot = User::factory()->create(['role' => 'family', 'email' => 'peverelli.t@gmail.com']);
        $other = User::factory()->create(['role' => 'family', 'email' => 'ordinary-family@example.test']);
        $access = app(CareHourAccess::class);
        $this->assertTrue($access->canEnroll($pilot));
        $this->assertTrue($access->canBuy($pilot));
        $this->assertTrue($access->canBuyGift($pilot));
        $this->assertTrue($access->canDiscover($other));
        $this->assertTrue($access->canEnroll($other));
        $this->assertTrue($access->canBuy($other));
        $this->assertTrue($access->canBuyGift(null));
        $this->assertTrue($access->canContribute(null));
        $this->assertFalse($access->canDiscover(null));
        $this->assertFalse($access->canDiscover(User::factory()->create(['role' => 'caregiver'])));

        $this->get(route('care-hours.gifts.index'))->assertOk();
        $this->get(route('family.care-hours.buy'))->assertRedirect(route('login'));

        $this->actingAs($pilot)->get(route('family.billing.show'))->assertOk()->assertSee('Prepaid care hours');
        $this->get(route('care-hours.gifts.index'))->assertOk();
        $this->actingAs($other)->get(route('family.billing.show'))->assertOk()->assertSee('Prepaid care hours');
        $this->get(route('care-hours.gifts.index'))->assertOk();
        $this->assertDatabaseCount('care_hour_purchases', 0);
    }

    public function test_family_without_hours_keeps_card_request_hire_and_existing_payment_under_shipped_configuration(): void
    {
        $family = User::factory()->create(['role' => 'family', 'email' => 'ordinary-family@example.test']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::create([
            'user_id' => $caregiver->id, 'status' => 'active', 'screening_initial_approved_at' => now(),
            'identity_verified_at' => now(), 'identity_verification_status' => 'approved',
            'bio' => str_repeat('Experienced caregiver. ', 4), 'platform_hourly_rate' => 27,
            'years_experience' => 5, 'service_area_zip' => '27601', 'service_radius_miles' => 10,
            'insurance_status' => CaregiverProfile::INSURANCE_NO,
        ]);
        $request = CareRequest::create([
            'family_user_id' => $family->id, 'title' => 'Morning companionship',
            'status' => CareRequest::STATUS_OPEN, 'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay()->setTime(9, 0),
            'requested_end_at' => now()->addDay()->setTime(11, 0),
            'address_line1' => '123 Main St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        ]);
        $request->recipient()->create(['full_name' => 'Margaret', 'relationship_to_family' => 'Mother']);
        $application = CareRequestApplication::create([
            'care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED, 'proposed_rate' => 27,
            'cover_note' => str_repeat('Reliable care. ', 10),
        ]);

        Livewire::actingAs($family)->test(CreateCareRequestWizard::class)
            ->assertSet('careHourAccountId', null)->assertDontSee('Use care hours first');
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSet('careHourAccountId', null)->call('reviewHire', $application->id)
            ->assertDontSee('Use care hours')->call('confirmReviewedHire')->assertHasNoErrors();

        $booking = $request->booking()->firstOrFail();
        $this->assertFalse(app(PrepaidBookingFundingService::class)->isPrepaid($booking));
        $payment = $booking->payment;
        $this->assertInstanceOf(CareBookingPayment::class, $payment);
        $before = $payment->getRawOriginal();
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSet('careHourAccountId', null)->call('$refresh');
        $this->assertSame($before, $payment->fresh()->getRawOriginal());
        $this->assertDatabaseCount('care_hour_accounts', 0);
        $this->assertDatabaseCount('care_hour_purchases', 0);
        $this->assertDatabaseCount('care_hour_booking_fundings', 0);
        $this->assertDatabaseCount('care_hour_payment_operations', 0);
    }

    public function test_ordinary_family_can_buy_and_receive_a_guest_contribution_in_one_wallet(): void
    {
        $owner = User::factory()->create(['role' => 'family', 'email' => 'robert@example.test']);
        $this->actingAs($owner)->post(route('family.care-hours.buy.start'))->assertRedirect();
        $wallet = CareHourAccount::sole();
        $purchases = app(CareHourPurchaseService::class);
        $quote = $purchases->quote($wallet, 10);
        $this->get(route('family.care-hours.packages', $wallet))->assertOk()->assertSee('$310');
        $this->post(route('family.care-hours.checkout', $wallet), [
            'hours' => 10, 'quotes' => [10 => $quote['quote_key']], 'request_key' => (string) Str::uuid(), 'terms' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $purchase = CareHourPurchase::sole();
        $purchases->refresh($purchase);
        $this->assertSame(660, $wallet->balance()['available_minutes']);

        $contributions = app(CareHourContributionService::class);
        $link = $contributions->createLink($wallet, $owner);
        auth()->logout();
        $this->flushSession();
        $this->get(route('care-hours.contribute', $link['token']))->assertOk();
        Notification::fake();
        $verification = $contributions->requestVerification($link['link'], 'jane@example.test', 'Jane');
        $token = $contributions->verify($link['link'], $verification->id, $this->otp());
        $contribution = $purchases->startContribution($link['link'], $token, 10,
            $purchases->quote($wallet, 10)['quote_key'], (string) Str::uuid(), url('/ok'), url('/cancel'));
        $purchases->refresh($contribution);
        $this->assertSame(1260, $wallet->balance()['available_minutes']);
        $this->assertSame(0, (int) $contribution->bonus_minutes);
        $this->assertDatabaseCount('care_hour_welcome_claims', 1);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame(1, $wallet->familyAccount->activeMemberships()->count());
        $this->assertNull($owner->fresh()->stripe_customer_id);
        $this->assertNull($wallet->familyAccount->fresh()->stripe_customer_id);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_guest_gift_buyer_can_verify_buy_and_have_another_family_claim_once(): void
    {
        $this->post(route('care-hours.gifts.verify'), [
            'name' => 'Jane Bennett', 'email' => 'jane@example.test', 'hours' => 10,
        ])->assertRedirect(route('care-hours.gifts.index'))->assertSessionHasNoErrors();
        $this->post(route('care-hours.gifts.confirm'), ['code' => $this->otp()])
            ->assertRedirect(route('care-hours.gifts.index'))->assertSessionHasNoErrors();
        $gifts = app(CareHourGiftService::class);
        $this->post(route('care-hours.gifts.checkout'), [
            'hours' => 10, 'quotes' => [10 => $gifts->quote(10)['quote_key']],
            'request_key' => (string) Str::uuid(), 'terms' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $purchase = CareHourPurchase::sole();
        $this->get(route('care-hours.gifts.receipt', $purchase->public_id))->assertOk()->assertSee('Your gift is ready');
        $code = $gifts->codeForBuyer($purchase->fresh(), null, session('care_hours.gift_buyer'));
        $this->assertNotNull($code);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('care_hour_accounts', 0);
        $this->flushSession();

        $mum = User::factory()->create(['role' => 'family', 'email' => 'linda@example.test']);
        $this->actingAs($mum)->post(route('family.care-hours.claim.preview'), ['code' => $code])
            ->assertRedirect()->assertSessionHasNoErrors();
        $nonce = session('care_hours.gift_claim.nonce');
        $this->post(route('family.care-hours.claim.confirm'), ['confirm' => 1, 'nonce' => $nonce])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(660, CareHourAccount::sole()->balance()['available_minutes']);
        $this->assertSame($mum->id, $purchase->gift->fresh()->claimed_by_user_id);
        $this->post(route('family.care-hours.claim.confirm'), ['confirm' => 1, 'nonce' => $nonce])->assertStatus(422);
        $this->assertDatabaseCount('care_hour_lots', 1);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    private function otp(): string
    {
        $code = '';
        Notification::assertSentOnDemand(CareHourVerificationNotification::class, function ($notice) use (&$code) {
            $code = $notice->code;

            return true;
        });

        return $code;
    }
}
