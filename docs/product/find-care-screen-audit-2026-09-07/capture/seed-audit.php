<?php

/**
 * Synthetic screenshot fixtures. Run after migrations and homecare:e2e-seed.
 * This script deliberately refuses every database except find-care-audit.sqlite
 * in APP_ENV=playwright. It does not invoke payment, messaging, or plan services.
 * Recreate the isolated audit database before rerunning; fixture users are unique.
 */

use App\Models\CareBooking;
use App\Models\CareBookingEvent;
use App\Models\CareBookingPayment;
use App\Models\CareBookingTaskCheck;
use App\Models\CaregiverProfile;
use App\Models\CarePlan;
use App\Models\CarePlanEvent;
use App\Models\CareRelationship;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareRequestInvitation;
use App\Models\CareRequestMessage;
use App\Models\CareReview;
use App\Models\CareTask;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use App\Services\Booking\BookingTrustService;
use App\Services\CareRecipientProfiles\CareRecipientProfileService;
use App\Services\FamilyAccounts\FamilyAccountProvisioner;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = str_replace('\\', '/', (string) config('database.connections.sqlite.database'));
if (! $app->environment('playwright')
    || config('database.default') !== 'sqlite'
    || ! str_ends_with(strtolower($database), '/find-care-audit.sqlite')) {
    throw new RuntimeException('Audit fixtures require APP_ENV=playwright and the isolated SQLite file find-care-audit.sqlite.');
}
if (User::where('email', 'maria.find-care-audit@example.com')->exists()) {
    throw new RuntimeException('Audit fixtures already exist. Use a freshly migrated isolated audit database.');
}

$manifest = DB::transaction(function (): array {
    $password = 'password';
    $family = User::create([
        'name' => 'Maria Bennett', 'email' => 'maria.find-care-audit@example.com',
        'role' => 'family', 'password' => Hash::make($password), 'email_verified_at' => now(),
        'phone' => '+1 919 555 0142', 'city' => 'Raleigh', 'state' => 'NC',
    ]);
    $account = app(FamilyAccountProvisioner::class)->provisionOwner($family, 'find_care_screen_audit');
    $profileData = [
        'recipient_is_requester' => false, 'full_name' => 'Eleanor Bennett', 'preferred_name' => 'Ellie',
        'date_of_birth' => '1947-04-12', 'age_range' => '75_84', 'pronouns' => 'She/her',
        'relationship_to_family' => 'Mother',
        'about_them' => 'A retired teacher who enjoys gardening, family stories, and a friendly conversation.',
        'interests_and_comforts' => 'Crossword puzzles, jazz, and sitting in the garden when the weather is mild.',
        'good_visit_notes' => 'A relaxed pace, a warm lunch, and a short walk make a good visit.',
        'communication_preferences' => ['short_clear_sentences', 'extra_time'],
        'communication_notes' => 'Please introduce yourself and give Ellie time to answer.',
        'support_areas' => ['companionship', 'meals', 'household_help', 'mobility'],
        'mobility_level' => 'uses_aid', 'mobility_notes' => 'Uses a walking cane outdoors; stay nearby on steps.',
        'routine_notes' => 'Lunch around noon, followed by a short walk and a cup of tea.',
        'food_and_drink_notes' => 'Prefers soup and a sandwich. Encourage a glass of water at lunch.',
        'comfort_needs' => ['reminders_reassurance'], 'safety_items' => ['fall_risk'],
        'safety_notes' => 'Keep the hallway clear and remind Ellie to use the handrail.',
        'caregiver_quality_preferences' => ['patient', 'calm', 'conversational'],
        'caregiver_do_notes' => 'Explain what you are doing and offer choices.',
        'include_additional_contact' => true, 'additional_contact_name' => 'Daniel Bennett',
        'additional_contact_relationship' => 'Son', 'additional_contact_phone' => '+1 919 555 0153',
        'additional_contact_email' => 'daniel.find-care-audit@example.com',
        'assigned_escalation_notes' => 'Contact Maria first; Daniel is the backup family contact.',
    ];
    $profileService = app(CareRecipientProfileService::class);
    $recipientProfile = $profileService->saveDraft($family, null, $profileData);
    $recipientProfile = $profileService->makeReady($family, $recipientProfile, $profileData, $recipientProfile->revision, true);

    $tasks = [];
    foreach ([
        'Companionship' => 'Conversation, a crossword puzzle, and a short walk if Ellie feels up to it.',
        'Meal preparation' => 'Prepare soup and a sandwich for lunch; leave the kitchen tidy.',
        'Light housekeeping' => 'Wash dishes and keep the living room and hallway clear.',
    ] as $name => $note) {
        $task = CareTask::firstOrCreate(['name' => $name]);
        $tasks[$task->id] = ['task_note' => $note];
        Skill::firstOrCreate(['name' => $name]);
    }
    $language = Language::firstOrCreate(['name' => 'English']);
    $caregivers = [];
    foreach ([
        ['Sofia Martinez', 'sofia', 7, 4.9, 24, 'I enjoy helping older adults keep a familiar routine, prepare meals, and stay connected.'],
        ['James Wilson', 'james', 5, 4.8, 18, 'Patient, dependable support with companionship, everyday tasks, and gentle encouragement.'],
        ['Aisha Robinson', 'aisha', 9, 5.0, 31, 'I bring a calm approach to home support and take time to understand each person.'],
    ] as [$name, $slug, $experience, $rating, $reviews, $bio]) {
        $caregiver = User::create([
            'name' => $name, 'email' => $slug.'.find-care-audit@example.com', 'role' => 'caregiver',
            'password' => Hash::make($password), 'email_verified_at' => now(), 'onboarding_completed_at' => now(),
            'date_of_birth' => '1987-08-18', 'phone' => '+1 919 555 016'.count($caregivers),
            'city' => 'Raleigh', 'state' => 'NC',
        ]);
        $profile = CaregiverProfile::create([
            'user_id' => $caregiver->id, 'slug' => $slug.'-find-care-audit', 'status' => 'active',
            'bio' => $bio, 'hourly_rate' => 30, 'platform_hourly_rate' => 30,
            'years_experience' => $experience, 'service_area_zip' => '27601', 'service_radius_miles' => 25,
            'insurance_status' => CaregiverProfile::INSURANCE_NO, 'identity_verification_status' => 'approved',
            'identity_verified_at' => now()->subMonths(3), 'background_check_verified_at' => now()->subMonths(2),
            'is_accepting_new_clients' => true, 'top_caregiver' => true, 'average_rating' => $rating,
            'reviews_count' => $reviews, 'invite_response_rate' => 96, 'avg_invite_response_minutes' => 45,
            'reliability_score' => 98, 'completed_bookings_count' => $reviews * 3,
        ]);
        $profile->skills()->sync(Skill::whereIn('name', ['Companionship', 'Meal preparation', 'Light housekeeping'])->pluck('id'));
        $profile->languages()->sync([$language->id]);
        foreach (range(0, 6) as $day) {
            $profile->availabilities()->create(['day_of_week' => $day, 'start_time' => '00:00', 'end_time' => '23:59']);
        }
        $caregivers[$slug] = $caregiver;
    }
    $caregiver = $caregivers['sofia'];
    $recipient = [
        'recipient_is_requester' => false, 'full_name' => 'Eleanor Bennett', 'date_of_birth' => '1947-04-12',
        'gender' => 'female', 'mobility_level' => 'uses_aid', 'relationship_to_family' => 'Mother',
        'care_notes' => 'Ellie enjoys conversation and a calm routine. Stay nearby on steps and offer a glass of water at lunch.',
        'care_recipient_profile_id' => $recipientProfile->id,
        'care_recipient_profile_version_id' => $recipientProfile->latest_ready_version_id,
    ];
    $address = ['address_line1' => '142 Maple Grove Lane', 'address_line2' => 'Apartment 2B',
        'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601', 'lat' => 35.7796, 'lng' => -78.6382];
    $schedule = [['day' => 1, 'start_time' => '10:00', 'end_time' => '12:00'],
        ['day' => 3, 'start_time' => '10:00', 'end_time' => '12:00'],
        ['day' => 5, 'start_time' => '10:00', 'end_time' => '12:00']];

    $makeRequest = function (string $variation, string $label, string $status = 'open', ?Carbon $start = null, ?Carbon $end = null) use ($family, $recipient, $address, $tasks, $schedule): CareRequest {
        $request = CareRequest::create(array_merge($address, [
            'family_user_id' => $family->id, 'created_by_user_id' => $family->id,
            'title' => ($variation === 'recurring' ? 'Recurring care' : 'A helping hand').' for Ellie — '.$label,
            'additional_info' => 'We are looking for a friendly caregiver to help Ellie feel comfortable and supported at home.',
            'scope_of_work' => 'Companionship, preparing lunch, and light housekeeping.',
            'time_expectations' => 'Please arrive on time and leave a short update after the visit.',
            'home_access_notes' => 'Use the main entrance and ring apartment 2B. Maria will meet you for the first visit.',
            'preferred_response_hours' => 12, 'is_private' => false, 'status' => $status,
            'request_type' => $variation, 'budget_min' => 30, 'budget_max' => 30,
            'requested_start_at' => $start ?? now()->addDays(3)->setTime(10, 0),
            'requested_end_at' => $end ?? now()->addDays(3)->setTime(12, 0),
            'recurring_days' => $variation === 'recurring' ? [1, 3, 5] : null,
            'recurring_start_time' => $variation === 'recurring' ? '10:00' : null,
            'recurring_end_time' => $variation === 'recurring' ? '12:00' : null,
            'recurring_schedule' => $variation === 'recurring' ? $schedule : null,
            'recurring_starts_on' => $variation === 'recurring' ? now()->addDays(3)->toDateString() : null,
            'recurring_ends_on' => $variation === 'recurring' ? now()->addMonths(3)->toDateString() : null,
        ]));
        $request->recipient()->create($recipient);
        $request->thirdPartyContact()->create(['full_name' => 'Daniel Bennett', 'relationship_to_recipient' => 'Son',
            'phone' => '+1 919 555 0153', 'email' => 'daniel.find-care-audit@example.com']);
        $request->tasks()->sync($tasks);
        return $request;
    };
    $makeApplication = function (CareRequest $request, User $applicant, string $status = 'applied'): CareRequestApplication {
        return CareRequestApplication::create(['care_request_id' => $request->id, 'caregiver_user_id' => $applicant->id,
            'status' => $status, 'proposed_rate' => 30,
            'cover_note' => 'I would be happy to help Ellie with lunch, a little tidying, and conversation. I am available for your requested schedule.']);
    };
    $makeConversation = function (CareRequestApplication $application) use ($family): int {
        $conversation = CareRequestConversation::findOrCreateForApplication($application, $family->id);
        foreach ([[$family->id, 'Hi! Ellie enjoys a calm routine and usually has lunch at noon. Does that work for you?'],
            [$application->caregiver_user_id, 'Absolutely. I will take time to get to know Ellie and follow her usual routine.']] as [$sender, $body]) {
            CareRequestMessage::create(['care_request_conversation_id' => $conversation->id, 'sender_user_id' => $sender, 'body' => $body]);
        }
        $conversation->update(['last_message_at' => now(), 'last_message_sender_id' => $application->caregiver_user_id]);
        return $conversation->id;
    };

    $makeBooking = function (CareRequest $request, string $stage, ?Carbon $overrideStart = null) use ($family, $caregiver, $makeApplication): CareBooking {
        $isFinished = in_array($stage, ['after_shift', 'timesheet_review', 'leave_review', 'closed']);
        $isReviewed = in_array($stage, ['leave_review', 'closed']);
        $start = $overrideStart ?? match ($stage) {
            'scheduled', 'cancelled_booking' => now()->addDays(2)->setTime(10, 0),
            'in_progress' => now()->subMinutes(55),
            'after_shift' => now()->subHours(3),
            'timesheet_review' => now()->subHours(6),
            default => now()->subDays($stage === 'closed' ? 7 : 2)->setTime(10, 0),
        };
        $end = $start->copy()->addHours(2);
        $request->update(['status' => CareRequest::STATUS_FILLED, 'requested_start_at' => $start,
            'requested_end_at' => $end, 'first_hire_at' => $start->copy()->subDay()]);
        $application = $makeApplication($request, $caregiver, 'hired');
        $status = match ($stage) {
            'in_progress' => CareBooking::STATUS_IN_PROGRESS,
            'after_shift', 'timesheet_review' => CareBooking::STATUS_COMPLETED,
            'leave_review', 'closed' => CareBooking::STATUS_REVIEWED,
            'cancelled_booking' => CareBooking::STATUS_CANCELLED,
            default => CareBooking::STATUS_SCHEDULED,
        };
        $booking = CareBooking::create([
            'care_request_id' => $request->id, 'care_request_application_id' => $application->id,
            'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id, 'status' => $status,
            'scheduled_start_at' => $start, 'scheduled_end_at' => $end, 'expected_minutes' => 120,
            'family_terms_accepted_at' => $start->copy()->subDay(), 'caregiver_terms_accepted_at' => $start->copy()->subDay(),
            'agreement_snapshot' => app(BookingTrustService::class)->buildAgreementSnapshot($request->fresh(['recipient', 'tasks']), $application),
            'started_at' => $isFinished || $stage === 'in_progress' ? $start : null,
            'completed_at' => $isFinished ? $end : null,
            'worked_minutes' => $isFinished ? 120 : null,
            'timesheet_submitted_at' => $isFinished ? ($stage === 'after_shift' ? $end : $end->copy()->addMinutes(5)) : null,
            'family_confirmed_at' => $isReviewed ? $end->copy()->addMinutes(35) : null,
            'family_confirmed_by_user_id' => $isReviewed ? $family->id : null,
            'reviewed_at' => $isReviewed ? $end->copy()->addMinutes(35) : null,
            'check_in_lat' => $isFinished || $stage === 'in_progress' ? 35.7796 : null,
            'check_in_lng' => $isFinished || $stage === 'in_progress' ? -78.6382 : null,
            'check_in_accuracy_meters' => $isFinished || $stage === 'in_progress' ? 14.2 : null,
            'check_in_source' => $isFinished || $stage === 'in_progress' ? 'browser_gps' : null,
            'check_in_note' => $isFinished || $stage === 'in_progress' ? 'Arrived and checked in with Maria. Ellie is comfortable.' : null,
            'check_out_lat' => $isFinished ? 35.7797 : null, 'check_out_lng' => $isFinished ? -78.6383 : null,
            'check_out_accuracy_meters' => $isFinished ? 12.8 : null, 'check_out_source' => $isFinished ? 'browser_gps' : null,
            'check_out_note' => $isFinished ? 'Ellie ate lunch, enjoyed a crossword and a short garden walk. The kitchen is tidy and she was comfortable when I left.' : null,
            'cancelled_at' => $stage === 'cancelled_booking' ? now()->subHour() : null,
            'cancelled_by_user_id' => $stage === 'cancelled_booking' ? $family->id : null,
            'cancellation_reason' => $stage === 'cancelled_booking' ? 'Our family plans changed and this visit is no longer needed.' : null,
        ]);
        CareBookingPayment::create([
            'care_booking_id' => $booking->id, 'financial_reference' => $booking->financial_reference,
            'pricing_version' => $booking->pricing_version, 'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id,
            'status' => $isReviewed ? 'captured' : ($stage === 'cancelled_booking' ? 'cancelled' : 'authorized'),
            'currency' => 'usd', 'family_care_rate_cents' => 3000, 'family_processing_fee_rate_cents' => 100,
            'caregiver_gross_rate_cents' => 2700, 'amount_authorized_cents' => 6200,
            'amount_captured_cents' => $isReviewed ? 6200 : 0, 'worked_minutes' => $isFinished ? 120 : null,
            'family_care_amount_cents' => 6000, 'family_processing_fee_cents' => 200,
            'caregiver_gross_amount_cents' => 5400, 'caregiver_amount_cents' => 5190,
            'stripe_processing_fee_cents' => $isReviewed ? 210 : 0, 'platform_fee_cents' => 800,
            'fee_finalization_status' => $isReviewed ? 'finalized' : 'pending',
            'authorized_at' => $start->copy()->subDay(), 'authorization_expires_at' => now()->addDays(5),
            'captured_at' => $isReviewed ? $end->copy()->addMinutes(35) : null,
            'metadata' => ['synthetic_audit_fixture' => true],
        ]);
        foreach ($request->fresh('tasks')->tasks as $index => $task) {
            $complete = $isFinished || ($stage === 'in_progress' && $index === 0);
            CareBookingTaskCheck::create(['care_booking_id' => $booking->id, 'care_task_id' => $task->id,
                'label' => $task->name, 'notes' => $task->pivot->task_note, 'is_completed' => $complete,
                'completed_at' => $complete ? ($isFinished ? $end->copy()->subMinutes(5) : now()->subMinutes(10)) : null,
                'completed_by_user_id' => $complete ? $caregiver->id : null]);
        }
        $events = [['booking_hired', $family->id, 'family', $start->copy()->subDay()]];
        if ($isFinished || $stage === 'in_progress') $events[] = ['shift_started_by_caregiver', $caregiver->id, 'caregiver', $start];
        if ($isFinished) $events[] = ['shift_completed_by_caregiver', $caregiver->id, 'caregiver', $end];
        if ($isFinished) $events[] = ['timesheet_submitted', $caregiver->id, 'caregiver', $stage === 'after_shift' ? $end : $end->copy()->addMinutes(5)];
        if ($isReviewed) $events[] = ['timesheet_confirmed_by_family', $family->id, 'family', $end->copy()->addMinutes(35)];
        foreach ($events as [$event, $actor, $role, $time]) {
            CareBookingEvent::create(['care_booking_id' => $booking->id, 'actor_user_id' => $actor, 'actor_role' => $role,
                'event_type' => $event, 'payload' => ['source' => 'find_care_screen_audit'], 'happened_at' => $time]);
        }
        if ($stage === 'closed') {
            CareReview::create(['care_request_id' => $request->id, 'care_booking_id' => $booking->id,
                'reviewer_user_id' => $family->id, 'reviewee_user_id' => $caregiver->id, 'rating' => 5,
                'comment' => 'Sofia was warm, punctual, and patient. Ellie really enjoyed their conversation.']);
            CareReview::create(['care_request_id' => $request->id, 'care_booking_id' => $booking->id,
                'reviewer_user_id' => $caregiver->id, 'reviewee_user_id' => $family->id, 'rating' => 5,
                'comment' => 'Maria shared a clear routine and Ellie was a pleasure to spend time with.']);
        }
        return $booking;
    };

    $manifest = ['familyEmail' => $family->email, 'password' => $password, 'familyId' => $family->id,
        'familyAccountId' => $account->id, 'recipientProfileId' => $recipientProfile->id,
        'caregivers' => collect($caregivers)->map(fn (User $user) => ['id' => $user->id, 'email' => $user->email, 'name' => $user->name])->all(),
        'requests' => [], 'bookings' => [], 'applications' => [], 'conversations' => [], 'plans' => [],
        'notes' => ['All names, addresses, messages, visits, reviews and payment rows are synthetic audit data.',
            'Recurring post-hire visits are attached to a care plan. Generated occurrences use request_type=one_time, as the application does.',
            'after_shift and timesheet_review are both completed, submitted, family-unconfirmed visits; the current UI shares their review-hours state.',
            'Payment rows contain no Stripe customer, payment method, intent, or account identifiers.']];
    foreach (['one_time', 'recurring'] as $variation) {
        foreach (['waiting', 'invitations', 'applicants', 'scheduled', 'in_progress', 'after_shift', 'timesheet_review', 'leave_review', 'closed', 'cancelled', 'cancelled_booking'] as $stage) {
            $label = ucwords(str_replace('_', ' ', $stage));
            $request = $makeRequest($variation, $label, $stage === 'cancelled' ? 'cancelled' : 'open');
            $manifest['requests'][$variation][$stage] = $request->id;
            if ($stage === 'invitations') {
                foreach (['sofia', 'aisha'] as $slug) {
                    CareRequestInvitation::create(['care_request_id' => $request->id, 'family_user_id' => $family->id,
                        'invited_by_user_id' => $family->id, 'caregiver_user_id' => $caregivers[$slug]->id,
                        'status' => 'pending', 'message' => 'Your experience looks like a lovely fit for Ellie. Are you available for this care request?',
                        'expires_at' => now()->addHours(60)]);
                }
            } elseif ($stage === 'applicants') {
                $shortlisted = $makeApplication($request, $caregiver, 'shortlisted');
                $applied = $makeApplication($request, $caregivers['james']);
                $request->update(['first_applicant_at' => now()->subHours(3), 'first_shortlist_at' => now()->subHour()]);
                $manifest['applications'][$variation] = ['shortlisted' => $shortlisted->id, 'applied' => $applied->id];
                $manifest['conversations'][$variation] = $makeConversation($shortlisted);
            } elseif (! in_array($stage, ['waiting', 'cancelled'])) {
                $booking = $makeBooking($request, $stage);
                $manifest['bookings'][$variation][$stage] = $booking->id;
            }
        }
    }

    // Public recurring hire becomes a plan; later occurrences are single dated visits.
    foreach (['active', 'pending', 'paused', 'ended'] as $planStage) {
        $source = $planStage === 'active'
            ? CareRequest::findOrFail($manifest['requests']['recurring']['scheduled'])
            : $makeRequest('recurring', ucfirst($planStage).' plan source', 'filled', now()->subWeeks(2)->setTime(10, 0), now()->subWeeks(2)->setTime(12, 0));
        if ($planStage !== 'active') $makeBooking($source, 'closed', now()->subWeeks(2)->setTime(10, 0));
        $sourceBooking = $source->fresh('booking')->booking;
        $relationship = CareRelationship::create(['family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id,
            'source_care_request_id' => $source->id, 'last_care_request_id' => $source->id, 'last_care_booking_id' => $sourceBooking->id,
            'recipient_name' => 'Eleanor Bennett', 'care_recipient_profile_id' => $recipientProfile->id,
            'status' => $planStage === 'ended' ? 'ended' : ($planStage === 'paused' ? 'paused' : 'active'),
            'last_visit_at' => $sourceBooking->completed_at, 'metadata' => ['synthetic_audit_fixture' => true]]);
        $plan = CarePlan::create([
            'care_relationship_id' => $relationship->id, 'family_user_id' => $family->id, 'caregiver_user_id' => $caregiver->id,
            'source_care_request_id' => $source->id, 'source_care_booking_id' => $sourceBooking->id,
            'status' => $planStage === 'pending' ? 'pending_caregiver' : $planStage,
            'title' => 'Recurring care for Ellie — '.ucfirst($planStage),
            'care_recipient_profile_id' => $recipientProfile->id,
            'care_recipient_profile_version_id' => $recipientProfile->latest_ready_version_id,
            'recipient_snapshot' => $recipient, 'address_snapshot' => $address,
            'task_snapshot' => CareTask::whereIn('id', array_keys($tasks))->get()->map(fn ($task) => [
                'id' => $task->id, 'name' => $task->name, 'task_note' => $tasks[$task->id]['task_note']])->all(),
            'care_notes' => 'Please follow Ellie’s familiar morning routine: companionship, lunch, and a tidy kitchen.',
            'schedule_days' => [1, 3, 5], 'schedule_start_time' => '10:00', 'schedule_end_time' => '12:00',
            'schedule_slots' => $schedule, 'schedule_version' => 1, 'timezone' => 'America/New_York',
            'starts_on' => now()->subWeeks(2)->toDateString(),
            'ends_on' => $planStage === 'ended' ? now()->subDay()->toDateString() : now()->addMonths(3)->toDateString(),
            'hourly_rate' => 30, 'family_message' => 'We would love to keep the same friendly routine each week.',
            'caregiver_note' => $planStage !== 'pending' ? 'Happy to continue supporting Ellie on this schedule.' : null,
            'offered_at' => now()->subDays(10), 'expires_at' => $planStage === 'pending' ? now()->addDays(2) : null,
            'accepted_at' => $planStage !== 'pending' ? now()->subDays(9) : null,
            'activated_at' => $planStage !== 'pending' ? now()->subDays(9) : null,
            'paused_at' => $planStage === 'paused' ? now()->subDay() : null,
            'pause_starts_on' => $planStage === 'paused' ? now()->toDateString() : null,
            'resumes_on' => $planStage === 'paused' ? now()->addWeek()->toDateString() : null,
            'ended_at' => $planStage === 'ended' ? now()->subDay() : null,
            'payment_status' => $planStage === 'pending' ? 'unchecked' : 'authorized',
            'metadata' => ['synthetic_audit_fixture' => true, 'home_access_notes' => 'Ring apartment 2B at the main entrance.'],
        ]);
        $manifest['plans'][$planStage] = $plan->id;
        $manifest['planSources'][$planStage] = $source->id;
        $source->update(['care_plan_id' => $plan->id]);
        $sourceBooking->update(['care_plan_id' => $plan->id, 'occurrence_key' => 'audit-'.$plan->id.'-source',
            'plan_visit_kind' => 'regular', 'plan_schedule_version' => 1]);
        CarePlanEvent::create(['care_plan_id' => $plan->id, 'actor_user_id' => $family->id,
            'event_type' => $planStage === 'pending' ? 'offer_sent' : 'plan_'.$planStage,
            'reason' => $planStage === 'paused' ? 'Ellie is spending a week with family.' : ($planStage === 'ended' ? 'The family no longer needs this schedule.' : null),
            'payload' => ['source' => 'find_care_screen_audit']]);
    }
    foreach (['scheduled', 'in_progress', 'after_shift', 'timesheet_review', 'leave_review', 'closed', 'cancelled_booking'] as $stage) {
        $planId = $manifest['plans'][$stage === 'closed' || $stage === 'cancelled_booking' ? 'ended' : 'active'];
        $request = CareRequest::findOrFail($manifest['requests']['recurring'][$stage]);
        $booking = CareBooking::findOrFail($manifest['bookings']['recurring'][$stage]);
        $request->update(['care_plan_id' => $planId, 'is_system_generated' => $stage !== 'scheduled',
            'request_type' => $stage === 'scheduled' ? 'recurring' : 'one_time']);
        $booking->update(['care_plan_id' => $planId, 'occurrence_key' => 'audit-'.$planId.'-'.$stage,
            'plan_visit_kind' => 'regular', 'plan_schedule_version' => 1]);
    }
    CarePlan::findOrFail($manifest['plans']['active'])->update(['next_booking_id' => $manifest['bookings']['recurring']['scheduled']]);
    $manifest['navigation'] = [
        'create' => '/family/requests/create', 'requests' => '/family/requests',
        'requestDetailPattern' => '/family/requests/{id}', 'planDetailPattern' => '/family/care/{id}',
        'planIndex' => '/family/care',
    ];
    $manifest['seededAt'] = now()->toIso8601String();
    return $manifest;
});

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
