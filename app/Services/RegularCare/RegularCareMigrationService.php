<?php

namespace App\Services\RegularCare;

use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CarePlan;
use App\Models\CarePlanEvent;
use App\Models\CareRelationship;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Services\Booking\BookingTrustService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegularCareMigrationService
{
    public function __construct(
        private readonly CarePlanOccurrenceService $occurrences,
        private readonly CarePlanHealthService $health,
        private readonly BookingTrustService $trust,
    ) {}

    /** @return array<string,mixed> */
    public function inspect(CareRequest $request, ?array $confirmedSchedule = null): array
    {
        $request->loadMissing(['family', 'recipient', 'tasks', 'booking.payment', 'booking.application.caregiver', 'applications.caregiver', 'carePlan.generatedBookings.payment']);
        $plan = $request->carePlan ?: CarePlan::query()->where('source_care_request_id', $request->id)->first();
        $booking = $request->booking;
        $application = $booking?->application ?: $request->applications->firstWhere('status', CareRequestApplication::STATUS_HIRED);
        $caregiver = $application?->caregiver ?: $booking?->caregiver;
        $warnings = [];

        if (! $request->isRecurring()) {
            $warnings[] = 'Source request is not marked recurring.';
        }
        if (! $caregiver) {
            $warnings[] = 'No hired caregiver could be resolved.';
        }
        if (empty($request->recurring_days)) {
            $warnings[] = 'Recurring weekdays are missing.';
        }
        if (! $request->recurring_start_time || ! $request->recurring_end_time) {
            $warnings[] = 'Recurring start or end time is missing.';
        }
        if ($booking?->payment?->authorization_expires_at?->isPast()
            && $booking->payment->status === \App\Models\CareBookingPayment::STATUS_AUTHORIZED) {
            $warnings[] = 'The retained booking authorization appears expired; payment preparation must review it without changing history.';
        }

        $duplicates = collect();
        if ($plan) {
            $duplicates = $plan->generatedBookings()
                ->selectRaw('scheduled_start_at, COUNT(*) as duplicate_count')
                ->groupBy('scheduled_start_at')
                ->havingRaw('COUNT(*) > 1')
                ->get();
        }

        $confirmedSchedule = $confirmedSchedule ? $this->normalizeConfirmedSchedule($confirmedSchedule) : null;
        $previewPlan = $plan
            ? clone $plan
            : $this->previewPlan($request, $caregiver?->id, $application?->proposed_rate, $confirmedSchedule);
        if ($previewPlan && $confirmedSchedule) {
            $previewPlan->forceFill($this->scheduleAttributes($confirmedSchedule));
        }
        $futureOccurrences = $caregiver && $previewPlan
            ? $this->occurrences->scheduledOccurrences(
                $previewPlan,
                now($previewPlan->timezone)->addWeeks((int) config('marketplace.regular_care.visit_window_weeks', 6))->endOfDay()
            )
            : [];

        return compact('request', 'plan', 'booking', 'application', 'caregiver', 'warnings', 'duplicates', 'previewPlan', 'futureOccurrences') + [
            'payment_action' => $this->paymentAction($booking),
            'current_schedule' => $this->requestSchedule($request),
            'confirmed_schedule' => $confirmedSchedule,
        ];
    }

    public function execute(CareRequest $request, array $confirmedSchedule, ?int $actorUserId = null): CarePlan
    {
        $confirmedSchedule = $this->normalizeConfirmedSchedule($confirmedSchedule);
        $report = $this->inspect($request, $confirmedSchedule);
        if (! $report['caregiver'] || ! $report['application']) {
            throw ValidationException::withMessages(['target' => 'A hired caregiver and application are required before migration.']);
        }
        if (! $request->isRecurring()) {
            throw ValidationException::withMessages(['target' => 'The source request must be marked as recurring.']);
        }

        return DB::transaction(function () use ($request, $report, $actorUserId, $confirmedSchedule): CarePlan {
            $lockedRequest = CareRequest::query()->lockForUpdate()->findOrFail($request->id);
            $plan = CarePlan::query()->where('source_care_request_id', $lockedRequest->id)->lockForUpdate()->first();
            $scheduleHash = hash('sha256', json_encode($confirmedSchedule, JSON_THROW_ON_ERROR));
            $previousHash = (string) data_get($plan?->metadata, 'migration_confirmed_schedule_hash', '');
            $scheduleChanged = ! $plan || $previousHash !== $scheduleHash;
            if (! $plan) {
                $caregiver = $report['caregiver'];
                $application = $report['application'];
                $relationship = CareRelationship::query()->firstOrNew([
                    'family_account_id' => $request->family_account_id,
                    'caregiver_user_id' => $caregiver->id,
                    'recipient_name' => $request->recipient?->full_name,
                ]);
                $relationship->fill([
                    'family_user_id' => $request->family_user_id,
                    'source_care_request_id' => $relationship->source_care_request_id ?: $request->id,
                    'last_care_request_id' => $request->id,
                    'last_care_booking_id' => $request->booking?->id,
                    'status' => CareRelationship::STATUS_ACTIVE,
                    'last_visit_at' => $request->booking?->completed_at ?: $request->booking?->scheduled_start_at,
                ])->save();

                $preview = $this->previewPlan($lockedRequest, $caregiver->id, $application->proposed_rate, $confirmedSchedule);
                $plan = CarePlan::query()->create(array_merge($preview->toArray(), [
                    'care_relationship_id' => $relationship->id,
                    'metadata' => [
                        'origin' => 'existing_customer_migration',
                        'migrated_at' => now()->toIso8601String(),
                        'migrated_by_user_id' => $actorUserId,
                        'migration_confirmed_schedule' => $confirmedSchedule,
                        'migration_confirmed_schedule_hash' => $scheduleHash,
                    ],
                ]));
            } elseif ($scheduleChanged) {
                $protectedFutureExists = $plan->generatedBookings()
                    ->where('status', CareBooking::STATUS_SCHEDULED)
                    ->where('scheduled_start_at', '>', now())
                    ->whereHas('payment', fn ($query) => $query->whereIn('status', [
                        CareBookingPayment::STATUS_AUTHORIZED,
                        CareBookingPayment::STATUS_CAPTURED,
                        CareBookingPayment::STATUS_TRANSFERRED,
                        CareBookingPayment::STATUS_PARTIALLY_REFUNDED,
                        CareBookingPayment::STATUS_REFUNDED,
                    ]))
                    ->exists();
                if ($protectedFutureExists) {
                    throw ValidationException::withMessages([
                        'target' => 'The existing plan has a financially protected future visit. Resolve it before repairing the confirmed schedule.',
                    ]);
                }

                $plan->generatedBookings()
                    ->where('status', CareBooking::STATUS_SCHEDULED)
                    ->where('scheduled_start_at', '>', now())
                    ->where('id', '!=', $lockedRequest->booking?->id)
                    ->update([
                        'status' => CareBooking::STATUS_CANCELLED,
                        'cancelled_at' => now(),
                        'cancelled_by_user_id' => $actorUserId,
                        'cancellation_reason' => 'Replaced by confirmed migration schedule.',
                    ]);
                $metadata = array_merge((array) $plan->metadata, [
                    'migration_confirmed_schedule' => $confirmedSchedule,
                    'migration_confirmed_schedule_hash' => $scheduleHash,
                    'migration_schedule_repaired_at' => now()->toIso8601String(),
                    'migration_schedule_repaired_by_user_id' => $actorUserId,
                ]);
                $plan->forceFill($this->scheduleAttributes($confirmedSchedule) + [
                    'schedule_version' => max(1, (int) $plan->schedule_version + 1),
                    'metadata' => $metadata,
                ])->save();
            }

            $lockedRequest->forceFill(['care_plan_id' => $plan->id])->save();
            $booking = $lockedRequest->booking;
            if ($booking) {
                $booking->forceFill([
                    'care_plan_id' => $plan->id,
                    'occurrence_key' => $booking->occurrence_key ?: 'regular-care-migrated:'.$plan->id.':booking:'.$booking->id,
                    'plan_visit_kind' => $booking->plan_visit_kind ?: 'regular',
                    'plan_schedule_version' => $booking->plan_schedule_version ?: $plan->schedule_version,
                ])->save();
                if (! $plan->source_care_booking_id) {
                    $plan->forceFill(['source_care_booking_id' => $booking->id])->save();
                }
                if ($scheduleChanged) {
                    $this->trust->recordEvent($booking, $actorUserId, $actorUserId ? 'admin' : 'system', 'regular_care_existing_customer_migrated', [
                        'care_plan_id' => $plan->id,
                        'payment_preserved' => (bool) $booking->payment,
                        'confirmed_schedule' => $confirmedSchedule,
                    ]);
                }
            }

            $this->occurrences->materialize($plan->fresh());

            if ($scheduleChanged) {
                CarePlanEvent::query()->create([
                    'care_plan_id' => $plan->id,
                    'actor_user_id' => $actorUserId,
                    'event_type' => $previousHash === '' ? 'existing_customer_migrated' : 'migration_schedule_repaired',
                    'reason' => 'Existing recurring customer migrated using an explicitly confirmed schedule.',
                    'payload' => [
                        'source_care_request_id' => $lockedRequest->id,
                        'retained_care_booking_id' => $booking?->id,
                        'retained_payment_id' => $booking?->payment?->id,
                        'confirmed_schedule' => $confirmedSchedule,
                        'confirmed_at' => now()->toIso8601String(),
                        'confirmed_by_user_id' => $actorUserId,
                        'confirmation_source' => 'controlled_migration_command',
                    ],
                ]);
            }

            return $this->health->reconcile($plan->fresh());
        });
    }

    private function previewPlan(CareRequest $request, ?int $caregiverId, mixed $rate, ?array $confirmedSchedule = null): ?CarePlan
    {
        if (! $caregiverId) {
            return null;
        }
        $schedule = $confirmedSchedule ?: $this->requestSchedule($request);
        $recipient = $request->recipient;

        return new CarePlan([
            'family_account_id' => $request->family_account_id,
            'family_user_id' => $request->family_user_id,
            'caregiver_user_id' => $caregiverId,
            'source_care_request_id' => $request->id,
            'source_care_booking_id' => $request->booking?->id,
            'status' => CarePlan::STATUS_ACTIVE,
            'title' => 'Regular care for '.($recipient?->full_name ?: $request->family?->name),
            'recipient_snapshot' => [
                'recipient_is_requester' => (bool) $recipient?->recipient_is_requester,
                'full_name' => $recipient?->full_name,
                'date_of_birth' => $recipient?->date_of_birth,
                'gender' => $recipient?->gender,
                'mobility_level' => $recipient?->mobility_level,
                'relationship_to_family' => $recipient?->relationship_to_family,
                'care_notes' => $recipient?->care_notes,
            ],
            'address_snapshot' => [
                'address_line1' => $request->address_line1, 'address_line2' => $request->address_line2,
                'city' => $request->city, 'state' => $request->state, 'zip' => $request->zip,
                'lat' => $request->lat, 'lng' => $request->lng,
            ],
            'task_snapshot' => $request->tasks->map(fn ($task) => ['id' => $task->id, 'name' => $task->name, 'task_note' => $task->pivot?->task_note])->values()->all(),
            'care_notes' => $request->scope_of_work ?: $request->additional_info,
            'schedule_days' => $schedule['days'],
            'schedule_start_time' => $schedule['start_time'],
            'schedule_end_time' => $schedule['end_time'],
            'starts_on' => $schedule['starts_on'],
            'ends_on' => $schedule['ends_on'],
            'timezone' => $schedule['timezone'],
            'schedule_version' => 1,
            'hourly_rate' => (float) ($rate ?: $request->budget_max ?: 30),
            'accepted_at' => $request->first_hire_at ?: now(),
            'activated_at' => $request->first_hire_at ?: now(),
            'payment_status' => CarePlan::PAYMENT_UNCHECKED,
        ]);
    }

    /**
     * @param  array<string,mixed>  $schedule
     * @return array{days:list<int>,start_time:string,end_time:string,starts_on:string,ends_on:string|null,timezone:string}
     */
    private function normalizeConfirmedSchedule(array $schedule): array
    {
        $rawDays = is_string($schedule['days'] ?? null)
            ? explode(',', (string) $schedule['days'])
            : (array) ($schedule['days'] ?? []);
        $days = collect($rawDays)
            ->map(fn ($day) => (int) trim((string) $day))
            ->filter(fn (int $day) => $day >= 0 && $day <= 6)
            ->unique()
            ->sort()
            ->values()
            ->all();
        if ($days === []) {
            throw ValidationException::withMessages(['schedule' => 'Confirmed weekdays must include at least one value from 0 through 6.']);
        }

        $startTime = $this->normalizeTime((string) ($schedule['start_time'] ?? ''));
        $endTime = $this->normalizeTime((string) ($schedule['end_time'] ?? ''));
        if ($this->timeMinutes($endTime) <= $this->timeMinutes($startTime)) {
            throw ValidationException::withMessages(['schedule' => 'Confirmed end time must be after the start time.']);
        }

        try {
            $startsOn = Carbon::parse((string) ($schedule['starts_on'] ?? ''))->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['schedule' => 'Confirmed start date is invalid.']);
        }
        try {
            $endsOn = filled($schedule['ends_on'] ?? null)
                ? Carbon::parse((string) $schedule['ends_on'])->toDateString()
                : null;
        } catch (\Throwable) {
            throw ValidationException::withMessages(['schedule' => 'Confirmed end date is invalid.']);
        }
        if ($endsOn && $endsOn < $startsOn) {
            throw ValidationException::withMessages(['schedule' => 'Confirmed end date must be on or after the start date.']);
        }

        $timezone = trim((string) ($schedule['timezone'] ?? ''));
        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            throw ValidationException::withMessages(['schedule' => 'Confirmed timezone is invalid.']);
        }

        return [
            'days' => $days,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'timezone' => $timezone,
        ];
    }

    /** @return array{days:list<int>,start_time:string,end_time:string,starts_on:string,ends_on:string|null,timezone:string} */
    private function requestSchedule(CareRequest $request): array
    {
        return [
            'days' => array_map('intval', $request->recurring_days ?? []),
            'start_time' => $this->normalizeTime((string) ($request->recurring_start_time ?: '09:00')),
            'end_time' => $this->normalizeTime((string) ($request->recurring_end_time ?: '13:00')),
            'starts_on' => $request->recurring_starts_on?->toDateString()
                ?: $request->requested_start_at?->toDateString()
                ?: now()->addDay()->toDateString(),
            'ends_on' => $request->recurring_ends_on?->toDateString(),
            'timezone' => (string) config('app.timezone', 'America/New_York'),
        ];
    }

    /** @return array<string,mixed> */
    private function scheduleAttributes(array $schedule): array
    {
        return [
            'schedule_days' => $schedule['days'],
            'schedule_start_time' => $schedule['start_time'],
            'schedule_end_time' => $schedule['end_time'],
            'starts_on' => $schedule['starts_on'],
            'ends_on' => $schedule['ends_on'],
            'timezone' => $schedule['timezone'],
        ];
    }

    private function normalizeTime(string $time): string
    {
        $time = trim($time);
        if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            $time .= ':00';
        }
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $time) !== 1) {
            throw ValidationException::withMessages(['schedule' => 'Confirmed start and end times must use HH:MM.']);
        }

        return $time;
    }

    private function timeMinutes(string $time): int
    {
        return ((int) substr($time, 0, 2) * 60) + (int) substr($time, 3, 2);
    }

    private function paymentAction(?CareBooking $booking): string
    {
        if (! $booking) {
            return 'No retained booking; future visits will follow the normal 48-hour authorization window.';
        }
        if (! $booking->payment) {
            return 'Retain booking; no payment exists. Payment preparation will handle it when due.';
        }

        return 'Preserve payment #'.$booking->payment->id.' in status '.$booking->payment->status.'; do not create or mutate a Stripe payment during migration.';
    }
}
