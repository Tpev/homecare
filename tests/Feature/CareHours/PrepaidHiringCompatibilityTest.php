<?php

namespace Tests\Feature\CareHours;

use App\Livewire\Family\ManageCareRequest;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CaregiverProfile;
use App\Models\CareHourAccount;
use App\Models\CareHourPaymentOperation;
use App\Models\CareHourPurchase;
use App\Models\CarePricingAgreement;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\CareHourLedgerService;
use App\Services\CareHours\PrepaidBookingFundingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PrepaidHiringCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_hire_uses_hours_without_card_configuration_or_card_payment_records(): void
    {
        [$family, $request, $application, $account] = $this->scenario();
        config(['services.stripe.bypass' => false, 'services.stripe.secret' => null]);
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->set('careHourAccountId', $account->id)->call('hire', $application->id)->assertHasNoErrors();

        $booking = $request->booking()->firstOrFail();
        $this->assertSame('ready', app(PrepaidBookingFundingService::class)->forBooking($booking)->status);
        $this->assertSame(120, $account->balance()['reserved_minutes']);
        $this->assertDatabaseCount('care_booking_payments', 0);
        $this->assertNull($family->fresh()->stripe_customer_id);
    }

    public function test_explicit_card_choice_with_positive_balance_survives_reload_and_keeps_existing_card_path(): void
    {
        [$family, $request, $application, $account] = $this->scenario();
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSet('careHourAccountId', $account->id)
            ->set('careHourAccountId', null)->call('$refresh')->assertSet('careHourAccountId', null);
        $this->assertDatabaseHas('care_hour_request_preferences', ['care_request_id' => $request->id, 'enabled' => false]);
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSet('careHourAccountId', null)->call('reviewHire', $application->id)->assertSet('careHourAccountId', null)
            ->call('hire', $application->id)->assertHasNoErrors();

        $booking = $request->booking()->firstOrFail();
        $this->assertFalse(app(PrepaidBookingFundingService::class)->isPrepaid($booking));
        $this->assertInstanceOf(CareBookingPayment::class, $booking->payment);
        $this->assertSame(300, $account->balance()['available_minutes']);
        $this->assertSame(0, $account->balance()['reserved_minutes']);
    }

    public static function featureStates(): array
    {
        return ['feature enabled' => [true], 'new sales and enrollment paused' => [false]];
    }

    #[DataProvider('featureStates')]
    public function test_a_family_without_a_card_has_existing_hours_selected_by_default_without_reserving_before_confirmation(bool $enabled): void
    {
        [$family, $request, $application, $account] = $this->scenario();
        config(['care_hours.enabled' => $enabled, 'services.stripe.bypass' => false, 'services.stripe.secret' => null]);
        $this->assertNull($family->stripe_customer_id);
        $this->assertNull($account->familyAccount->stripe_customer_id);

        $component = Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setActiveTab', 'applicants')->assertSet('careHourAccountId', $account->id);
        $this->assertButtonDisabled($component->html(), 'reviewHire('.$application->id.')', false);

        $component->call('reviewHire', $application->id)->assertSee('Usual card payment')->assertSee('Use care hours');
        $document = new \DOMDocument;
        @$document->loadHTML($component->html());
        $inputs = (new \DOMXPath($document))->query('//input[@name="care-hour-funding"][@checked]');
        $this->assertCount(1, $inputs);
        $this->assertSame((string) $account->id, $inputs->item(0)->getAttribute('value'));
        $this->assertButtonDisabled($component->html(), 'confirmReviewedHire', false);

        $this->assertFalse($request->booking()->exists());
        $this->assertSame(CareRequest::STATUS_OPEN, $request->fresh()->status);
        $this->assertSame(300, $account->balance()['available_minutes']);
        $this->assertDatabaseCount('care_booking_payments', 0);
        $this->assertDatabaseCount('care_hour_booking_fundings', 0);

        $component->call('confirmReviewedHire')->assertHasNoErrors();

        $booking = $request->booking()->firstOrFail();
        $this->assertSame('ready', app(PrepaidBookingFundingService::class)->forBooking($booking)->status);
        $this->assertSame(180, $account->balance()['available_minutes']);
        $this->assertSame(120, $account->balance()['reserved_minutes']);
        $this->assertDatabaseCount('care_booking_payments', 0);
        $this->assertDatabaseCount('care_hour_payment_operations', 0);
        $this->assertNull($family->fresh()->stripe_customer_id);
        $this->assertNull($account->familyAccount->fresh()->stripe_customer_id);
    }

    public function test_partial_balance_defaults_to_hours_and_shows_remaining_payment_before_hire(): void
    {
        [$family, $request, $application, $account] = $this->scenario();
        $request->update(['requested_end_at' => $request->requested_start_at->copy()->addHours(6)]);
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('reviewHire', $application->id)->assertSet('careHourAccountId', $account->id)
            ->assertSee('From care hours')->assertSee('5 h')->assertSee('Remaining payment')->assertSee('$31.00')
            ->assertSee('1 h needs a separate payment');
        $this->assertSame(300, $account->balance()['available_minutes']);
        $this->assertDatabaseCount('care_hour_booking_fundings', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_held_hours_and_inactive_accounts_are_not_defaulted(): void
    {
        [$family, $request, $application, $account] = $this->scenario();
        $account->lots()->update(['status' => 'held']);
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('reviewHire', $application->id)->assertSet('careHourAccountId', null);
        $account->lots()->update(['status' => 'available']);
        $account->update(['active' => false]);
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('reviewHire', $application->id)->assertSet('careHourAccountId', null)->assertDontSee('Use care hours');
        $this->assertDatabaseCount('care_hour_booking_fundings', 0);
    }

    public function test_same_named_recipient_without_the_balance_link_does_not_inherit_hours(): void
    {
        [$family, $request, , $account] = $this->scenario();
        $otherRequest = $request->replicate();
        $otherRequest->save();
        $otherRequest->recipient()->create(['full_name' => 'Margaret', 'relationship_to_family' => 'Mother']);
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $otherRequest->id])
            ->assertSet('careHourAccountId', null)->assertDontSee('Use care hours');
        $this->assertSame(300, $account->balance()['available_minutes']);
    }

    public function test_custom_priced_applicant_review_uses_card_and_direct_hire_cannot_apply_hours(): void
    {
        [$family, $request, $application, $account] = $this->scenario();
        $this->customAgreement($family, $request, $application);
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSet('careHourAccountId', $account->id)->call('hire', $application->id)
            ->assertHasErrors('careHourAccountId');
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('reviewHire', $application->id)->assertSet('careHourAccountId', null)->assertDontSee('Use care hours');
        $this->assertFalse($request->booking()->exists());
        $this->assertDatabaseCount('care_hour_booking_fundings', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_partial_day_recurring_applicant_does_not_default_to_prepaid(): void
    {
        [$family, $request, $application, $account] = $this->scenario();
        $request->update(['request_type' => CareRequest::TYPE_RECURRING,
            'recurring_starts_on' => now()->addDay()->toDateString(), 'recurring_days' => [1, 3],
            'recurring_start_time' => '09:00', 'recurring_end_time' => '11:00']);
        $application->update(['selected_days' => [1]]);
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('reviewHire', $application->id)->assertSet('careHourAccountId', null)->assertDontSee('Use care hours');
        $this->assertSame(300, $account->balance()['available_minutes']);
        $this->assertDatabaseCount('care_hour_booking_fundings', 0);
    }

    public function test_existing_card_funded_visit_is_not_defaulted_or_changed(): void
    {
        [$family, $request, $application, $account] = $this->scenario();
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->set('careHourAccountId', null)->call('hire', $application->id)->assertHasNoErrors();
        $payment = $request->booking()->firstOrFail()->payment;
        $before = $payment->getRawOriginal();
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSet('careHourAccountId', null)->call('$refresh');
        $this->assertSame($before, $payment->fresh()->getRawOriginal());
        $this->assertSame(300, $account->balance()['available_minutes']);
        $this->assertDatabaseCount('care_hour_booking_fundings', 0);
    }

    #[DataProvider('featureStates')]
    public function test_no_prepaid_balance_preserves_the_existing_missing_card_entry(bool $enabled): void
    {
        [$family, $request, $application, $account] = $this->scenario(false);
        config(['care_hours.enabled' => $enabled, 'services.stripe.bypass' => false, 'services.stripe.secret' => null]);
        $originalBalance = $account->balance();

        $component = Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setActiveTab', 'applicants')->assertSee('Add a card to hire')->assertSee('Review care & price');
        $this->assertButtonDisabled($component->html(), 'reviewHire('.$application->id.')', true);

        $component->call('reviewHire', $application->id)->assertSet('careHourAccountId', null);
        $this->assertButtonDisabled($component->html(), 'confirmReviewedHire', true);
        $component->call('confirmReviewedHire');
        $this->assertFalse($request->booking()->exists());
        $this->assertSame(CareRequest::STATUS_OPEN, $request->fresh()->status);
        $this->assertSame($originalBalance, $account->balance());
        $this->assertDatabaseCount('care_booking_payments', 0);
        $this->assertDatabaseCount('care_hour_booking_fundings', 0);
    }

    public function test_another_familys_hours_cannot_be_selected_during_hiring(): void
    {
        [$family, $request, $application] = $this->scenario();
        [, , , $otherAccount] = $this->scenario();
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->set('careHourAccountId', $otherAccount->id)->call('hire', $application->id)
            ->assertHasErrors('careHourAccountId');
        $this->assertFalse($request->booking()->exists());
        $this->assertSame(CareRequest::STATUS_OPEN, $request->fresh()->status);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_admin_can_review_recorded_settlement_with_a_reason_and_cannot_cross_account_scope(): void
    {
        [$family, $request, $application, $account] = $this->scenario();
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->set('careHourAccountId', $account->id)->call('hire', $application->id)->assertHasNoErrors();
        $booking = $request->booking()->firstOrFail();
        $booking->update(['status' => 'completed', 'worked_minutes' => 60, 'completed_at' => now()]);
        $settlement = app(\App\Services\CareHours\PrepaidBookingSettlementService::class)->settle($booking);
        $funding = $settlement->funding;
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'test@test.com']);
        $this->actingAs($admin)->get(route('admin.care-hours.show', $account))
            ->assertOk()->assertSee('Payment details and recovery')->assertSee('Caregiver gross $27.00');
        $this->post(route('admin.care-hours.visit.recheck', [$account, $funding]), ['reason' => 'Review pending caregiver payout'])->assertRedirect();
        $this->assertDatabaseHas('family_account_activity_logs', ['actor_user_id' => $admin->id, 'action' => 'care_hours_visit_recheck']);
        $this->assertSame(60, $account->balance()['used_minutes']);
        $this->assertDatabaseCount('care_booking_payments', 0);

        $otherFamily = User::factory()->create(['role' => 'family']);
        $other = \App\Services\FamilyAccounts\FamilyAccountContext::class;
        $otherAccount = CareHourAccount::create(['family_account_id' => app($other)->account($otherFamily)->id, 'display_name' => 'Other recipient']);
        $this->actingAs($admin)->post(route('admin.care-hours.visit.recheck', [$otherAccount, $funding]), ['reason' => 'Wrong account visit'])->assertNotFound();
    }

    public function test_failed_email_does_not_starve_a_family_notice_in_a_bounded_batch(): void
    {
        [$family, , , $account] = $this->scenario();
        $purchase = $account->purchases()->firstOrFail();
        foreach (['receipt' => $family->email, 'family' => (string) $family->id] as $kind => $recipient) {
            DB::table('care_hour_purchase_notices')->insert(['care_hour_purchase_id' => $purchase->id,
                'kind' => $kind, 'recipient' => $recipient, 'dedupe_key' => hash('sha256', $kind),
                'created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);
        }
        Notification::swap(\Mockery::mock(\Illuminate\Notifications\ChannelManager::class));
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Test mail outage'));
        $service = app(\App\Services\CareHours\CareHourPurchaseService::class);
        $this->assertSame(0, $service->dispatchNotices(1));
        Notification::fake();
        $this->assertSame(1, $service->dispatchNotices(1));
        $this->assertNotNull(DB::table('care_hour_purchase_notices')->where('kind', 'family')->value('sent_at'));
        $this->assertNull(DB::table('care_hour_purchase_notices')->where('kind', 'receipt')->value('sent_at'));
    }

    public function test_recovery_skips_old_completed_refunds_so_new_cancelled_visits_are_processed(): void
    {
        $payments = [];
        foreach ([true, false] as $alreadyRefunded) {
            [$family, $request, $application, $account] = $this->scenario();
            $booking = CareBooking::create(['care_request_id' => $request->id,
                'care_request_application_id' => $application->id, 'family_user_id' => $family->id,
                'caregiver_user_id' => $application->caregiver_user_id, 'status' => 'scheduled',
                'scheduled_start_at' => $request->requested_start_at, 'scheduled_end_at' => $request->requested_end_at,
                'expected_minutes' => 120]);
            $service = app(PrepaidBookingFundingService::class);
            $funding = $service->selectForBooking($booking, $account, $family);
            $booking->update(['status' => 'cancelled']);
            $funding->update(['status' => 'released', 'updated_at' => now()->subDays($alreadyRefunded ? 2 : 1)]);
            $payments[] = CareHourPaymentOperation::create(['public_id' => (string) Str::uuid(),
                'operation_key' => (string) Str::uuid(), 'care_hour_booking_funding_id' => $funding->id,
                'kind' => 'shortfall', 'status' => 'captured', 'amount_cents' => 100, 'captured_cents' => 100,
                'refunded_cents' => $alreadyRefunded ? 100 : 0, 'currency' => 'usd',
                'stripe_payment_intent_id' => 'pi_recovery_'.$funding->id]);
        }
        $this->artisan('care-hours:reconcile-bookings', ['--limit' => 1])->assertSuccessful();
        $this->assertSame(100, $payments[1]->fresh()->refunded_cents);
        $this->assertSame(1, CareHourPaymentOperation::where('kind', 'refund')->count());
    }

    private function customAgreement(User $family, CareRequest $request, CareRequestApplication $application): void
    {
        $past = $request->replicate();
        $past->status = CareRequest::STATUS_FILLED;
        $past->save();
        $pastApplication = $application->replicate();
        $pastApplication->care_request_id = $past->id;
        $pastApplication->save();
        $booking = CareBooking::create(['care_request_id' => $past->id, 'care_request_application_id' => $pastApplication->id,
            'family_user_id' => $family->id, 'caregiver_user_id' => $application->caregiver_user_id,
            'status' => CareBooking::STATUS_COMPLETED]);
        CarePricingAgreement::create(['family_account_id' => $request->family_account_id,
            'caregiver_user_id' => $application->caregiver_user_id, 'source_booking_id' => $booking->id,
            'created_by_user_id' => $family->id, 'family_care_rate_cents' => 1575,
            'family_processing_fee_rate_cents' => 0, 'caregiver_gross_rate_cents' => 1500,
            'caregiver_fee_policy' => CarePricingAgreement::PLATFORM_PAYS_PROCESSING,
            'reason' => 'Existing custom caregiver pricing.', 'active' => true]);
    }

    private function assertButtonDisabled(string $html, string $action, bool $disabled): void
    {
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $buttons = (new \DOMXPath($document))->query('//button[@*[name()="wire:click"]="'.$action.'"][not(contains(@class, "hc-care-text-link"))]');
        $this->assertCount(1, $buttons);
        $this->assertSame($disabled, $buttons->item(0)->hasAttribute('disabled'));
    }

    private function scenario(bool $withCredit = true): array
    {
        config(['care_hours.enabled' => true, 'services.stripe.bypass' => true]);
        Notification::fake();
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::create(['user_id' => $caregiver->id, 'status' => 'active',
            'screening_initial_approved_at' => now(), 'identity_verified_at' => now(),
            'identity_verification_status' => 'approved', 'bio' => str_repeat('Experienced caregiver. ', 4),
            'platform_hourly_rate' => 27, 'years_experience' => 5, 'service_area_zip' => '27601',
            'service_radius_miles' => 10, 'insurance_status' => CaregiverProfile::INSURANCE_NO]);
        $request = CareRequest::create(['family_user_id' => $family->id, 'title' => 'Morning companionship',
            'status' => CareRequest::STATUS_OPEN, 'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay()->setTime(9, 0), 'requested_end_at' => now()->addDay()->setTime(11, 0),
            'address_line1' => '123 Main St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601']);
        $recipient = $request->recipient()->create(['full_name' => 'Margaret', 'relationship_to_family' => 'Mother']);
        $account = app(CareHourIdentityService::class)->enroll($recipient, $family);
        if ($withCredit) {
            $purchase = CareHourPurchase::create(['public_id' => (string) Str::uuid(), 'request_key' => (string) Str::uuid(),
                'care_hour_account_id' => $account->id, 'family_account_id' => $account->family_account_id,
                'buyer_user_id' => $family->id, 'buyer_email' => $family->email, 'buyer_name' => $family->name,
                'paid_minutes' => 300, 'bonus_minutes' => 0, 'total_minutes' => 300, 'amount_cents' => 15500,
                'currency' => 'usd', 'status' => 'paid', 'processing_fee_cents' => 0, 'terms_version' => 'test', 'quote' => [],
                'terms_accepted_at' => now(), 'success_url' => '/care-hours', 'cancel_url' => '/care-hours']);
            app(CareHourLedgerService::class)->issuePurchase($purchase);
        }
        $application = CareRequestApplication::create(['care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id, 'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 27, 'cover_note' => str_repeat('Reliable care. ', 10)]);

        return [$family, $request, $application, $account];
    }
}
