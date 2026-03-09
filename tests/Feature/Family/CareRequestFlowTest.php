<?php

namespace Tests\Feature\Family;

use App\Livewire\Caregiver\ApplyToCareRequest;
use App\Livewire\Family\CreateCareRequestWizard;
use App\Livewire\Family\ManageCareRequest;
use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareTask;
use App\Models\CaregiverProfile;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CareRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_can_publish_care_request_with_recipient_and_third_party_contact(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);

        $taskA = CareTask::query()->create(['name' => 'Companionship']);
        $taskB = CareTask::query()->create(['name' => 'Meal preparation']);

        $startAt = now()->addDay()->setTime(9, 0)->format('Y-m-d\TH:i');
        $endAt = now()->addDay()->setTime(13, 0)->format('Y-m-d\TH:i');

        Livewire::actingAs($family)
            ->test(CreateCareRequestWizard::class)
            ->set('title', 'Need caregiver for Monday afternoon support')
            ->set('additional_info', 'Looking for non-medical help and supervision.')
            ->set('scope_of_work', 'Companionship, meal setup, and mobility supervision for afternoon support.')
            ->set('time_expectations', 'Please arrive 10 minutes early and keep routine timing.')
            ->set('home_access_notes', 'Use side entrance. Parking is available in driveway.')
            ->set('preferred_response_hours', 12)
            ->set('requested_start_at', $startAt)
            ->set('requested_end_at', $endAt)
            ->set('address_line1', '123 Main St')
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('zip', '27601')
            ->set('selectedTasks', [$taskA->id, $taskB->id])
            ->set('taskNotes.'.$taskA->id, 'Prefers board games and walks.')
            ->call('nextStep')
            ->set('recipient_full_name', 'Jane Doe')
            ->set('recipient_relationship_to_family', 'Mother')
            ->set('recipient_care_notes', 'Needs reminders to hydrate and supervision during short walks.')
            ->call('nextStep')
            ->set('includeThirdPartyContact', true)
            ->set('third_party_full_name', 'John Doe')
            ->set('third_party_relationship_to_recipient', 'Son')
            ->set('third_party_phone', '+1 919 555 0101')
            ->set('third_party_email', 'john@example.com')
            ->call('nextStep')
            ->call('publish');

        $careRequest = CareRequest::query()->first();

        $this->assertNotNull($careRequest);
        $this->assertDatabaseHas('care_requests', [
            'id' => $careRequest->id,
            'family_user_id' => $family->id,
            'status' => CareRequest::STATUS_OPEN,
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);
        $this->assertDatabaseHas('care_request_recipients', [
            'care_request_id' => $careRequest->id,
            'full_name' => 'Jane Doe',
        ]);
        $this->assertDatabaseHas('care_request_third_party_contacts', [
            'care_request_id' => $careRequest->id,
            'full_name' => 'John Doe',
        ]);
        $this->assertDatabaseHas('care_request_task', [
            'care_request_id' => $careRequest->id,
            'care_task_id' => $taskA->id,
        ]);
    }

    public function test_active_caregiver_can_apply_to_open_request(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
            'bio' => str_repeat('Experienced caregiver. ', 4),
            'hourly_rate' => 28,
            'years_experience' => 5,
            'service_area_zip' => '27601',
            'service_radius_miles' => 10,
        ]);
        $skill = Skill::query()->create(['name' => 'Companionship']);
        $language = Language::query()->create(['name' => 'English']);
        $profile->skills()->sync([$skill->id]);
        $profile->languages()->sync([$language->id]);
        $profile->availabilities()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '13:00',
        ]);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Morning care needed',
            'status' => CareRequest::STATUS_OPEN,
            'requested_start_at' => now()->addDay()->setTime(8, 0),
            'requested_end_at' => now()->addDay()->setTime(12, 0),
            'address_line1' => '456 Oak Ave',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27603',
        ]);
        $request->recipient()->create([
            'full_name' => 'Recipient Name',
            'relationship_to_family' => 'Father',
        ]);

        Livewire::actingAs($caregiver)
            ->test(ApplyToCareRequest::class, ['careRequest' => $request->id])
            ->set('proposed_rate', 28.50)
            ->set('cover_note', str_repeat('I have relevant home care experience. ', 3))
            ->call('submit');

        $this->assertDatabaseHas('care_request_applications', [
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 28.50,
        ]);
    }

    public function test_family_can_hire_applicant_and_request_is_marked_filled(): void
    {
        $family = User::factory()->create(['role' => 'family']);

        $caregiverA = User::factory()->create(['role' => 'caregiver']);
        $caregiverB = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::query()->create(['user_id' => $caregiverA->id, 'status' => 'active']);
        CaregiverProfile::query()->create(['user_id' => $caregiverB->id, 'status' => 'active']);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Evening support needed',
            'status' => CareRequest::STATUS_OPEN,
            'requested_start_at' => now()->addDays(2)->setTime(16, 0),
            'requested_end_at' => now()->addDays(2)->setTime(20, 0),
            'address_line1' => '789 Pine St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27604',
        ]);
        $request->recipient()->create([
            'full_name' => 'Senior Name',
            'relationship_to_family' => 'Grandmother',
        ]);

        $applicationA = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiverA->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 27,
            'cover_note' => 'Can start this week.',
        ]);
        $applicationB = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiverB->id,
            'status' => CareRequestApplication::STATUS_SHORTLISTED,
            'proposed_rate' => 29,
            'cover_note' => 'Flexible evenings.',
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $applicationB->id);

        $this->assertDatabaseHas('care_requests', [
            'id' => $request->id,
            'status' => CareRequest::STATUS_FILLED,
        ]);
        $this->assertDatabaseHas('care_request_applications', [
            'id' => $applicationB->id,
            'status' => CareRequestApplication::STATUS_HIRED,
        ]);
        $this->assertDatabaseHas('care_request_applications', [
            'id' => $applicationA->id,
            'status' => CareRequestApplication::STATUS_NOT_SELECTED,
        ]);
        $this->assertDatabaseHas('care_request_conversations', [
            'care_request_id' => $request->id,
            'care_request_application_id' => $applicationB->id,
            'caregiver_user_id' => $caregiverB->id,
            'family_user_id' => $family->id,
        ]);
    }

    public function test_family_can_hire_applicant_for_recurring_request_and_create_scheduled_booking(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::query()->create(['user_id' => $caregiver->id, 'status' => 'active']);

        $recurringStartsOn = now()->addDays(2)->startOfDay();

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Recurring daytime support',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_RECURRING,
            'recurring_days' => [1, 3, 5],
            'recurring_starts_on' => $recurringStartsOn,
            'recurring_start_time' => '09:00:00',
            'recurring_end_time' => '12:30:00',
            'address_line1' => '42 Cedar Ave',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27607',
        ]);
        $request->recipient()->create([
            'full_name' => 'Parent Name',
            'relationship_to_family' => 'Mother',
        ]);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 30,
            'cover_note' => 'Can do recurring mornings.',
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $request = $request->fresh('booking');

        $this->assertNotNull($request->booking);
        $this->assertSame('scheduled', $request->booking->status);
        $this->assertSame(
            $recurringStartsOn->copy()->setTime(9, 0, 0)->format('Y-m-d H:i:s'),
            $request->booking->scheduled_start_at?->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            $recurringStartsOn->copy()->setTime(12, 30, 0)->format('Y-m-d H:i:s'),
            $request->booking->scheduled_end_at?->format('Y-m-d H:i:s')
        );
    }

    public function test_family_cannot_complete_shift_before_caregiver_check_in(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::query()->create(['user_id' => $caregiver->id, 'status' => 'active']);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Morning assistance',
            'status' => CareRequest::STATUS_OPEN,
            'requested_start_at' => now()->addDays(2)->setTime(9, 0),
            'requested_end_at' => now()->addDays(2)->setTime(12, 0),
            'address_line1' => '12 Maple St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);
        $request->recipient()->create([
            'full_name' => 'Family Recipient',
            'relationship_to_family' => 'Mother',
        ]);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 29,
            'cover_note' => 'Available this week.',
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id)
            ->call('completeBooking');

        $booking = CareBooking::query()->where('care_request_id', $request->id)->first();
        $this->assertNotNull($booking);
        $this->assertSame(CareBooking::STATUS_SCHEDULED, $booking?->status);
        $this->assertNull($booking?->completed_at);
    }

    public function test_non_family_user_cannot_access_family_routes(): void
    {
        $caregiver = User::factory()->create(['role' => 'caregiver']);

        $response = $this->actingAs($caregiver)->get('/family/requests');

        $response->assertForbidden();
    }

    public function test_family_can_publish_recurring_care_request(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);

        $task = CareTask::query()->create(['name' => 'Companionship']);

        Livewire::actingAs($family)
            ->test(CreateCareRequestWizard::class)
            ->set('request_type', CareRequest::TYPE_RECURRING)
            ->set('title', 'Recurring weekday morning support')
            ->set('additional_info', 'Needs recurring support for morning routine.')
            ->set('scope_of_work', 'Morning routine setup, companionship, and non-medical safety supervision.')
            ->set('time_expectations', 'Arrive by 8:55 and follow medicine reminder schedule.')
            ->set('home_access_notes', 'Keypad entry code provided after hire.')
            ->set('preferred_response_hours', 10)
            ->set('recurring_days', [1, 3, 5])
            ->set('recurring_start_time', '09:00')
            ->set('recurring_end_time', '12:00')
            ->set('recurring_starts_on', now()->addDay()->toDateString())
            ->set('recurring_ends_on', now()->addMonths(2)->toDateString())
            ->set('address_line1', '900 Elm St')
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('zip', '27605')
            ->set('selectedTasks', [$task->id])
            ->call('nextStep')
            ->set('recipient_full_name', 'Mary Doe')
            ->set('recipient_relationship_to_family', 'Mother')
            ->set('recipient_care_notes', 'Needs standby support from bed to chair in mornings.')
            ->call('nextStep')
            ->set('includeThirdPartyContact', false)
            ->call('nextStep')
            ->call('publish');

        $careRequest = CareRequest::query()->latest('id')->first();

        $this->assertNotNull($careRequest);
        $this->assertSame(CareRequest::TYPE_RECURRING, $careRequest->request_type);
        $this->assertSame([1, 3, 5], $careRequest->recurring_days);
        $this->assertSame('09:00', $careRequest->recurring_start_time);
        $this->assertSame('12:00', $careRequest->recurring_end_time);
        $this->assertNull($careRequest->requested_start_at);
        $this->assertNull($careRequest->requested_end_at);
    }
}
