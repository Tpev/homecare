<?php

namespace Tests\Feature\Family;

use App\Exceptions\Payments\PaymentException;
use App\Livewire\Caregiver\ApplyToCareRequest;
use App\Livewire\Family\CreateCareRequestWizard;
use App\Livewire\Family\ManageCareRequest;
use App\Mail\Ops\NewCareRequestOpsAlertMail;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareRequestInvitation;
use App\Models\CareReview;
use App\Models\CareTask;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use App\Services\Payments\BookingPaymentService;
use App\Support\CareRequestProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class CareRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_response_metrics_never_show_negative_time(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Morning companionship',
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'status' => CareRequest::STATUS_FILLED,
            'requested_start_at' => now()->addDay()->setTime(9, 0),
            'requested_end_at' => now()->addDay()->setTime(12, 0),
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
            'address_line1' => '100 Main St',
            'preferred_response_hours' => 12,
        ]);

        $request->forceFill([
            'created_at' => now(),
            'first_applicant_at' => now()->subMinutes(30),
            'first_hire_at' => now()->subMinutes(15),
        ])->save();

        $request->refresh();

        $this->assertSame('0m', CareRequestProgress::firstResponseLabel($request));
        $this->assertSame('0m', CareRequestProgress::firstHireLabel($request));

        $this->actingAs($family)
            ->get(route('family.requests.index'))
            ->assertOk()
            ->assertSee('0m')
            ->assertDontSee('First response -')
            ->assertDontSee('First hire -');
    }

    public function test_family_care_hub_uses_state_specific_action_labels(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);

        $openRequest = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Review caregiver label',
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'status' => CareRequest::STATUS_OPEN,
            'requested_start_at' => now()->addDay()->setTime(9, 0),
            'requested_end_at' => now()->addDay()->setTime(11, 0),
            'city' => 'Durham',
            'state' => 'NC',
            'zip' => '27703',
            'address_line1' => '10 Main St',
        ]);

        CareRequestApplication::query()->create([
            'care_request_id' => $openRequest->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 30,
        ]);

        $scheduledRequest = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Open visit label',
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'status' => CareRequest::STATUS_FILLED,
            'requested_start_at' => now()->addDays(2)->setTime(10, 0),
            'requested_end_at' => now()->addDays(2)->setTime(12, 0),
            'city' => 'Durham',
            'state' => 'NC',
            'zip' => '27703',
            'address_line1' => '20 Main St',
        ]);

        $scheduledApplication = CareRequestApplication::query()->create([
            'care_request_id' => $scheduledRequest->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 30,
        ]);

        CareBooking::query()->create([
            'care_request_id' => $scheduledRequest->id,
            'care_request_application_id' => $scheduledApplication->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => now()->addDays(2)->setTime(10, 0),
            'scheduled_end_at' => now()->addDays(2)->setTime(12, 0),
        ]);

        $completedRequest = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Review hours label',
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'status' => CareRequest::STATUS_FILLED,
            'requested_start_at' => now()->subDay()->setTime(10, 0),
            'requested_end_at' => now()->subDay()->setTime(12, 0),
            'city' => 'Durham',
            'state' => 'NC',
            'zip' => '27703',
            'address_line1' => '30 Main St',
        ]);

        $completedApplication = CareRequestApplication::query()->create([
            'care_request_id' => $completedRequest->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 30,
        ]);

        CareBooking::query()->create([
            'care_request_id' => $completedRequest->id,
            'care_request_application_id' => $completedApplication->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_COMPLETED,
            'scheduled_start_at' => now()->subDay()->setTime(10, 0),
            'scheduled_end_at' => now()->subDay()->setTime(12, 0),
            'completed_at' => now()->subDay()->setTime(12, 0),
            'timesheet_submitted_at' => now()->subDay()->setTime(12, 10),
            'worked_minutes' => 120,
        ]);

        $this->actingAs($family)
            ->get(route('family.requests.index'))
            ->assertOk()
            ->assertSee('Review caregivers')
            ->assertSee('Open visit')
            ->assertSee('Review hours')
            ->assertDontSee('>Open</a>', false);
    }

    public function test_family_rebooking_stays_available_without_cluttering_care_overview(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caroline = User::factory()->create(['role' => 'caregiver', 'name' => 'Caroline Petrini-Poli']);
        $bob = User::factory()->create(['role' => 'caregiver', 'name' => 'Bob Helper']);

        $this->createCompletedRebookSource($family, $caroline, 'Older visit with Caroline', now()->subWeeks(2));
        $newerCaroline = $this->createCompletedRebookSource($family, $caroline, 'Newer visit with Caroline', now()->subWeek());
        $bobVisit = $this->createCompletedRebookSource($family, $bob, 'Visit with Bob', now()->subDays(3));

        $dashboardHtml = $this->actingAs($family)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($dashboardHtml, 'Book Caroline again'));
        $this->assertSame(1, substr_count($dashboardHtml, 'Book Bob again'));

        $this->actingAs($family)
            ->get(route('family.requests.index'))
            ->assertOk()
            ->assertDontSee('Book Caroline again')
            ->assertDontSee('Book Bob again');

        foreach ([$newerCaroline, $bobVisit] as $sourceVisit) {
            $this->actingAs($family)
                ->get(route('family.requests.book_again', $sourceVisit))
                ->assertOk()
                ->assertSee('One visit or regular care—without starting over.')
                ->assertSee('Set up regular care');
        }
    }

    public function test_assigned_caregiver_identity_is_visible_on_family_request_cards(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'name' => 'Charles Petrini-Poli',
        ]);
        CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'profile_photo_path' => 'caregiver-photos/charles.jpg',
            'status' => 'active',
        ]);

        $request = $this->createCompletedRebookSource($family, $caregiver, 'Visit with assigned caregiver', now()->subDay());

        $this->actingAs($family)
            ->get(route('family.requests.show', ['careRequest' => $request, 'tab' => 'applicants']))
            ->assertOk()
            ->assertSee('Caregivers who replied')
            ->assertSee('Charles Petrini-Poli')
            ->assertSee('Hired')
            ->assertSee('/storage/caregiver-photos/charles.jpg', false);
    }

    public function test_request_detail_lands_on_the_right_lifecycle_screen(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Caroline Petrini-Poli']);

        $openRequest = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Need companionship replies',
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'status' => CareRequest::STATUS_OPEN,
            'requested_start_at' => now()->addDay()->setTime(9, 0),
            'requested_end_at' => now()->addDay()->setTime(12, 0),
            'city' => 'Durham',
            'state' => 'NC',
            'zip' => '27703',
            'address_line1' => '1520 Home Creek Drive',
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $openRequest->id])
            ->assertSet('activeTab', 'applicants')
            ->assertSee('Finding care')
            ->assertSee('Your request is live.')
            ->assertSee('Find matching caregivers')
            ->assertSee('Suggested caregivers')
            ->assertSee('Invite one or two caregivers')
            ->assertSee('After a caregiver replies, this screen changes to compare, chat, and hire.')
            ->assertSee('Caregivers')
            ->assertSee('Invite matching people')
            ->assertSee('Care details')
            ->assertDontSee('At a glance')
            ->assertDontSee('Invite, chat, hire')
            ->assertDontSee('No caregivers have replied yet.')
            ->assertDontSee('Filter or sort caregivers')
            ->assertDontSee('More timing details')
            ->assertDontSee('Need to stop this request?')
            ->assertDontSee('Request options')
            ->assertDontSee('Time, location, payment');

        $reviewRequest = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Choose caregiver request',
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'status' => CareRequest::STATUS_OPEN,
            'requested_start_at' => now()->addDays(2)->setTime(9, 0),
            'requested_end_at' => now()->addDays(2)->setTime(12, 0),
            'city' => 'Durham',
            'state' => 'NC',
            'zip' => '27703',
            'address_line1' => '1520 Home Creek Drive',
        ]);
        CareRequestApplication::query()->create([
            'care_request_id' => $reviewRequest->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 30,
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $reviewRequest->id])
            ->assertSet('activeTab', 'applicants')
            ->assertSee('Choose caregiver')
            ->assertSee('Caregivers are ready for review.')
            ->assertSee('Ready to choose')
            ->assertSee('Hire Caroline')
            ->assertSee('Chat first')
            ->assertDontSee('Save & chat')
            ->assertDontSee('Filter or sort caregivers')
            ->assertSee('Need more choices?')
            ->assertSee('Invite more caregivers')
            ->assertSee('Review, chat, hire')
            ->assertDontSee('At a glance')
            ->assertDontSee('Request options')
            ->assertDontSee('Invite, chat, hire');

        $secondCaregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Michael Rivera']);
        $multiReviewRequest = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Compare caregiver request',
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'status' => CareRequest::STATUS_OPEN,
            'requested_start_at' => now()->addDays(2)->setTime(13, 0),
            'requested_end_at' => now()->addDays(2)->setTime(15, 0),
            'city' => 'Durham',
            'state' => 'NC',
            'zip' => '27703',
            'address_line1' => '1520 Home Creek Drive',
        ]);
        CareRequestApplication::query()->create([
            'care_request_id' => $multiReviewRequest->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 30,
        ]);
        CareRequestApplication::query()->create([
            'care_request_id' => $multiReviewRequest->id,
            'caregiver_user_id' => $secondCaregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 30,
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $multiReviewRequest->id])
            ->assertSet('activeTab', 'applicants')
            ->assertSee('2 caregivers replied')
            ->assertSee('Review each card below')
            ->assertDontSee('Compare caregivers')
            ->assertDontSee('Filter or sort caregivers')
            ->assertSee('Hire Caroline')
            ->assertSee('Hire Michael')
            ->assertDontSee('Ready to choose');

        $scheduledRequest = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Scheduled visit request',
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'status' => CareRequest::STATUS_FILLED,
            'requested_start_at' => now()->addDays(3)->setTime(10, 0),
            'requested_end_at' => now()->addDays(3)->setTime(12, 0),
            'city' => 'Durham',
            'state' => 'NC',
            'zip' => '27703',
            'address_line1' => '1520 Home Creek Drive',
        ]);
        $scheduledApplication = CareRequestApplication::query()->create([
            'care_request_id' => $scheduledRequest->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 30,
        ]);
        CareBooking::query()->create([
            'care_request_id' => $scheduledRequest->id,
            'care_request_application_id' => $scheduledApplication->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => now()->addDays(3)->setTime(10, 0),
            'scheduled_end_at' => now()->addDays(3)->setTime(12, 0),
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $scheduledRequest->id])
            ->assertSet('activeTab', 'shift')
            ->assertSee('Visit scheduled')
            ->assertSee('Your visit is scheduled.')
            ->assertSee('Right now')
            ->assertSee('Caroline is coming')
            ->assertSee('Message caregiver')
            ->assertSee('Change or cancel')
            ->assertSee('Before the visit')
            ->assertSee('Visit plan')
            ->assertSee('Map and visit record')
            ->assertSee('Profile, chat')
            ->assertSee('Time, location, payment')
            ->assertSee('Change or get help')
            ->assertDontSee('At a glance')
            ->assertDontSee('Invite, chat, hire')
            ->assertSee('If the caregiver is late')
            ->assertDontSee('Book Caroline again')
            ->assertDontSee('No-show rule')
            ->assertDontSee('Cancel this scheduled visit')
            ->assertDontSee('Task completion snapshot')
            ->call('setActiveTab', 'support')
            ->assertSee('Change or cancel this visit')
            ->assertSee('Request cancellation or reschedule')
            ->assertSee('Need to cancel now?');

        $completedRequest = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Timesheet review request',
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'status' => CareRequest::STATUS_FILLED,
            'requested_start_at' => now()->subDay()->setTime(10, 0),
            'requested_end_at' => now()->subDay()->setTime(12, 0),
            'city' => 'Durham',
            'state' => 'NC',
            'zip' => '27703',
            'address_line1' => '1520 Home Creek Drive',
        ]);
        $completedApplication = CareRequestApplication::query()->create([
            'care_request_id' => $completedRequest->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 30,
        ]);
        CareBooking::query()->create([
            'care_request_id' => $completedRequest->id,
            'care_request_application_id' => $completedApplication->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_COMPLETED,
            'scheduled_start_at' => now()->subDay()->setTime(10, 0),
            'scheduled_end_at' => now()->subDay()->setTime(12, 0),
            'completed_at' => now()->subDay()->setTime(12, 0),
            'timesheet_submitted_at' => now()->subDay()->setTime(12, 10),
            'worked_minutes' => 120,
        ]);

        $completedComponent = Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $completedRequest->id])
            ->assertSet('activeTab', 'shift')
            ->assertSee('Review hours')
            ->assertSee('Caregiver hours need your review.')
            ->assertSee('Submitted hours')
            ->assertSee('Estimated payment')
            ->assertSee('Approve hours and pay')
            ->assertSee('Question hours')
            ->assertSee('Visit details before approval')
            ->assertDontSee('Review hours and payment')
            ->assertSee('Profile, chat')
            ->assertDontSee('At a glance')
            ->assertDontSee('Review caregiver timesheet')
            ->assertDontSee('Invite, chat, hire');

        $completedComponent
            ->call('setActiveTab', 'support')
            ->assertSee('Question hours or payment')
            ->assertSee('Question the submitted hours')
            ->assertDontSee('Request cancellation or reschedule')
            ->set('changeReason', 'Trying to reschedule after the visit is complete.')
            ->call('submitChangeRequest')
            ->assertSee('Visit changes are only available before caregiver check-in.');

        $this->assertDatabaseCount('care_booking_change_requests', 0);

        $reviewedRequest = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Reviewed visit request',
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'status' => CareRequest::STATUS_FILLED,
            'requested_start_at' => now()->subDays(2)->setTime(10, 0),
            'requested_end_at' => now()->subDays(2)->setTime(12, 0),
            'city' => 'Durham',
            'state' => 'NC',
            'zip' => '27703',
            'address_line1' => '1520 Home Creek Drive',
        ]);
        $reviewedApplication = CareRequestApplication::query()->create([
            'care_request_id' => $reviewedRequest->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 30,
        ]);
        $reviewedBooking = CareBooking::query()->create([
            'care_request_id' => $reviewedRequest->id,
            'care_request_application_id' => $reviewedApplication->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_REVIEWED,
            'scheduled_start_at' => now()->subDays(2)->setTime(10, 0),
            'scheduled_end_at' => now()->subDays(2)->setTime(12, 0),
            'started_at' => now()->subDays(2)->setTime(10, 0),
            'completed_at' => now()->subDays(2)->setTime(12, 0),
            'timesheet_submitted_at' => now()->subDays(2)->setTime(12, 10),
            'worked_minutes' => 120,
            'family_confirmed_at' => now()->subDays(2)->setTime(13, 0),
        ]);
        CareReview::query()->create([
            'care_request_id' => $reviewedRequest->id,
            'care_booking_id' => $reviewedBooking->id,
            'reviewer_user_id' => $family->id,
            'reviewee_user_id' => $caregiver->id,
            'rating' => 5,
            'comment' => 'Caroline was calm and kind.',
        ]);
        CareReview::query()->create([
            'care_request_id' => $reviewedRequest->id,
            'care_booking_id' => $reviewedBooking->id,
            'reviewer_user_id' => $caregiver->id,
            'reviewee_user_id' => $family->id,
            'rating' => 5,
            'comment' => 'Everything was ready when I arrived.',
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $reviewedRequest->id])
            ->assertSet('activeTab', 'shift')
            ->assertSee('Visit complete')
            ->assertSee('This visit is complete.')
            ->assertSee('Final details')
            ->assertSee('Visit receipt')
            ->assertSee('Reviews')
            ->assertSee('Your review')
            ->assertSee('Caregiver feedback')
            ->assertSee('Detailed visit record')
            ->assertSee('Open map')
            ->assertDontSee('Payment status:')
            ->assertDontSee('Visit service location map')
            ->assertSee('Book Caroline again')
            ->assertSee('Profile, chat')
            ->assertDontSee('At a glance')
            ->assertDontSee('Invite, chat, hire')
            ->assertDontSee('More timing details')
            ->assertDontSee('Task completion snapshot');

        $reviewedHtml = $this->actingAs($family)
            ->get(route('family.requests.show', $reviewedRequest->id))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($reviewedHtml, 'Book Caroline again'));
    }

    public function test_family_next_action_distinguishes_scheduled_and_active_visits(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Caroline Petrini-Poli']);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Clear visit state',
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'status' => CareRequest::STATUS_FILLED,
            'requested_start_at' => now()->addHours(2),
            'requested_end_at' => now()->addHours(4),
            'city' => 'Durham',
            'state' => 'NC',
            'zip' => '27703',
            'address_line1' => '1520 Home Creek Drive',
        ]);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 30,
        ]);

        $booking = CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => now()->addHours(2),
            'scheduled_end_at' => now()->addHours(4),
        ]);

        $this->assertSame('Visit is scheduled', CareRequestProgress::bestNextAction($request->fresh('booking'))['title']);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSee('Your visit is scheduled.')
            ->assertSee('Caroline is coming')
            ->assertDontSee('Visit is active');

        $booking->update([
            'status' => CareBooking::STATUS_IN_PROGRESS,
            'started_at' => now()->subMinutes(15),
        ]);

        $this->assertSame('Visit is happening now', CareRequestProgress::bestNextAction($request->fresh('booking'))['title']);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSee('Care is happening now.')
            ->assertSee('Caroline checked in')
            ->assertSee('Live visit details')
            ->assertSee('Live check-in')
            ->assertSee('Map and visit record')
            ->assertSee('Message caregiver')
            ->assertSee('The visit has ended')
            ->assertSee('Get help')
            ->assertDontSee('Caregiver is checked in')
            ->assertDontSee('Visit is scheduled');
    }

    public function test_family_can_publish_care_request_with_recipient_and_third_party_contact(): void
    {
        Mail::fake();

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

        Mail::assertSent(NewCareRequestOpsAlertMail::class, function (NewCareRequestOpsAlertMail $mail) use ($careRequest) {
            return $mail->careRequest->is($careRequest)
                && $mail->hasTo('peverelli.t@gmail.com')
                && $mail->hasTo('hello@carelolo.com');
        });
    }

    public function test_active_caregiver_can_apply_to_open_request(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
            'bio' => str_repeat('Experienced caregiver. ', 4),
            'platform_hourly_rate' => 28.50,
            'years_experience' => 5,
            'service_area_zip' => '27601',
            'service_radius_miles' => 10,
            'insurance_status' => CaregiverProfile::INSURANCE_NO,
            'identity_verified_at' => now(),
            'identity_verification_status' => 'approved',
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
            'recipient_is_requester' => true,
            'full_name' => $family->name,
            'relationship_to_family' => 'Self',
        ]);

        Livewire::actingAs($caregiver)
            ->test(ApplyToCareRequest::class, ['careRequest' => $request->id])
            ->assertSee('Requester receives care')
            ->assertSee('The person posting is also receiving care.')
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

    public function test_failed_hire_payment_keeps_request_open_on_current_screen(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'name' => 'Charles Petrini-Poli',
        ]);
        CaregiverProfile::query()->create(['user_id' => $caregiver->id, 'status' => 'active']);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'One-time care support for Companionship',
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'status' => CareRequest::STATUS_OPEN,
            'requested_start_at' => now()->addDays(2)->setTime(15, 5),
            'requested_end_at' => now()->addDays(2)->setTime(18, 5),
            'address_line1' => '123 Test St',
            'city' => 'Willer-sur-Thur',
            'state' => 'AR',
            'zip' => '12345',
        ]);
        $request->recipient()->create([
            'full_name' => 'Care Recipient',
            'relationship_to_family' => 'Self',
        ]);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_SHORTLISTED,
            'proposed_rate' => 30,
            'cover_note' => 'We think your profile could be a strong fit for this request.',
        ]);

        $this->mock(BookingPaymentService::class, function ($mock): void {
            $mock->shouldReceive('prepareOnSessionAuthorization')
                ->once()
                ->andThrow(new PaymentException('Add a payment method before hiring. Open Billing & Payments from your account menu.'));
        });

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSee('Caregivers are ready for review.')
            ->assertSee('Hire Charles')
            ->call('hire', $application->id)
            ->assertSee('Add a payment method before hiring.')
            ->assertSee('Caregivers are ready for review.')
            ->assertSee('Hire Charles')
            ->assertDontSee('Caregiver selected. Visit setup is next.');

        $this->assertDatabaseHas('care_requests', [
            'id' => $request->id,
            'status' => CareRequest::STATUS_OPEN,
            'first_hire_at' => null,
        ]);
        $this->assertDatabaseHas('care_request_applications', [
            'id' => $application->id,
            'status' => CareRequestApplication::STATUS_SHORTLISTED,
        ]);
        $this->assertDatabaseMissing('care_bookings', [
            'care_request_id' => $request->id,
        ]);
    }

    public function test_family_request_page_repairs_open_status_when_booking_already_exists(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'name' => 'Charles Petrini-Poli',
        ]);
        CaregiverProfile::query()->create(['user_id' => $caregiver->id, 'status' => 'active']);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'One-time care support for Companionship',
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'status' => CareRequest::STATUS_OPEN,
            'requested_start_at' => now()->addDays(2)->setTime(15, 5),
            'requested_end_at' => now()->addDays(2)->setTime(18, 5),
            'address_line1' => '123 Test St',
            'city' => 'Willer-sur-Thur',
            'state' => 'AR',
            'zip' => '12345',
        ]);
        $request->recipient()->create([
            'full_name' => 'Care Recipient',
            'relationship_to_family' => 'Self',
        ]);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_SHORTLISTED,
            'proposed_rate' => 30,
            'cover_note' => 'We think your profile could be a strong fit for this request.',
        ]);

        CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => $request->requested_start_at,
            'scheduled_end_at' => $request->requested_end_at,
            'expected_minutes' => 180,
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSee('Your visit is scheduled.')
            ->assertSee('VISIT Scheduled')
            ->assertDontSee('Hire Charles');

        $this->assertDatabaseHas('care_requests', [
            'id' => $request->id,
            'status' => CareRequest::STATUS_FILLED,
        ]);
        $this->assertDatabaseHas('care_request_applications', [
            'id' => $application->id,
            'status' => CareRequestApplication::STATUS_HIRED,
        ]);
    }

    public function test_family_can_save_and_chat_with_applicant_before_hiring(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::query()->create(['user_id' => $caregiver->id, 'status' => 'active']);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Chat before hire request',
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'status' => CareRequest::STATUS_OPEN,
            'requested_start_at' => now()->addDay()->setTime(10, 0),
            'requested_end_at' => now()->addDay()->setTime(12, 0),
            'city' => 'Durham',
            'state' => 'NC',
            'zip' => '27703',
            'address_line1' => '1520 Home Creek Drive',
        ]);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 30,
        ]);

        $component = Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSee('Chat first')
            ->assertDontSee('Save & chat')
            ->assertDontSee('Caregiver selection')
            ->call('startConversation', $application->id);

        $conversation = CareRequestConversation::query()
            ->where('care_request_application_id', $application->id)
            ->firstOrFail();

        $component->assertRedirect(route('messages.show', $conversation->id, false));

        $this->assertDatabaseHas('care_request_applications', [
            'id' => $application->id,
            'status' => CareRequestApplication::STATUS_SHORTLISTED,
        ]);
    }

    public function test_closed_request_still_links_to_caregiver_profile_from_applicants(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);
        $caregiver = User::factory()->create([
            'name' => 'Caroline Hill',
            'role' => 'caregiver',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);

        $skill = Skill::query()->create(['name' => 'Companionship']);
        $language = Language::query()->create(['name' => 'English']);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'slug' => 'caroline-hill',
            'bio' => 'Caroline has deep experience with calm morning routines, companionship, and meal preparation.',
            'years_experience' => 7,
            'status' => 'active',
            'average_rating' => 4.9,
            'reviews_count' => 12,
            'platform_hourly_rate' => 30,
            'identity_verified_at' => now(),
            'background_check_verified_at' => now(),
            'reliability_score' => 98,
            'is_accepting_new_clients' => true,
        ]);
        $profile->skills()->sync([$skill->id]);
        $profile->languages()->sync([$language->id]);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Closed request with selected caregiver',
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'status' => CareRequest::STATUS_FILLED,
            'requested_start_at' => now()->addDay()->setTime(9, 0),
            'requested_end_at' => now()->addDay()->setTime(12, 0),
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
            'address_line1' => '100 Main St',
            'preferred_response_hours' => 12,
        ]);

        CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'cover_note' => 'I already know the morning routine.',
            'proposed_rate' => 30,
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setActiveTab', 'applicants')
            ->assertSee('Caroline Hill is hired for this visit')
            ->assertSee('Caroline has deep experience with calm morning routines')
            ->assertSee('Companionship')
            ->assertSee('Languages: English')
            ->assertSee('View profile')
            ->assertSee(route('caregivers.show', 'caroline-hill'), false);
    }

    public function test_family_can_withdraw_open_request_and_close_pending_flow(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $applicant = User::factory()->create(['role' => 'caregiver']);
        $invitedCaregiver = User::factory()->create(['role' => 'caregiver']);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Request to withdraw',
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'status' => CareRequest::STATUS_OPEN,
            'requested_start_at' => now()->addDay()->setTime(9, 0),
            'requested_end_at' => now()->addDay()->setTime(12, 0),
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
            'address_line1' => '100 Main St',
        ]);
        $request->recipient()->create([
            'full_name' => 'Care Recipient',
            'relationship_to_family' => 'Mother',
        ]);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $applicant->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'cover_note' => 'I can help with this request.',
            'proposed_rate' => 30,
        ]);

        $invitation = CareRequestInvitation::query()->create([
            'care_request_id' => $request->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $invitedCaregiver->id,
            'status' => CareRequestInvitation::STATUS_PENDING,
            'expires_at' => now()->addHours(24),
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertDontSee('Withdraw request')
            ->call('setActiveTab', 'overview')
            ->assertSee('Withdraw request')
            ->call('withdrawRequest')
            ->assertSee('Request withdrawn');

        $this->assertDatabaseHas('care_requests', [
            'id' => $request->id,
            'status' => CareRequest::STATUS_CANCELLED,
        ]);
        $this->assertDatabaseHas('care_request_applications', [
            'id' => $application->id,
            'status' => CareRequestApplication::STATUS_NOT_SELECTED,
        ]);
        $this->assertDatabaseHas('care_request_invitations', [
            'id' => $invitation->id,
            'status' => CareRequestInvitation::STATUS_CANCELLED,
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
        $firstScheduledDay = $recurringStartsOn->copy();
        while (! in_array($firstScheduledDay->dayOfWeek, [1, 3, 5], true)) {
            $firstScheduledDay->addDay();
        }

        $this->assertNotNull($request->booking);
        $this->assertSame('scheduled', $request->booking->status);
        $this->assertSame(
            $firstScheduledDay->copy()->setTime(9, 0, 0)->format('Y-m-d H:i:s'),
            $request->booking->scheduled_start_at?->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            $firstScheduledDay->copy()->setTime(12, 30, 0)->format('Y-m-d H:i:s'),
            $request->booking->scheduled_end_at?->format('Y-m-d H:i:s')
        );
        $plan = \App\Models\CarePlan::query()->where('source_care_request_id', $request->id)->firstOrFail();
        $this->assertSame(\App\Models\CarePlan::STATUS_ACTIVE, $plan->status);
        $this->assertGreaterThan(1, $plan->generatedBookings()->count());
        $this->assertSame(
            $plan->generatedBookings()->count(),
            $plan->generatedBookings()->distinct('occurrence_key')->count('occurrence_key')
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

    public function test_family_can_confirm_timesheet_when_booking_status_is_reviewed(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::query()->create(['user_id' => $caregiver->id, 'status' => 'active']);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Reviewed booking confirmation',
            'status' => CareRequest::STATUS_FILLED,
            'requested_start_at' => now()->addDay()->setTime(9, 0),
            'requested_end_at' => now()->addDay()->setTime(12, 0),
            'address_line1' => '44 Oak St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);
        $request->recipient()->create([
            'full_name' => 'Recipient Person',
            'relationship_to_family' => 'Mother',
        ]);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 30,
            'cover_note' => 'Ready to help.',
        ]);

        $booking = CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_REVIEWED,
            'scheduled_start_at' => now()->subHours(4),
            'scheduled_end_at' => now()->subHour(),
            'started_at' => now()->subHours(4),
            'completed_at' => now()->subHour(),
            'timesheet_submitted_at' => now()->subHour(),
            'worked_minutes' => 180,
            'family_confirmed_at' => null,
        ]);

        CareBookingPayment::query()->create([
            'care_booking_id' => $booking->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => 'authorized',
            'amount_authorized_cents' => 9000,
            'amount_captured_cents' => 0,
            'currency' => 'usd',
        ]);

        $paymentService = Mockery::mock(BookingPaymentService::class);
        $paymentService->shouldReceive('captureForBooking')->once()->withArgs(function (CareBooking $capturedBooking) use ($booking) {
            return $capturedBooking->id === $booking->id;
        });
        app()->instance(BookingPaymentService::class, $paymentService);

        $this->assertSame(
            'Hours need your approval',
            CareRequestProgress::bestNextAction($request->fresh('booking'))['title']
        );

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSee('Approve hours and pay')
            ->assertSee('capture payment')
            ->assertSee('3h 00m')
            ->assertSee('$99.00')
            ->assertSee('$90.00')
            ->assertDontSee('Review caregiver timesheet')
            ->assertDontSee('Leave a caregiver review')
            ->call('completeBooking');

        $this->assertNotNull($booking->fresh()?->family_confirmed_at);
    }

    public function test_family_can_see_caregiver_review_feedback_in_shift_view(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::query()->create(['user_id' => $caregiver->id, 'status' => 'active']);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Review visibility request',
            'status' => CareRequest::STATUS_FILLED,
            'requested_start_at' => now()->subHours(5),
            'requested_end_at' => now()->subHours(2),
            'address_line1' => '300 Pine Ave',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);
        $request->recipient()->create([
            'full_name' => 'Elder Person',
            'relationship_to_family' => 'Mother',
        ]);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 30,
            'cover_note' => 'Experienced and available.',
        ]);

        $booking = CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_REVIEWED,
            'scheduled_start_at' => now()->subHours(5),
            'scheduled_end_at' => now()->subHours(2),
            'started_at' => now()->subHours(5),
            'completed_at' => now()->subHours(2),
            'timesheet_submitted_at' => now()->subHours(2),
            'worked_minutes' => 180,
            'family_confirmed_at' => now()->subHours(1),
        ]);

        CareReview::query()->create([
            'care_booking_id' => $booking->id,
            'care_request_id' => $request->id,
            'reviewer_user_id' => $caregiver->id,
            'reviewee_user_id' => $family->id,
            'rating' => 5,
            'comment' => 'Family was clear and respectful.',
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->set('activeTab', 'shift')
            ->assertSee('Caregiver feedback')
            ->assertSee('Family was clear and respectful.');
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
            ->set('recurring_end_choice', 'date')
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
        $this->assertNotNull($careRequest->recurring_ends_on);
        $this->assertNull($careRequest->requested_start_at);
        $this->assertNull($careRequest->requested_end_at);
    }

    public function test_recurring_request_moves_first_day_to_selected_weekday_and_estimates_one_visit(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $mismatchedStart = now()->addDays(2)->startOfDay();
        $selectedDay = ($mismatchedStart->dayOfWeek + 1) % 7;
        $expectedStart = $mismatchedStart->copy()->addDay();

        $component = Livewire::actingAs($family)
            ->test(CreateCareRequestWizard::class)
            ->set('request_type', CareRequest::TYPE_RECURRING)
            ->set('recurring_days', [$selectedDay])
            ->set('recurring_starts_on', $mismatchedStart->toDateString())
            ->set('recurring_start_time', '09:00')
            ->set('recurring_duration_minutes', '180');

        $component
            ->assertSet('recurring_starts_on', $expectedStart->toDateString())
            ->assertSee('Starting day moved to '.$expectedStart->format('l, F j'));
        $this->assertSame(3.0, $component->get('estimatedHours'));
        $this->assertSame(90.0, $component->get('estimatedCost'));
    }

    public function test_family_can_publish_different_times_for_each_recurring_day(): void
    {
        $family = User::factory()->create(['role' => 'family', 'city' => 'Raleigh', 'state' => 'NC']);
        $task = CareTask::query()->create(['name' => 'Companionship']);

        Livewire::actingAs($family)
            ->test(CreateCareRequestWizard::class)
            ->set('request_type', CareRequest::TYPE_RECURRING)
            ->set('additional_info', 'Two weekly visits with different times.')
            ->set('recurring_days', [1, 5])
            ->set('recurring_schedule', [
                '1' => ['start_time' => '14:00', 'duration_minutes' => '120', 'end_time' => ''],
                '5' => ['start_time' => '09:30', 'duration_minutes' => '180', 'end_time' => ''],
            ])
            ->set('recurring_starts_on', now()->addDay()->toDateString())
            ->set('address_line1', '900 Elm St')
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('zip', '27605')
            ->set('selectedTasks', [$task->id])
            ->call('nextStep')
            ->set('recipient_full_name', 'Barbara Pearl')
            ->set('recipient_relationship_to_family', 'Self')
            ->call('nextStep')
            ->call('nextStep')
            ->call('publish')
            ->assertHasNoErrors();

        $request = CareRequest::query()->latest('id')->firstOrFail();

        $this->assertSame([1, 5], $request->recurring_days);
        $this->assertSame('14:00', $request->recurring_start_time);
        $this->assertSame('16:00', $request->recurring_end_time);
        $this->assertSame([
            ['day' => 1, 'start_time' => '14:00', 'end_time' => '16:00'],
            ['day' => 5, 'start_time' => '09:30', 'end_time' => '12:30'],
        ], $request->recurringScheduleSlots());
        $this->assertSame(300, collect($request->recurringScheduleSlots())
            ->sum(fn (array $slot) => \App\Support\WeeklySchedule::durationMinutes($slot)));
    }

    public function test_family_can_publish_without_custom_title_and_request_gets_generated_title(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);

        $task = CareTask::query()->create(['name' => 'Companionship']);
        $startAt = now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i');
        $endAt = now()->addDay()->setTime(14, 0)->format('Y-m-d\TH:i');

        Livewire::actingAs($family)
            ->test(CreateCareRequestWizard::class)
            ->set('title', '')
            ->set('additional_info', 'Need someone to stay with my mom and help with lunch setup.')
            ->set('requested_start_at', $startAt)
            ->set('requested_end_at', $endAt)
            ->set('address_line1', '100 Main St')
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('zip', '27601')
            ->set('selectedTasks', [$task->id])
            ->call('nextStep')
            ->call('nextStep')
            ->set('recipient_full_name', 'Margaret Doe')
            ->call('publish');

        $careRequest = CareRequest::query()->latest('id')->first();

        $this->assertNotNull($careRequest);
        $this->assertNotSame('', trim((string) $careRequest->title));
        $this->assertStringContainsString('care support', strtolower((string) $careRequest->title));
    }

    public function test_family_can_publish_one_time_request_from_day_time_and_duration(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);

        $task = CareTask::query()->create(['name' => 'Errands']);
        $start = now()->addDay()->setTime(10, 0, 0);

        Livewire::actingAs($family)
            ->test(CreateCareRequestWizard::class)
            ->set('selectedTasks', [$task->id])
            ->set('requested_start_date', $start->toDateString())
            ->set('requested_start_time', $start->format('H:i'))
            ->set('requested_duration_minutes', '210')
            ->set('address_line1', '100 Main St')
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('zip', '27601')
            ->set('recipient_full_name', 'Don Johnson')
            ->call('publish');

        $careRequest = CareRequest::query()->latest('id')->first();

        $this->assertNotNull($careRequest);
        $this->assertSame($start->format('Y-m-d H:i:s'), $careRequest->requested_start_at?->format('Y-m-d H:i:s'));
        $this->assertSame($start->copy()->addMinutes(210)->format('Y-m-d H:i:s'), $careRequest->requested_end_at?->format('Y-m-d H:i:s'));
    }

    public function test_family_can_save_and_reuse_household_and_recipient_profiles_in_one_click(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);

        $task = CareTask::query()->create(['name' => 'Companionship']);
        $startAt = now()->addDay()->setTime(9, 0)->format('Y-m-d\TH:i');
        $endAt = now()->addDay()->setTime(12, 0)->format('Y-m-d\TH:i');

        Livewire::actingAs($family)
            ->test(CreateCareRequestWizard::class)
            ->set('request_mode', CreateCareRequestWizard::MODE_FAST_TRACK)
            ->set('selectedTasks', [$task->id])
            ->set('requested_start_at', $startAt)
            ->set('requested_end_at', $endAt)
            ->set('address_line1', '10 Oak Street')
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('zip', '27601')
            ->call('nextStep')
            ->set('recipient_full_name', 'First Recipient')
            ->set('recipient_relationship_to_family', 'Mother')
            ->call('nextStep')
            ->call('publish');

        $this->assertDatabaseHas('family_household_profiles', [
            'family_user_id' => $family->id,
            'address_line1' => '10 Oak Street',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);

        $this->assertDatabaseHas('family_recipient_profiles', [
            'family_user_id' => $family->id,
            'full_name' => 'First Recipient',
            'relationship_to_family' => 'Mother',
        ]);

        Livewire::actingAs($family)
            ->test(CreateCareRequestWizard::class)
            ->call('applySavedProfiles')
            ->assertSet('address_line1', '10 Oak Street')
            ->assertSet('city', 'Raleigh')
            ->assertSet('state', 'NC')
            ->assertSet('zip', '27601')
            ->assertSet('recipient_full_name', 'First Recipient')
            ->assertSet('recipient_relationship_to_family', 'Mother');
    }

    public function test_family_can_prefill_from_last_request(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);

        $task = CareTask::query()->create(['name' => 'Meal preparation']);
        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Previous request title',
            'additional_info' => 'Need support for mom during lunch.',
            'scope_of_work' => 'Meal prep and companionship.',
            'time_expectations' => 'Arrive on time.',
            'home_access_notes' => 'Use front door lockbox.',
            'preferred_response_hours' => 8,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay()->setTime(10, 0),
            'requested_end_at' => now()->addDay()->setTime(12, 0),
            'address_line1' => '12 River St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
            'status' => CareRequest::STATUS_OPEN,
        ]);
        $request->tasks()->sync([$task->id => ['task_note' => 'Soft diet only']]);
        $request->recipient()->create([
            'full_name' => 'Margaret Johnson',
            'relationship_to_family' => 'Mother',
            'care_notes' => 'Needs help with meal setup.',
        ]);
        $request->thirdPartyContact()->create([
            'full_name' => 'Daniel Johnson',
            'relationship_to_recipient' => 'Son',
            'phone' => '+1 919 555 1111',
        ]);

        Livewire::actingAs($family)
            ->test(CreateCareRequestWizard::class)
            ->call('prefillFromLastRequest')
            ->assertSet('title', 'Previous request title')
            ->assertSet('address_line1', '12 River St')
            ->assertSet('city', 'Raleigh')
            ->assertSet('state', 'NC')
            ->assertSet('zip', '27601')
            ->assertSet('preferred_response_hours', 8)
            ->assertSet('recipient_full_name', 'Margaret Johnson')
            ->assertSet('includeThirdPartyContact', true)
            ->assertSet('requested_start_at', '')
            ->assertSet('requested_end_at', '')
            ->assertSet('selectedTasks', [$task->id]);
    }

    public function test_family_can_publish_request_for_self_without_typing_recipient_name(): void
    {
        $family = User::factory()->create([
            'name' => 'Don Harris',
            'role' => 'family',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);

        $task = CareTask::query()->create(['name' => 'Companionship']);
        $startAt = now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i');
        $endAt = now()->addDay()->setTime(12, 0)->format('Y-m-d\TH:i');

        Livewire::actingAs($family)
            ->test(CreateCareRequestWizard::class)
            ->set('care_for', CreateCareRequestWizard::CARE_FOR_SELF)
            ->set('selectedTasks', [$task->id])
            ->set('requested_start_at', $startAt)
            ->set('requested_end_at', $endAt)
            ->set('address_line1', '100 Main St')
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('zip', '27601')
            ->call('publish');

        $careRequest = CareRequest::query()->latest('id')->first();

        $this->assertNotNull($careRequest);
        $this->assertDatabaseHas('care_request_recipients', [
            'care_request_id' => $careRequest->id,
            'recipient_is_requester' => true,
            'full_name' => 'Don Harris',
            'relationship_to_family' => 'Self',
        ]);
        $this->assertDatabaseHas('family_recipient_profiles', [
            'family_user_id' => $family->id,
            'recipient_is_requester' => true,
            'full_name' => 'Don Harris',
            'relationship_to_family' => 'Self',
        ]);
    }

    public function test_family_sees_one_time_estimated_cost_preview(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);

        $task = CareTask::query()->create(['name' => 'Companionship']);
        $startAt = now()->addDay()->setTime(9, 0)->format('Y-m-d\TH:i');
        $endAt = now()->addDay()->setTime(13, 0)->format('Y-m-d\TH:i');

        Livewire::actingAs($family)
            ->test(CreateCareRequestWizard::class)
            ->set('request_type', CareRequest::TYPE_ONE_TIME)
            ->set('additional_info', 'Need companionship and meal support for my mom at home.')
            ->set('selectedTasks', [$task->id])
            ->set('requested_start_at', $startAt)
            ->set('requested_end_at', $endAt)
            ->call('nextStep')
            ->assertSee('Review and publish')
            ->assertSee('Estimated one-time cost')
            ->assertSee('4.00h')
            ->assertSee('$30.00/hr')
            ->assertSee('$120.00')
            ->assertDontSee('Request summary');
    }

    public function test_create_request_shows_plain_language_publish_checklist(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'name' => 'Don Johnson',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);

        $task = CareTask::query()->create(['name' => 'Companionship']);

        Livewire::actingAs($family)
            ->test(CreateCareRequestWizard::class)
            ->assertSee('Before publishing')
            ->assertSee('0 of 4 essentials ready')
            ->assertSee('Person')
            ->assertSee('Help')
            ->assertSee('Time')
            ->assertSee('Address')
            ->assertSee('Needed')
            ->set('care_for', CreateCareRequestWizard::CARE_FOR_SELF)
            ->set('selectedTasks', [$task->id])
            ->set('requested_start_date', now()->addDay()->toDateString())
            ->set('requested_start_time', '09:30')
            ->set('requested_duration_minutes', '120')
            ->set('address_line1', '1520 Home Creek Drive')
            ->set('city', 'Durham')
            ->set('state', 'NC')
            ->set('zip', '27703')
            ->assertSee('All essentials are ready. Review once, then publish.')
            ->assertSee('Don Johnson')
            ->assertSee('Companionship')
            ->assertSee('Durham, NC 27703')
            ->assertSee('Ready');
    }

    private function createCompletedRebookSource(User $family, User $caregiver, string $title, \Illuminate\Support\Carbon $startAt): CareRequest
    {
        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => $title,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'status' => CareRequest::STATUS_FILLED,
            'requested_start_at' => $startAt,
            'requested_end_at' => $startAt->copy()->addHours(2),
            'city' => 'Durham',
            'state' => 'NC',
            'zip' => '27703',
            'address_line1' => '1520 Home Creek Drive',
        ]);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 30,
        ]);

        CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_REVIEWED,
            'scheduled_start_at' => $startAt,
            'scheduled_end_at' => $startAt->copy()->addHours(2),
            'completed_at' => $startAt->copy()->addHours(2),
            'worked_minutes' => 120,
            'family_confirmed_at' => $startAt->copy()->addHours(3),
        ]);

        return $request;
    }
}
