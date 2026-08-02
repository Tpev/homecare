<?php

namespace App\Services\ContinuousCoverage;

use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\ContinuousCoverageShift;
use App\Services\Booking\BookingTrustService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContinuousCoverageBookingAdapter
{
    public function __construct(
        private readonly BookingTrustService $trust,
        private readonly ContinuousCoverageEventRecorder $events,
        private readonly ContinuousCoverageAccess $access,
        private readonly ContinuousCoverageNotificationService $notifications,
    ) {}

    public function linkConfirmedShift(ContinuousCoverageShift $shift): CareBooking
    {
        $linked = false;
        $booking = DB::transaction(function () use ($shift, &$linked): CareBooking {
            $locked = ContinuousCoverageShift::query()->lockForUpdate()->findOrFail($shift->id);
            if ($locked->care_booking_id) {
                return CareBooking::query()->findOrFail($locked->care_booking_id);
            }
            if (! $locked->assigned_caregiver_user_id || ! in_array($locked->status, [
                ContinuousCoverageShift::STATUS_CONFIRMED,
                ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION,
            ], true)) {
                throw ValidationException::withMessages(['shift' => 'Only a mutually confirmed coverage shift can create a visit booking.']);
            }

            $plan = $locked->plan()->with('family')->firstOrFail();
            if (! $this->access->allows($plan->family)) {
                throw ValidationException::withMessages(['shift' => 'Continuous Coverage is not currently available for this plan.']);
            }
            $caregiver = $locked->assignedCaregiver()->firstOrFail();
            $recipient = (array) $plan->recipient_snapshot;
            $address = (array) $plan->address_snapshot;
            $assignmentVersion = max(1, (int) data_get($locked->metadata, 'assignment_version', 1));
            $occurrenceKey = 'continuous-coverage:'.$locked->id.':'.$assignmentVersion;

            $existing = CareBooking::query()->where('occurrence_key', $occurrenceKey)->first();
            if ($existing) {
                $locked->forceFill(['care_booking_id' => $existing->id])->save();
                $linked = true;

                return $existing;
            }

            $request = CareRequest::query()->create([
                'family_user_id' => $plan->family_user_id,
                'is_system_generated' => true,
                'title' => 'Continuous coverage: '.$plan->recipientName().' with '.$caregiver->name,
                'additional_info' => $plan->care_notes,
                'scope_of_work' => $plan->care_notes,
                'time_expectations' => $locked->scheduled_start_at->format('l, F j \f\r\o\m g:i A').' to '.$locked->scheduled_end_at->format('g:i A'),
                'home_access_notes' => data_get($plan->metadata, 'home_access_notes'),
                'preferred_response_hours' => 12,
                'status' => CareRequest::STATUS_FILLED,
                'request_type' => CareRequest::TYPE_ONE_TIME,
                'budget_min' => $plan->hourly_rate,
                'budget_max' => $plan->hourly_rate,
                'requested_start_at' => $locked->scheduled_start_at,
                'requested_end_at' => $locked->scheduled_end_at,
                'address_line1' => (string) data_get($address, 'address_line1', 'Address on file'),
                'address_line2' => data_get($address, 'address_line2'),
                'city' => (string) data_get($address, 'city', ''),
                'state' => (string) data_get($address, 'state', ''),
                'zip' => (string) data_get($address, 'zip', ''),
                'lat' => data_get($address, 'lat'),
                'lng' => data_get($address, 'lng'),
                'first_applicant_at' => now(),
                'first_shortlist_at' => now(),
                'first_hire_at' => now(),
            ]);

            $request->recipient()->create([
                'recipient_is_requester' => (bool) data_get($recipient, 'recipient_is_requester', false),
                'full_name' => (string) data_get($recipient, 'full_name', 'Care recipient'),
                'date_of_birth' => data_get($recipient, 'date_of_birth'),
                'gender' => data_get($recipient, 'gender'),
                'mobility_level' => data_get($recipient, 'mobility_level'),
                'relationship_to_family' => (string) data_get($recipient, 'relationship_to_family', 'Loved one'),
                'care_notes' => data_get($recipient, 'care_notes') ?: $plan->care_notes,
            ]);

            $taskPayload = collect((array) $plan->task_snapshot)
                ->filter(fn ($task) => is_array($task) && ! empty($task['id']))
                ->mapWithKeys(fn (array $task) => [(int) $task['id'] => ['task_note' => $task['task_note'] ?? null]])
                ->all();
            $request->tasks()->sync($taskPayload);

            $application = CareRequestApplication::query()->create([
                'care_request_id' => $request->id,
                'caregiver_user_id' => $caregiver->id,
                'status' => CareRequestApplication::STATUS_HIRED,
                'proposed_rate' => $plan->hourly_rate,
                'cover_note' => 'Accepted through the family-approved Continuous Coverage care team.',
            ]);

            $booking = CareBooking::query()->create([
                'care_request_id' => $request->id,
                'occurrence_key' => $occurrenceKey,
                'plan_visit_kind' => 'coverage',
                'care_request_application_id' => $application->id,
                'family_user_id' => $plan->family_user_id,
                'caregiver_user_id' => $caregiver->id,
                'agreement_snapshot' => $this->trust->buildAgreementSnapshot($request->fresh(['recipient', 'tasks']), $application),
                'family_terms_accepted_at' => $locked->family_confirmed_at ?: now(),
                'caregiver_terms_accepted_at' => $locked->caregiver_accepted_at ?: now(),
                'status' => CareBooking::STATUS_SCHEDULED,
                'scheduled_start_at' => $locked->scheduled_start_at,
                'scheduled_end_at' => $locked->scheduled_end_at,
                'expected_minutes' => $locked->scheduled_minutes,
            ]);

            $this->trust->seedTaskChecks($booking, $request->fresh(['tasks']));
            $this->trust->recordEvent($booking, null, 'system', 'continuous_coverage_booking_created', [
                'continuous_coverage_plan_id' => $plan->id,
                'continuous_coverage_shift_id' => $locked->id,
            ]);
            $locked->forceFill(['care_booking_id' => $booking->id])->save();
            $this->events->record($plan, 'booking_linked', shift: $locked, payload: ['care_booking_id' => $booking->id]);
            $linked = true;

            return $booking;
        });

        if ($linked) {
            $this->notifications->shiftConfirmed($shift->fresh(['plan.family', 'assignedCaregiver']));
        }

        return $booking;
    }
}
