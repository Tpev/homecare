<?php

namespace Tests\Feature\Operations;

use App\Livewire\Caregiver\ApplyToCareRequest;
use App\Livewire\Family\ManageCareRequest;
use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CaregiverProfile;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use App\Support\MarketplaceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MarketplaceVelocityEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_applying_and_hiring_record_first_response_and_first_hire_timestamps(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);
        $this->createMarketplaceReadyCaregiverProfile($caregiver, '27601');

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Morning support for parent',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay()->setTime(9, 0),
            'requested_end_at' => now()->addDay()->setTime(12, 0),
            'scope_of_work' => 'Companionship and meal prep.',
            'time_expectations' => 'Arrive 10 minutes early.',
            'home_access_notes' => 'Keypad entry.',
            'address_line1' => '123 Main St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);
        $request->recipient()->create([
            'full_name' => 'Mary Johnson',
            'relationship_to_family' => 'Mother',
            'care_notes' => 'Needs standby support.',
        ]);

        Livewire::actingAs($caregiver)
            ->test(ApplyToCareRequest::class, ['careRequest' => $request->id])
            ->set('cover_note', str_repeat('I can support this request with reliable, non-medical care. ', 2))
            ->call('submit');

        $request->refresh();
        $this->assertNotNull($request->first_applicant_at);

        $application = CareRequestApplication::query()
            ->where('care_request_id', $request->id)
            ->where('caregiver_user_id', $caregiver->id)
            ->firstOrFail();

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $request->refresh();
        $this->assertNotNull($request->first_hire_at);
    }

    public function test_family_can_send_single_invite_from_manage_request_suggestions(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);
        $this->createMarketplaceReadyCaregiverProfile($caregiver, '27601');

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Weekday companionship',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay()->setTime(9, 0),
            'requested_end_at' => now()->addDay()->setTime(13, 0),
            'scope_of_work' => 'Companionship and reminders.',
            'time_expectations' => 'Consistent morning routine.',
            'home_access_notes' => 'Front door key in lockbox.',
            'address_line1' => '501 Oak Ave',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);
        $request->recipient()->create([
            'full_name' => 'Margaret Lane',
            'relationship_to_family' => 'Mother',
            'care_notes' => 'Needs conversation and oversight.',
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('inviteSuggestedCaregiver', $caregiver->id);

        $this->assertDatabaseHas('care_request_invitations', [
            'care_request_id' => $request->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('marketplace_notification_deliveries', [
            'user_id' => $caregiver->id,
            'event_key' => MarketplaceEvent::MATCHING_REQUEST_REMINDER,
        ]);
    }

    public function test_reminder_command_dispatches_matching_and_shift_soon_notifications(): void
    {
        $family = User::factory()->create(['role' => 'family']);

        $matchingCaregiver = User::factory()->create([
            'role' => 'caregiver',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);
        $this->createMarketplaceReadyCaregiverProfile($matchingCaregiver, '27601');

        $openRequest = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Open request waiting on responses',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay()->setTime(8, 0),
            'requested_end_at' => now()->addDay()->setTime(12, 0),
            'address_line1' => '10 River St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);
        $openRequest->recipient()->create([
            'full_name' => 'Request Recipient',
            'relationship_to_family' => 'Mother',
        ]);

        $shiftCaregiver = User::factory()->create([
            'role' => 'caregiver',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);
        $this->createMarketplaceReadyCaregiverProfile($shiftCaregiver, '27602');

        $filledRequest = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Scheduled shift request',
            'status' => CareRequest::STATUS_FILLED,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addHours(2),
            'requested_end_at' => now()->addHours(5),
            'address_line1' => '22 Pine St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27602',
        ]);
        $filledRequest->recipient()->create([
            'full_name' => 'Shift Recipient',
            'relationship_to_family' => 'Father',
        ]);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $filledRequest->id,
            'caregiver_user_id' => $shiftCaregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 30.00,
            'cover_note' => 'Accepted and ready.',
        ]);

        CareBooking::query()->create([
            'care_request_id' => $filledRequest->id,
            'care_request_application_id' => $application->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $shiftCaregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => now()->addMinutes(60),
            'scheduled_end_at' => now()->addMinutes(240),
        ]);

        $this->artisan('homecare:dispatch-notifications --type=all')
            ->assertExitCode(0);

        $this->assertGreaterThan(
            0,
            \App\Models\MarketplaceNotificationDelivery::query()
                ->where('event_key', MarketplaceEvent::MATCHING_REQUEST_REMINDER)
                ->count()
        );
        $this->assertDatabaseHas('marketplace_notification_deliveries', [
            'user_id' => $family->id,
            'event_key' => MarketplaceEvent::SHIFT_STARTING_SOON,
        ]);
        $this->assertDatabaseHas('marketplace_notification_deliveries', [
            'user_id' => $shiftCaregiver->id,
            'event_key' => MarketplaceEvent::SHIFT_STARTING_SOON,
        ]);
    }

    private function createMarketplaceReadyCaregiverProfile(User $caregiver, string $zip): CaregiverProfile
    {
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'slug' => 'caregiver-'.$caregiver->id,
            'status' => 'active',
            'bio' => str_repeat('Experienced non-medical caregiver. ', 3),
            'platform_hourly_rate' => 28,
            'years_experience' => 4,
            'service_area_zip' => $zip,
            'service_radius_miles' => 10,
            'is_accepting_new_clients' => true,
            'insurance_status' => CaregiverProfile::INSURANCE_NO,
            'identity_verified_at' => now(),
            'identity_verification_status' => 'approved',
        ]);

        $skill = Skill::query()->create(['name' => 'Companionship '.$caregiver->id]);
        $language = Language::query()->create(['name' => 'English '.$caregiver->id]);

        $profile->skills()->sync([$skill->id]);
        $profile->languages()->sync([$language->id]);
        foreach (range(0, 6) as $dayOfWeek) {
            $profile->availabilities()->create([
                'day_of_week' => $dayOfWeek,
                'start_time' => '08:00',
                'end_time' => '17:00',
            ]);
        }

        return $profile;
    }
}
