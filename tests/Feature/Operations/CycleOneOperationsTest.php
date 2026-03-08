<?php

namespace Tests\Feature\Operations;

use App\Livewire\Caregiver\ApplyToCareRequest;
use App\Livewire\Family\CreateCareRequestWizard;
use App\Livewire\Family\ManageCareRequest;
use App\Livewire\Support\TicketsCenter;
use App\Models\CareBooking;
use App\Models\CareBookingChangeRequest;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareReview;
use App\Models\CareTask;
use App\Models\CaregiverProfile;
use App\Models\FunnelEvent;
use App\Models\Language;
use App\Models\Skill;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CycleOneOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_hiring_creates_scheduled_booking(): void
    {
        [$family, $caregiver, $request, $application] = $this->seedHirableContext();

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $this->assertDatabaseHas('care_bookings', [
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
        ]);
    }

    public function test_family_can_complete_and_review_booking(): void
    {
        [$family, $caregiver, $request, $application] = $this->seedHirableContext();

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id)
            ->call('startBooking')
            ->call('completeBooking')
            ->set('reviewRating', 5)
            ->set('reviewComment', 'Reliable and kind caregiver.')
            ->call('submitReview');

        $this->assertDatabaseHas('care_bookings', [
            'care_request_id' => $request->id,
            'status' => CareBooking::STATUS_REVIEWED,
        ]);
        $this->assertDatabaseHas('care_reviews', [
            'care_request_id' => $request->id,
            'reviewer_user_id' => $family->id,
            'reviewee_user_id' => $caregiver->id,
            'rating' => 5,
        ]);
    }

    public function test_caregiver_reschedule_request_can_be_accepted_by_family(): void
    {
        [$family, $caregiver, $request, $application] = $this->seedHirableContext();

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        $newStart = now()->addDays(3)->setTime(10, 0)->format('Y-m-d\TH:i');
        $newEnd = now()->addDays(3)->setTime(14, 0)->format('Y-m-d\TH:i');

        Livewire::actingAs($caregiver)
            ->test(ApplyToCareRequest::class, ['careRequest' => $request->id])
            ->set('changeType', CareBookingChangeRequest::TYPE_RESCHEDULE)
            ->set('changeReason', 'Need to shift by one day due to existing assignment.')
            ->set('proposedStartAt', $newStart)
            ->set('proposedEndAt', $newEnd)
            ->call('submitChangeRequest');

        $changeRequest = CareBookingChangeRequest::query()->firstOrFail();

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('resolveChangeRequest', $changeRequest->id, 'accept');

        $this->assertDatabaseHas('care_booking_change_requests', [
            'id' => $changeRequest->id,
            'status' => CareBookingChangeRequest::STATUS_ACCEPTED,
        ]);
        $this->assertDatabaseHas('care_bookings', [
            'care_request_id' => $request->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'last_reschedule_reason' => 'Need to shift by one day due to existing assignment.',
        ]);
    }

    public function test_incomplete_caregiver_cannot_apply_to_request(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);

        CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
            'bio' => 'Short bio but missing key readiness fields.',
        ]);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Need daily support',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay(),
            'requested_end_at' => now()->addDay()->addHours(4),
            'address_line1' => '100 Main St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);

        $this->actingAs($caregiver)
            ->get('/care-requests/'.$request->id.'/apply')
            ->assertForbidden();
    }

    public function test_user_can_create_support_ticket_from_support_center(): void
    {
        $user = User::factory()->create(['role' => 'family']);

        Livewire::actingAs($user)
            ->test(TicketsCenter::class)
            ->set('subject', 'Need help with a cancellation request')
            ->set('description', 'The caregiver and I need admin help to resolve this cancellation flow.')
            ->set('category', 'cancellation')
            ->set('priority', 'high')
            ->call('createTicket');

        $this->assertDatabaseHas('support_tickets', [
            'opener_user_id' => $user->id,
            'category' => 'cancellation',
            'priority' => 'high',
        ]);
    }

    public function test_publishing_request_tracks_funnel_event(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);
        $task = CareTask::query()->create(['name' => 'Companionship']);

        Livewire::actingAs($family)
            ->test(CreateCareRequestWizard::class)
            ->set('title', 'Need evening companionship support')
            ->set('additional_info', 'Request includes routine support and supervision during evening hours.')
            ->set('scope_of_work', 'Companionship, hydration reminders, and mealtime assistance.')
            ->set('time_expectations', 'Arrive at least 10 minutes early.')
            ->set('home_access_notes', 'Use side gate code provided after hiring.')
            ->set('preferred_response_hours', 8)
            ->set('requested_start_at', now()->addDay()->setTime(17, 0)->format('Y-m-d\TH:i'))
            ->set('requested_end_at', now()->addDay()->setTime(20, 0)->format('Y-m-d\TH:i'))
            ->set('address_line1', '222 Pine Rd')
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('zip', '27607')
            ->set('selectedTasks', [$task->id])
            ->call('nextStep')
            ->set('recipient_full_name', 'Jane Smith')
            ->set('recipient_relationship_to_family', 'Mother')
            ->set('recipient_care_notes', 'Needs reminders and supervised walking support.')
            ->call('nextStep')
            ->set('includeThirdPartyContact', false)
            ->call('nextStep')
            ->call('publish');

        $this->assertDatabaseHas('funnel_events', [
            'event' => 'care_request_published',
            'user_id' => $family->id,
        ]);
    }

    private function seedHirableContext(): array
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);

        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
            'bio' => str_repeat('Experienced non-medical caregiver. ', 3),
            'hourly_rate' => 28,
            'years_experience' => 5,
            'service_area_zip' => '27601',
            'service_radius_miles' => 10,
            'slug' => 'ready-caregiver-'.$caregiver->id,
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
            'title' => 'Need support this week',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay()->setTime(9, 0),
            'requested_end_at' => now()->addDay()->setTime(12, 0),
            'scope_of_work' => 'Meal prep and companionship support.',
            'time_expectations' => 'Please arrive 10 minutes early.',
            'home_access_notes' => 'Use front entrance keypad.',
            'address_line1' => '123 Elm',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);
        $request->recipient()->create([
            'full_name' => 'Recipient Name',
            'relationship_to_family' => 'Mother',
            'care_notes' => 'Needs standby support.',
        ]);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_SHORTLISTED,
            'proposed_rate' => 27,
            'cover_note' => str_repeat('Experienced and available. ', 3),
        ]);

        return [$family, $caregiver, $request, $application];
    }
}
