<?php

namespace Tests\Feature\CareHours;

use App\Models\CareBooking;
use App\Models\CareHourAccount;
use App\Models\CareHourPurchase;
use App\Models\CarePricingAgreement;
use App\Models\CareRecipient;
use App\Models\CareRecipientProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Notifications\CareHourVerificationNotification;
use App\Services\CareHours\CareHourContributionService;
use App\Services\CareHours\CareHourGiftService;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\CareHourPaymentGateway;
use App\Services\CareHours\CareHourPurchaseService;
use App\Support\MarketplacePricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CareHourPricingEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['care_hours.enabled' => true, 'care_hours.sales_enabled' => true,
            'care_hours.contributions_enabled' => true, 'care_hours.gifts_enabled' => true,
            'care_hours.welcome_enabled' => true]);
        Notification::fake();
    }

    public function test_custom_family_sees_explanation_before_purchase_and_no_checkout_form(): void
    {
        [$booking, $account, $owner] = $this->family();
        $this->agreement($booking);
        $this->actingAs($owner)->get(route('family.care-hours.packages', $account))->assertOk()
            ->assertSee('custom pricing')->assertSee('Contact support')
            ->assertDontSee('action="'.route('family.care-hours.checkout', $account).'"', false);
        $this->get(route('family.care-hours.show', $account))->assertOk()->assertSee('custom pricing')
            ->assertDontSee('Buy prepaid care')->assertDontSee('Create private link');
        $this->assertDatabaseCount('care_hour_purchases', 0);
    }

    public function test_pricing_added_after_quote_is_rechecked_by_actual_checkout_before_any_purchase(): void
    {
        [$booking, $account, $owner] = $this->family();
        $quote = app(CareHourPurchaseService::class)->quote($account, 10);
        $this->agreement($booking);
        $this->mock(CareHourPaymentGateway::class)->shouldNotReceive('createCheckout');
        $this->actingAs($owner)->from(route('family.care-hours.packages', $account))
            ->post(route('family.care-hours.checkout', $account), [
                'hours' => 10, 'quotes' => [10 => $quote['quote_key']], 'request_key' => (string) Str::uuid(), 'terms' => '1',
            ])->assertSessionHasErrors('purchase');
        $this->assertDatabaseCount('care_hour_purchases', 0);
        $this->assertDatabaseCount('care_hour_welcome_claims', 0);
        $this->assertSame(0, $account->balance()['available_minutes']);
    }

    public function test_direct_quote_and_purchase_cannot_bypass_account_wide_eligibility(): void
    {
        [$booking, $account, $owner] = $this->family();
        $service = app(CareHourPurchaseService::class);
        $quote = $service->quote($account, 5);
        $this->agreement($booking);
        foreach ([fn () => $service->quote($account, 5), fn () => $service->start($account, 5, $owner,
            $quote['quote_key'], (string) Str::uuid(), '/success', '/cancel')] as $attempt) {
            try {
                $attempt();
                $this->fail('Custom-priced families must be blocked before a new prepaid payment.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('purchase', $error->errors());
            }
        }
        $this->assertDatabaseCount('care_hour_purchases', 0);
    }

    public function test_caregiver_specific_pricing_keeps_standard_care_eligible_and_custom_prices_unchanged(): void
    {
        [$booking, $account, $owner] = $this->family();
        $agreement = $this->agreement($booking, allCaregivers: false);
        $before = $agreement->fresh()->getAttributes();
        $purchase = $this->purchase($account, $owner);
        $this->assertSame('issued', $purchase->status);
        $this->assertSame(300, $account->balance()['available_minutes']);
        $this->assertSame($before, $agreement->fresh()->getAttributes());
        $pricing = app(MarketplacePricing::class);
        $this->assertSame(1575, $pricing->quoteForPair($account->family_account_id, $booking->caregiver_user_id, 60)['total_charge_cents']);
        $otherCaregiver = User::factory()->create(['role' => 'caregiver']);
        $this->assertSame(3100, $pricing->quoteForPair($account->family_account_id, $otherCaregiver->id, 60)['total_charge_cents']);
    }

    public function test_inactive_account_wide_agreement_does_not_block_purchase(): void
    {
        [$booking, $account, $owner] = $this->family();
        $this->agreement($booking)->update(['active' => false]);
        $this->assertSame('issued', $this->purchase($account, $owner)->status);
    }

    public function test_existing_contribution_link_is_closed_when_account_wide_pricing_changes(): void
    {
        [$booking, $account, $owner] = $this->family();
        $service = app(CareHourContributionService::class);
        ['link' => $link, 'token' => $linkToken] = $service->createLink($account, $owner);
        $verification = $service->requestVerification($link, 'contributor@example.test', 'Jane');
        $token = $service->verify($link, $verification->id, $this->sentCode());
        $quote = app(CareHourPurchaseService::class)->quote($account, 5);
        $this->agreement($booking);
        $this->get(route('care-hours.contribute', $linkToken))->assertGone()->assertDontSee('custom pricing');
        try {
            app(CareHourPurchaseService::class)->startContribution($link, $token, 5, $quote['quote_key'], (string) Str::uuid(), '/success', '/cancel');
            $this->fail('Previously verified contributors must be rechecked before payment.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('care_hours', $error->errors());
        }
        $this->assertDatabaseCount('care_hour_purchases', 0);
    }

    public function test_custom_priced_family_cannot_create_a_new_contribution_link(): void
    {
        [$booking, $account, $owner] = $this->family();
        $this->agreement($booking);
        $this->expectException(ValidationException::class);
        app(CareHourContributionService::class)->createLink($account, $owner);
    }

    public function test_gift_recipient_selection_remains_scoped_to_the_eligible_family(): void
    {
        [$otherBooking, $otherAccount] = $this->family();
        $this->agreement($otherBooking);
        [, $account, $owner] = $this->family();
        [$purchase, $code] = $this->gift();
        $service = app(CareHourGiftService::class);
        try {
            $service->resolveClaimAccount($code, $owner, 'account:'.$otherAccount->id);
            $this->fail('A claim must never resolve a different family’s recipient.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $error) {
            $this->assertNull($purchase->fresh()->care_hour_account_id);
        }
        // An unrelated custom-priced family must not affect this recipient's eligibility.
        $this->assertSame($account->id, $service->resolveClaimAccount($code, $owner, 'account:'.$account->id)->id);
        $quote = $service->previewForClaim($code, $account, $owner);
        $this->assertSame('issued', $service->claim($code, $account, $owner, $quote['quote_key'])->status);
        $this->assertSame(660, $account->balance()['available_minutes']);
        $this->assertSame(0, $otherAccount->balance()['available_minutes']);
    }

    public function test_gift_remains_unclaimed_if_pricing_changes_after_preview_and_can_be_claimed_when_eligible(): void
    {
        [$booking, $account, $owner] = $this->family();
        [$purchase, $code] = $this->gift();
        $service = app(CareHourGiftService::class);
        $quote = $service->previewForClaim($code, $account, $owner);
        $agreement = $this->agreement($booking);
        try {
            $service->claim($code, $account, $owner, $quote['quote_key']);
            $this->fail('A stale preview cannot assign prepaid money to an ineligible family.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('recipient', $error->errors());
        }
        $this->assertNull($purchase->fresh()->care_hour_account_id);
        $this->assertNull($purchase->gift->fresh()->claimed_at);
        $this->assertDatabaseCount('care_hour_lots', 0);
        $this->assertDatabaseCount('care_hour_welcome_claims', 0);
        $agreement->update(['active' => false]);
        $service->claim($code, $account, $owner, $quote['quote_key']);
        $this->assertSame(660, $account->balance()['available_minutes']);
        $agreement->update(['active' => true]);
        // A replay of an already completed claim is still idempotent and readable.
        $this->assertSame($purchase->id, $service->claim($code, $account, $owner, $quote['quote_key'])->id);
        $this->assertSame(660, $account->balance()['available_minutes']);
    }

    public function test_unknown_recipient_gift_purchase_stays_available_but_custom_family_cannot_claim_for_self(): void
    {
        [$booking, , $owner] = $this->family();
        $this->agreement($booking);
        [$purchase, $code] = $this->gift();
        $this->assertSame('paid', $purchase->status);
        $profiles = CareRecipientProfile::count();
        $accounts = CareHourAccount::count();
        try {
            app(CareHourGiftService::class)->resolveSelfClaimAccount($code, $owner);
            $this->fail('An ineligible self claim must stop before creating a new identity.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('recipient', $error->errors());
        }
        $this->assertDatabaseCount('care_recipient_profiles', $profiles);
        $this->assertDatabaseCount('care_hour_accounts', $accounts);
        $this->assertNull($purchase->gift->fresh()->claimed_at);
        $this->assertNull($purchase->fresh()->care_hour_account_id);
    }

    public function test_paid_purchase_fulfillment_and_receipt_are_preserved_after_pricing_changes(): void
    {
        [$booking, $account, $owner] = $this->family();
        $service = app(CareHourPurchaseService::class);
        $quote = $service->quote($account, 5);
        $requestKey = (string) Str::uuid();
        $purchase = $service->start($account, 5, $owner, $quote['quote_key'], $requestKey, '/success', '/cancel');
        $this->agreement($booking);
        $again = $service->start($account, 5, $owner, $quote['quote_key'], $requestKey, '/success', '/cancel');
        $this->assertSame($purchase->id, $again->id);
        $this->assertSame($purchase->stripe_checkout_session_id, $again->stripe_checkout_session_id);
        $this->assertDatabaseCount('care_hour_purchases', 1);
        // Reconciliation must honor a provider payment already accepted for an existing checkout.
        $this->assertSame('issued', $service->refresh($purchase)->status);
        $this->actingAs($owner)->get(route('family.care-hours.purchase', $purchase->public_id))->assertOk();
        $this->assertSame(300, $account->balance()['available_minutes']);
    }

    public function test_unknown_checkout_attempt_can_be_recovered_after_pricing_changes_without_a_new_purchase(): void
    {
        [$booking, $account, $owner] = $this->family();
        $ids = [];
        $this->mock(CareHourPaymentGateway::class)->shouldReceive('createCheckout')->twice()
            ->andReturnUsing(function (CareHourPurchase $purchase) use (&$ids): array {
                $ids[] = $purchase->public_id;
                if (count($ids) === 1) {
                    throw new \RuntimeException('Simulated lost checkout response');
                }

                return ['id' => 'cs_recovered_original', 'url' => '/original-checkout'];
            });
        $service = app(CareHourPurchaseService::class);
        $quote = $service->quote($account, 10);
        $requestKey = (string) Str::uuid();
        $first = $service->start($account, 10, $owner, $quote['quote_key'], $requestKey, '/success', '/cancel');
        $this->assertNull($first->stripe_checkout_session_id);
        $this->assertNotNull($first->checkout_requested_at);
        $this->agreement($booking);
        $again = $service->start($account, 10, $owner, $quote['quote_key'], $requestKey, '/success', '/cancel');
        $this->assertSame($first->id, $again->id);
        $this->assertSame($ids[0], $ids[1]);
        $this->assertSame('cs_recovered_original', $again->stripe_checkout_session_id);
        $this->assertDatabaseCount('care_hour_purchases', 1);
        $this->assertSame(0, $account->balance()['available_minutes']);
        $this->assertDatabaseHas('care_hour_welcome_claims', ['care_hour_account_id' => $account->id,
            'care_hour_purchase_id' => $first->id, 'status' => 'reserved']);
    }

    public function test_persisted_purchase_without_a_provider_attempt_is_rechecked_before_checkout(): void
    {
        [$booking, $account, $owner] = $this->family();
        $service = app(CareHourPurchaseService::class);
        $quote = $service->quote($account, 5);
        $purchase = $service->start($account, 5, $owner, $quote['quote_key'], (string) Str::uuid(), '/success', '/cancel');
        // Model a process interrupted after the purchase transaction but before the provider request.
        $purchase->update(['stripe_checkout_session_id' => null, 'checkout_url' => null, 'checkout_requested_at' => null]);
        $this->agreement($booking);
        $this->mock(CareHourPaymentGateway::class)->shouldNotReceive('createCheckout');
        try {
            app(CareHourPurchaseService::class)->startCheckout($purchase);
            $this->fail('A persisted quote is not permission to start a new ineligible payment.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('purchase', $error->errors());
        }
        $this->assertNull($purchase->fresh()->checkout_requested_at);
        $this->assertSame(0, $account->balance()['available_minutes']);
    }

    private function purchase(CareHourAccount $account, User $owner): CareHourPurchase
    {
        $service = app(CareHourPurchaseService::class);
        $quote = $service->quote($account, 5);

        return $service->refresh($service->start($account, 5, $owner, $quote['quote_key'], (string) Str::uuid(), '/success', '/cancel'));
    }

    private function gift(): array
    {
        $service = app(CareHourGiftService::class);
        $verification = $service->requestVerification(Str::uuid().'@example.test', 'Jane');
        $token = $service->verify($verification->id, $this->sentCode());
        $purchase = $service->start($token, 10, $service->quote(10)['quote_key'], (string) Str::uuid(), '/success', '/cancel');
        $purchase = app(CareHourPurchaseService::class)->refresh($purchase);

        return [$purchase, $service->codeForBuyer($purchase, null, $token)];
    }

    private function sentCode(): string
    {
        $code = '';
        Notification::assertSentOnDemand(CareHourVerificationNotification::class, function ($notice) use (&$code) {
            $code = $notice->code;

            return true;
        });

        return $code;
    }

    private function agreement(CareBooking $booking, bool $allCaregivers = true): CarePricingAgreement
    {
        return CarePricingAgreement::create([
            'family_account_id' => $booking->family_account_id, 'caregiver_user_id' => $booking->caregiver_user_id,
            'source_booking_id' => $booking->id, 'created_by_user_id' => $booking->family_user_id,
            'active' => true, 'all_caregivers' => $allCaregivers ? true : null,
            'family_care_rate_cents' => 1575, 'family_processing_fee_rate_cents' => 0, 'caregiver_gross_rate_cents' => 1500,
            'caregiver_fee_policy' => CarePricingAgreement::PLATFORM_PAYS_PROCESSING, 'reason' => 'Reviewed custom family pricing',
        ]);
    }

    private function family(): array
    {
        $owner = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $request = CareRequest::create(['family_user_id' => $owner->id, 'title' => 'Care', 'status' => 'filled',
            'address_line1' => '123 Test St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601', 'request_type' => 'one_time',
            'requested_start_at' => now()->addDay(), 'requested_end_at' => now()->addDay()->addHours(2)]);
        $recipient = CareRecipient::create(['care_request_id' => $request->id, 'full_name' => 'Margaret', 'relationship_to_family' => 'Mother']);
        $account = app(CareHourIdentityService::class)->enroll($recipient, $owner);
        $application = CareRequestApplication::create(['care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'hired', 'proposed_rate' => 27]);
        $booking = CareBooking::create(['care_request_id' => $request->id, 'care_request_application_id' => $application->id,
            'family_user_id' => $owner->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'scheduled',
            'scheduled_start_at' => $request->requested_start_at, 'scheduled_end_at' => $request->requested_end_at, 'expected_minutes' => 120]);

        return [$booking, $account, $owner];
    }
}
