<?php

namespace Tests\Feature\Family;

use App\Livewire\Family\ManageCareRequest;
use App\Models\CareBooking;
use App\Models\CaregiverCertification;
use App\Models\CaregiverCertificationType;
use App\Models\CaregiverProfile;
use App\Models\CarePricingAgreement;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestInvitation;
use App\Models\FamilyCaregiverFavorite;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use App\Support\MarketplacePricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CareRecruitmentPolishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('marketplace.caregiver_prelaunch_mode', false);
        config()->set('marketplace.pricing_v2.enabled', true);
        config()->set('marketplace.pricing_v2.family_care_hourly_cents', 3000);
        config()->set('marketplace.pricing_v2.family_processing_fee_hourly_cents', 100);
        Notification::fake();
    }

    public function test_full_page_discovery_keeps_search_invitation_history_and_saved_views_on_the_same_request(): void
    {
        [$family, $request] = $this->requestFixture();
        $caregiver = $this->readyCaregiver('Jordan Searchable');
        $invitation = CareRequestInvitation::query()->create([
            'care_request_id' => $request->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestInvitation::STATUS_PENDING,
            'message' => 'Please consider our morning visit.',
            'expires_at' => $request->requested_end_at,
        ]);

        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setCaregiverView', 'search')
            ->assertSet('activeTab', 'invite')
            ->assertSet('caregiverView', 'search')
            ->assertSet('showCaregiverInvitePanel', false)
            ->set('caregiverSearch', 'Jordan')
            ->assertViewHas('caregiverSearchResults', fn ($results) => $results->pluck('user_id')->all() === [$caregiver->id])
            ->call('setCaregiverView', 'invited')
            ->assertSet('activeTab', 'invite')
            ->assertSet('caregiverView', 'invited')
            ->assertViewHas('invitationCards', fn ($cards) => $cards[$caregiver->id]['relationship_state'] === 'pending')
            ->call('setCaregiverView', 'saved')
            ->assertSet('caregiverView', 'saved')
            ->assertViewHas('savedDiscoveryCaregivers', fn ($caregivers) => $caregivers->isEmpty())
            ->call('setActiveTab', 'overview')
            ->assertSet('activeTab', 'overview');

        $this->assertSame(CareRequestInvitation::STATUS_PENDING, $invitation->fresh()->status);
        $this->assertDatabaseCount('care_request_invitations', 1);
        $this->assertDatabaseCount('care_request_applications', 0);
        $this->assertDatabaseCount('care_bookings', 0);
    }

    public function test_invalid_discovery_views_are_normalized_and_invalid_actions_do_not_change_the_selected_view(): void
    {
        [$family, $request] = $this->requestFixture();

        Livewire::actingAs($family)->withQueryParams(['tab' => 'invite', 'view' => 'not-a-view'])
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSet('activeTab', 'invite')
            ->assertSet('caregiverView', 'search')
            ->call('setCaregiverView', 'saved')
            ->call('setCaregiverView', 'not-a-view')
            ->assertSet('caregiverView', 'saved')
            ->set('caregiverView', 'not-a-view')
            ->assertSet('caregiverView', 'search')
            ->call('setApplicantView', 'shortlisted')
            ->call('setApplicantView', 'not-a-view')
            ->assertSet('activeTab', 'applicants')
            ->assertSet('applicationStatus', CareRequestApplication::STATUS_SHORTLISTED);

        $this->assertDatabaseCount('care_request_invitations', 0);
        $this->assertDatabaseCount('care_bookings', 0);
    }

    public function test_selecting_a_discovery_result_opens_an_invitation_preview_without_sending(): void
    {
        [$family, $request] = $this->requestFixture();
        $caregiver = $this->readyCaregiver('Taylor Preview');

        Livewire::actingAs($family)->withQueryParams(['tab' => 'invite'])
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSet('showCaregiverInvitePanel', false)
            ->call('beginCaregiverInvitation', $caregiver->id)
            ->assertSet('activeTab', 'invite')
            ->assertSet('showCaregiverInvitePanel', true)
            ->assertSet('confirmingCaregiverId', $caregiver->id)
            ->assertViewHas('confirmingCaregiver', fn ($card) => $card['user_id'] === $caregiver->id)
            ->set('caregiverInviteMessage', 'A draft to review before sending.')
            ->call('cancelCaregiverInvitation')
            ->assertSet('showCaregiverInvitePanel', false)
            ->assertSet('confirmingCaregiverId', null);

        $this->assertDatabaseCount('care_request_invitations', 0);
        $this->assertDatabaseCount('care_request_applications', 0);
        $this->assertDatabaseCount('care_bookings', 0);
        Notification::assertNothingSent();
    }

    public function test_cancelling_or_closing_a_full_page_invitation_preview_preserves_discovery_filters(): void
    {
        [$family, $request] = $this->requestFixture();
        $caregiver = $this->readyCaregiver('Riley Certified');
        $type = CaregiverCertificationType::query()->where('slug', 'cpr')->firstOrFail();
        CaregiverCertification::query()->create([
            'caregiver_profile_id' => $caregiver->caregiverProfile->id,
            'caregiver_certification_type_id' => $type->id,
            'verification_status' => CaregiverCertification::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setCaregiverView', 'search')
            ->set('caregiverSearch', 'Riley')
            ->set('certificationTypes', ['cpr'])
            ->set('certificationVerification', 'verified_only')
            ->call('beginCaregiverInvitation', $caregiver->id)
            ->assertSet('confirmingCaregiverId', $caregiver->id)
            ->call('cancelCaregiverInvitation')
            ->assertSet('activeTab', 'invite')
            ->assertSet('showCaregiverInvitePanel', false)
            ->assertSet('caregiverSearch', 'Riley')
            ->assertSet('certificationTypes', ['cpr'])
            ->assertSet('certificationVerification', 'verified_only')
            ->call('beginCaregiverInvitation', $caregiver->id)
            ->assertSet('confirmingCaregiverId', $caregiver->id)
            ->call('closeCaregiverInvitePanel')
            ->assertSet('showCaregiverInvitePanel', false)
            ->assertSet('certificationTypes', ['cpr'])
            ->assertSet('certificationVerification', 'verified_only');

        $this->assertDatabaseCount('care_request_invitations', 0);
        Notification::assertNothingSent();
    }

    public function test_sending_from_full_page_discovery_returns_to_the_sent_invitation_record(): void
    {
        [$family, $request] = $this->requestFixture();
        $caregiver = $this->readyCaregiver('Alex Invited');

        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setCaregiverView', 'search')
            ->set('caregiverSearch', 'Alex')
            ->call('beginCaregiverInvitation', $caregiver->id)
            ->set('caregiverInviteMessage', 'Please review our morning companionship request.')
            ->call('sendCaregiverInvitation')
            ->assertHasNoErrors()
            ->assertSet('activeTab', 'invite')
            ->assertSet('caregiverView', 'invited')
            ->assertSet('showCaregiverInvitePanel', false)
            ->assertSet('confirmingCaregiverId', null)
            ->assertSee('People you invited')
            ->assertSee('Invitation sent')
            ->assertSee($caregiver->name);

        $this->assertDatabaseHas('care_request_invitations', [
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestInvitation::STATUS_PENDING,
            'message' => 'Please review our morning companionship request.',
        ]);
        $this->assertDatabaseCount('care_request_invitations', 1);
        $this->assertDatabaseCount('care_request_applications', 0);
        $this->assertDatabaseCount('care_bookings', 0);
    }

    public function test_request_shortlisting_does_not_create_a_saved_profile_or_merge_the_two_lists(): void
    {
        [$family, $request] = $this->requestFixture();
        $applicant = $this->readyCaregiver('Casey Applicant');
        $favorite = $this->readyCaregiver('Morgan Saved Profile');
        $application = $this->application($request, $applicant);
        FamilyCaregiverFavorite::query()->create([
            'family_user_id' => $family->id,
            'caregiver_user_id' => $favorite->id,
        ]);

        $component = Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('shortlist', $application->id)
            ->call('setApplicantView', 'shortlisted')
            ->assertSet('applicationStatus', CareRequestApplication::STATUS_SHORTLISTED);
        $this->assertSame([$application->id], $component->instance()->visibleApplications->pluck('id')->all());

        $component->call('setCaregiverView', 'saved')
            ->assertViewHas('savedDiscoveryCaregivers', fn ($caregivers) => $caregivers->pluck('user_id')->all() === [$favorite->id]);

        $this->assertSame(CareRequestApplication::STATUS_SHORTLISTED, $application->fresh()->status);
        $this->assertDatabaseCount('family_caregiver_favorites', 1);
        $this->assertDatabaseMissing('family_caregiver_favorites', ['caregiver_user_id' => $applicant->id]);
        $this->assertDatabaseMissing('care_request_applications', ['caregiver_user_id' => $favorite->id]);
    }

    public function test_applicants_hide_prices_and_sort_by_date_while_hire_review_preserves_pair_pricing(): void
    {
        [$family, $request] = $this->requestFixture();
        $agreedCaregiver = $this->readyCaregiver('Agreed Rate Caregiver');
        $standardCaregiver = $this->readyCaregiver('Standard Rate Caregiver');
        $agreedApplication = $this->application($request, $agreedCaregiver, rate: 99);
        $standardApplication = $this->application($request, $standardCaregiver, rate: 5);
        $agreedApplication->forceFill(['created_at' => now()->subMinutes(10)])->save();
        $standardApplication->forceFill(['created_at' => now()->subMinutes(5)])->save();
        $agreement = $this->pricingAgreement($family, $agreedCaregiver, 1575, 0);
        $beforeAgreement = $agreement->fresh()->getRawOriginal();
        $beforeApplication = $agreedApplication->fresh()->getRawOriginal();
        $beforeStandardApplication = $standardApplication->fresh()->getRawOriginal();
        $beforeRequest = $request->fresh()->getRawOriginal();

        $component = Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setActiveTab', 'applicants')
            ->assertSee($agreedCaregiver->name)
            ->assertSee($standardCaregiver->name)
            ->assertSee('Latest first')
            ->assertSee('Oldest first')
            ->assertDontSee('Care rate high-low')
            ->assertDontSee('Care rate low-high')
            ->assertDontSee('Rate recorded on application')
            ->assertDontSee('/hr care')
            ->assertDontSee('/hr processing')
            ->assertDontSee('$15.75')
            ->assertDontSee('$30.00')
            ->assertDontSee('$99.00')
            ->assertDontSee('$5.00')
            ->assertSet('applicationSort', 'latest');
        $this->assertSame([$standardApplication->id, $agreedApplication->id], $component->instance()->visibleApplications->pluck('id')->all());
        $component->set('applicationSort', 'oldest');
        $this->assertSame([$agreedApplication->id, $standardApplication->id], $component->instance()->visibleApplications->pluck('id')->all());

        $component->call('reviewHire', $agreedApplication->id)
            ->assertViewHas('hireDecisionQuote', fn ($quote) => $quote['rate_cents'] === 1575
                && $quote['fee_rate_cents'] === 0 && $quote['care_cents'] === 3150
                && $quote['fee_cents'] === 0 && $quote['total_cents'] === 3150
                && $quote['minutes'] === 120 && $quote['currency'] === 'USD')
            ->assertSee('$15.75')
            ->assertSee('$31.50')
            ->call('closeDecisionReview')
            ->call('reviewHire', $standardApplication->id)
            ->assertViewHas('hireDecisionQuote', fn ($quote) => $quote['rate_cents'] === 3000
                && $quote['fee_rate_cents'] === 100 && $quote['care_cents'] === 6000
                && $quote['fee_cents'] === 200 && $quote['total_cents'] === 6200
                && $quote['minutes'] === 120 && $quote['currency'] === 'USD')
            ->assertSee('$30.00')
            ->assertSee('$62.00')
            ->call('closeDecisionReview');

        $this->assertSame($beforeAgreement, $agreement->fresh()->getRawOriginal());
        $this->assertSame($beforeApplication, $agreedApplication->fresh()->getRawOriginal());
        $this->assertSame($beforeStandardApplication, $standardApplication->fresh()->getRawOriginal());
        $this->assertSame($beforeRequest, $request->fresh()->getRawOriginal());
        $this->assertDatabaseCount('care_bookings', 1); // The agreement's pre-existing source visit only.
        $this->assertDatabaseCount('care_booking_payments', 0);
        $this->assertDatabaseCount('care_request_invitations', 0);
    }

    public function test_existing_booking_quote_uses_its_snapshot_when_the_pair_agreement_has_changed(): void
    {
        [$family, $request] = $this->requestFixture();
        $caregiver = $this->readyCaregiver('Snapshot Caregiver');
        $application = $this->application($request, $caregiver, CareRequestApplication::STATUS_HIRED, 99);
        $agreement = $this->pricingAgreement($family, $caregiver, 1575, 0);
        $request->update(['status' => CareRequest::STATUS_FILLED, 'first_hire_at' => now()]);
        $booking = CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => $request->requested_start_at,
            'scheduled_end_at' => $request->requested_end_at,
            'expected_minutes' => 120,
            'pricing_version' => app(MarketplacePricing::class)->currentVersion(),
            'pricing_agreement_id' => $agreement->id,
            'family_care_rate_cents' => 2300,
            'family_processing_fee_rate_cents' => 200,
            'caregiver_gross_rate_cents' => 2000,
            'caregiver_fee_policy' => CarePricingAgreement::PLATFORM_PAYS_PROCESSING,
        ]);
        $beforeBooking = $booking->fresh()->getRawOriginal();

        Livewire::actingAs($family)->withQueryParams(['tab' => 'applicants'])
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSee('Selected caregiver')
            ->assertSee($caregiver->name)
            ->assertDontSee('Rate recorded on application')
            ->assertDontSee('/hr care')
            ->assertDontSee('/hr processing')
            ->assertDontSee('$23.00')
            ->assertDontSee('$2.00')
            ->assertDontSee('$99.00')
            ->assertSet('reviewingApplicationId', null);

        $quote = app(MarketplacePricing::class)->quoteForCurrentBooking($booking, 60);
        $this->assertSame(2300, $quote['family_care_rate_cents']);
        $this->assertSame(200, $quote['family_processing_fee_rate_cents']);
        $this->assertSame(2500, $quote['total_charge_cents']);
        $this->assertSame($beforeBooking, $booking->fresh()->getRawOriginal());
        $this->assertSame(1575, $agreement->fresh()->family_care_rate_cents);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    #[DataProvider('historicalApplications')]
    public function test_historical_applications_can_still_be_reconsidered_and_hired(string $status, string $type): void
    {
        [$family, $request] = $this->requestFixture($type);
        $caregiver = $this->readyCaregiver('Jamie Historical');
        $application = $this->application($request, $caregiver, $status);

        $component = Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setApplicantView', 'past')
            ->assertSee('Reconsider')
            ->call('reviewHire', $application->id)
            ->assertSet('reviewingApplicationId', $application->id);
        $this->assertSame($status, $application->fresh()->status);
        $this->assertDatabaseCount('care_bookings', 0);

        $component->call('confirmReviewedHire')->assertHasNoErrors();

        $this->assertSame(CareRequestApplication::STATUS_HIRED, $application->fresh()->status);
        $this->assertSame(CareRequest::STATUS_FILLED, $request->fresh()->status);
        $this->assertDatabaseHas('care_bookings', [
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'caregiver_user_id' => $caregiver->id,
        ]);
    }

    public static function historicalApplications(): array
    {
        $cases = [];
        foreach ([CareRequest::TYPE_ONE_TIME, CareRequest::TYPE_RECURRING] as $type) {
            foreach ([CareRequestApplication::STATUS_REJECTED, CareRequestApplication::STATUS_WITHDRAWN, CareRequestApplication::STATUS_NOT_SELECTED] as $status) {
                $cases[$type.' '.$status] = [$status, $type];
            }
        }

        return $cases;
    }

    public function test_recurring_hire_preview_uses_the_first_future_visit_after_todays_slot_started(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-11 09:30:00', config('app.timezone')));
        [$family, $request] = $this->requestFixture(CareRequest::TYPE_RECURRING);
        $request->update([
            'requested_start_at' => null,
            'requested_end_at' => null,
            'recurring_starts_on' => now()->toDateString(),
            'recurring_days' => [5, 6],
            'recurring_start_time' => '09:00',
            'recurring_end_time' => '10:00',
            'recurring_schedule' => [
                ['day' => 5, 'start_time' => '09:00', 'end_time' => '10:00'],
                ['day' => 6, 'start_time' => '09:00', 'end_time' => '12:00'],
            ],
        ]);
        $caregiver = $this->readyCaregiver('First Future Visit Caregiver');
        $application = $this->application($request, $caregiver);
        $beforeRequest = $request->fresh()->getRawOriginal();
        $beforeApplication = $application->fresh()->getRawOriginal();

        $component = Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('reviewHire', $application->id)
            ->assertViewHas('hireDecisionQuote', fn ($quote): bool => $quote['minutes'] === 180
                && $quote['care_cents'] === 9000 && $quote['fee_cents'] === 300 && $quote['total_cents'] === 9300)
            ->assertViewHas('hireDecisionStart', fn ($date): bool => $date?->format('Y-m-d H:i:s') === '2026-09-12 09:00:00')
            ->assertSee('First visit')
            ->assertSee('$93.00');

        $this->assertSame($beforeRequest, $request->fresh()->getRawOriginal());
        $this->assertSame($beforeApplication, $application->fresh()->getRawOriginal());
        $this->assertDatabaseCount('care_plans', 0);
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);

        $component->call('confirmReviewedHire')->assertHasNoErrors();
        $booking = $request->fresh()->booking;
        $this->assertSame('2026-09-12 09:00:00', $booking->scheduled_start_at->format('Y-m-d H:i:s'));
        $this->assertSame(180, $booking->expected_minutes);
        $this->assertSame(9300, app(MarketplacePricing::class)->quoteForCurrentBooking($booking, $booking->expected_minutes)['total_charge_cents']);
    }

    public function test_unbooked_drafts_and_selected_requests_keep_factual_status_without_reopening_closed_requests(): void
    {
        foreach ([
            CareRequest::STATUS_DRAFT => 'This request is a draft',
            CareRequest::STATUS_FILLED => 'Caregiver selected. Visit setup is pending.',
            CareRequest::STATUS_CANCELLED => 'This request is closed',
            CareRequest::STATUS_EXPIRED => 'This request is closed',
        ] as $status => $heading) {
            auth()->logout();
            [$family, $request] = $this->requestFixture();
            $request->update(['status' => $status]);
            if ($status === CareRequest::STATUS_FILLED) {
                $this->application($request, $this->readyCaregiver('Selected Before Booking'), CareRequestApplication::STATUS_HIRED);
            }
            $beforeRequest = $request->fresh()->getRawOriginal();
            $beforeApplications = $request->applications()->get()->map->getRawOriginal()->all();
            $component = Livewire::actingAs($family)
                ->test(ManageCareRequest::class, ['careRequest' => $request->id])
                ->assertSet('activeTab', 'home')
                ->assertSee($heading);
            if (in_array($status, [CareRequest::STATUS_DRAFT, CareRequest::STATUS_FILLED], true)) {
                $component->assertDontSee('This request is closed');
            }
            $component->call('setActiveTab', 'start')
                ->assertSee($heading)
                ->assertDontSee('Confirm hire')
                ->assertDontSee('Choose a caregiver to get started');
            if (in_array($status, [CareRequest::STATUS_DRAFT, CareRequest::STATUS_FILLED], true)) {
                $component->assertDontSee('This request is closed');
            }
            $this->assertSame($beforeRequest, $request->fresh()->getRawOriginal());
            $this->assertSame($beforeApplications, $request->applications()->get()->map->getRawOriginal()->all());
            $this->assertDatabaseCount('care_bookings', 0);
        }
    }

    private function requestFixture(string $type = CareRequest::TYPE_ONE_TIME): array
    {
        $family = User::factory()->create(['role' => 'family']);
        $request = $this->requestFor($family, $type);

        return [$family, $request];
    }

    private function requestFor(User $family, string $type = CareRequest::TYPE_ONE_TIME): CareRequest
    {
        $start = now()->addDays(2)->setTime(9, 0);
        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Morning companionship for Eleanor',
            'request_type' => $type,
            'status' => CareRequest::STATUS_OPEN,
            'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
            'address_line1' => '123 Local Test Lane',
            'scope_of_work' => 'Companionship and breakfast preparation.',
            'requested_start_at' => $start,
            'requested_end_at' => $start->copy()->addHours(2),
            'recurring_days' => [$start->dayOfWeek],
            'recurring_start_time' => '09:00',
            'recurring_end_time' => '11:00',
            'recurring_schedule' => [['day' => $start->dayOfWeek, 'start_time' => '09:00', 'end_time' => '11:00']],
            'recurring_starts_on' => $start->toDateString(),
        ]);
        $request->recipient()->create(['full_name' => 'Eleanor', 'relationship_to_family' => 'Mother', 'recipient_is_requester' => false]);

        return $request;
    }

    private function application(CareRequest $request, User $caregiver, string $status = CareRequestApplication::STATUS_APPLIED, float $rate = 30): CareRequestApplication
    {
        return $request->applications()->create([
            'caregiver_user_id' => $caregiver->id,
            'status' => $status,
            'proposed_rate' => $rate,
            'cover_note' => 'Care request application note.',
        ]);
    }

    private function pricingAgreement(User $family, User $caregiver, int $careRate, int $feeRate): CarePricingAgreement
    {
        $source = $this->requestFor($family);
        $booking = CareBooking::query()->create([
            'care_request_id' => $source->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_COMPLETED,
            'scheduled_start_at' => now()->subDays(2)->setTime(9, 0),
            'scheduled_end_at' => now()->subDays(2)->setTime(11, 0),
        ]);

        return CarePricingAgreement::query()->create([
            'family_account_id' => $source->family_account_id,
            'caregiver_user_id' => $caregiver->id,
            'source_booking_id' => $booking->id,
            'created_by_user_id' => $family->id,
            'family_care_rate_cents' => $careRate,
            'family_processing_fee_rate_cents' => $feeRate,
            'caregiver_gross_rate_cents' => $careRate,
            'caregiver_fee_policy' => CarePricingAgreement::PLATFORM_PAYS_PROCESSING,
            'reason' => 'Existing pair agreement used to verify presentation.',
            'active' => true,
        ]);
    }

    private function readyCaregiver(string $name): User
    {
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => $name, 'city' => 'Raleigh', 'state' => 'NC']);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'slug' => str($name)->slug().'-'.$caregiver->id,
            'status' => 'active',
            'bio' => 'Experienced caregiver offering companionship and everyday non-medical help.',
            'platform_hourly_rate' => 30,
            'years_experience' => 5,
            'service_area_zip' => '27601',
            'service_radius_miles' => 15,
            'is_accepting_new_clients' => true,
            'identity_verified_at' => now(),
            'identity_verification_status' => 'approved',
            'average_rating' => 4.8,
            'reviews_count' => 8,
        ]);
        $profile->skills()->attach(Skill::query()->create(['name' => 'Companionship '.$caregiver->id]));
        $profile->languages()->attach(Language::query()->create(['name' => 'English '.$caregiver->id]));
        foreach (range(0, 6) as $day) {
            $profile->availabilities()->create(['day_of_week' => $day, 'start_time' => '08:00', 'end_time' => '18:00']);
        }

        return $caregiver->fresh('caregiverProfile');
    }
}
