<?php

namespace Tests\Feature\CareHours;

use App\Livewire\Family\CreateCareRequestWizard;
use App\Livewire\Family\ManageCareRequest;
use App\Models\CareBookingPayment;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\CareHours\CareHourAccess;
use App\Services\CareHours\PrepaidBookingFundingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class AutomaticPrivatePilotTest extends TestCase
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
        $this->assertTrue(config('care_hours.pilot_only'));
        $this->assertSame(['peverelli.t@gmail.com'], config('care_hours.pilot_emails'));
        config(['services.stripe.bypass' => true, 'marketplace.prelaunch_enabled' => false]);
        Notification::fake();
    }

    public function test_shipped_configuration_exposes_new_purchases_only_to_the_named_family(): void
    {
        $pilot = User::factory()->create(['role' => 'family', 'email' => 'peverelli.t@gmail.com']);
        $other = User::factory()->create(['role' => 'family', 'email' => 'ordinary-family@example.test']);
        $access = app(CareHourAccess::class);
        $this->assertTrue($access->canEnroll($pilot));
        $this->assertTrue($access->canBuy($pilot));
        $this->assertTrue($access->canBuyGift($pilot));
        $this->assertFalse($access->canDiscover($other));
        $this->assertFalse($access->canBuyGift(null));

        $this->actingAs($pilot)->get(route('family.billing.show'))->assertOk()->assertSee('Care hours');
        $this->get(route('care-hours.gifts.index'))->assertOk();
        $this->actingAs($other)->get(route('family.billing.show'))->assertOk()->assertDontSee('Care hours');
        $this->get(route('care-hours.gifts.index'))->assertNotFound();
        $this->assertDatabaseCount('care_hour_purchases', 0);
    }

    public function test_nonpilot_keeps_card_request_hire_and_existing_payment_under_shipped_configuration(): void
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
}
