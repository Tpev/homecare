<?php

namespace App\Services\RegularCare;

use App\Models\CareBooking;
use App\Models\CarePlan;
use App\Models\CarePlanEvent;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Services\Booking\BookingTrustService;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Support\MarketplaceEvent;
use App\Support\MarketplacePricing;
use App\Support\WeeklySchedule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CarePlanOccurrenceService
{
    public function __construct(
        private readonly BookingTrustService $trust,
        private readonly MarketplacePricing $pricing,
        private readonly CarePlanHealthService $health,
        private readonly MarketplaceNotificationService $notifications,
    ) {}

    /**
     * @return array{created:Collection<int,CareBooking>,existing:Collection<int,CareBooking>,planned:list<array<string,mixed>>}
     */
    public function materialize(CarePlan $plan, bool $dryRun = false, ?Carbon $through = null): array
    {
        if (! $dryRun) {
            $plan = $this->synchronizeLifecycle($plan);
        }
        $plan->loadMissing(['family', 'caregiver', 'relationship']);
        $through ??= now($plan->timezone ?: config('app.timezone'))
            ->addWeeks($this->visitWindowWeeks())
            ->endOfDay();

        $planned = $this->scheduledOccurrences($plan, $through);
        $created = collect();
        $existing = collect();

        foreach ($planned as $occurrence) {
            $found = $this->findExistingOccurrence($plan, $occurrence);
            if ($found) {
                if (! $dryRun) {
                    $found = $this->reconcileRegularOccurrence($found, $plan, $occurrence);
                }
                $existing->push($found);

                continue;
            }

            if (! $dryRun) {
                $created->push($this->createBooking($plan, $occurrence, 'regular'));
            }
        }

        if (! $dryRun) {
            $plan->forceFill(['last_generated_at' => now()])->save();
            $this->health->reconcile($plan);
        }

        return compact('created', 'existing', 'planned');
    }

    public function synchronizeLifecycle(CarePlan $plan): CarePlan
    {
        if (in_array($plan->status, [CarePlan::STATUS_ENDED, CarePlan::STATUS_CANCELLED, CarePlan::STATUS_DECLINED], true)
            || ! $plan->pause_starts_on) {
            return $plan;
        }

        $timezone = $plan->timezone ?: (string) config('app.timezone', 'America/New_York');
        $today = now($timezone)->startOfDay();
        $pauseStarts = Carbon::parse($plan->pause_starts_on->toDateString(), $timezone)->startOfDay();
        $resumeOn = $plan->resumes_on
            ? Carbon::parse($plan->resumes_on->toDateString(), $timezone)->startOfDay()
            : null;

        if ($resumeOn && $today->gte($resumeOn)) {
            $previousStatus = $plan->status;
            $plan->forceFill([
                'status' => CarePlan::STATUS_ACTIVE,
                'pause_starts_on' => null,
                'resumes_on' => null,
                'paused_at' => null,
            ])->save();
            if ($previousStatus !== CarePlan::STATUS_ACTIVE) {
                CarePlanEvent::query()->create([
                    'care_plan_id' => $plan->id,
                    'actor_user_id' => null,
                    'event_type' => 'automatic_resume',
                    'reason' => 'The scheduled regular-care pause ended.',
                    'payload' => ['previous_status' => $previousStatus, 'resume_date' => $resumeOn->toDateString()],
                ]);
                $plan->loadMissing(['family', 'caregiver']);
                foreach ([
                    [$plan->family, route('family.care.show', $plan->id)],
                    [$plan->caregiver, route('caregiver.regular-clients.index')],
                ] as [$recipient, $url]) {
                    if (! $recipient) {
                        continue;
                    }
                    $this->notifications->notify(
                        recipients: $recipient,
                        eventKey: MarketplaceEvent::REGULAR_CARE_RESUMED,
                        title: 'Recurring care resumed',
                        body: 'The scheduled pause ended and future regular visits are active again.',
                        url: $url,
                        payload: ['care_plan_id' => $plan->id, 'resume_date' => $resumeOn->toDateString()],
                        subject: $plan,
                        dedupeKey: 'regular-care-auto-resumed:plan-'.$plan->id.'-date-'.$resumeOn->toDateString().'-user-'.$recipient->id
                    );
                }
            }

            return $plan->fresh();
        }

        if ($today->gte($pauseStarts) && $plan->status !== CarePlan::STATUS_PAUSED) {
            $previousStatus = $plan->status;
            $plan->forceFill([
                'status' => CarePlan::STATUS_PAUSED,
                'paused_at' => $plan->paused_at ?: now(),
            ])->save();
            CarePlanEvent::query()->create([
                'care_plan_id' => $plan->id,
                'actor_user_id' => null,
                'event_type' => 'scheduled_pause_started',
                'reason' => 'The family-scheduled regular-care pause started.',
                'payload' => ['previous_status' => $previousStatus, 'pause_date' => $pauseStarts->toDateString()],
            ]);
        }

        return $plan->fresh();
    }

    /**
     * @return list<array{key:string,start:Carbon,end:Carbon,local_date:string,kind:string}>
     */
    public function scheduledOccurrences(CarePlan $plan, Carbon $through): array
    {
        if (! in_array($plan->status, [CarePlan::STATUS_ACTIVE, CarePlan::STATUS_PAYMENT_ATTENTION], true)) {
            return [];
        }

        $timezone = $plan->timezone ?: (string) config('app.timezone', 'America/New_York');
        $startDate = Carbon::parse($plan->starts_on->toDateString(), $timezone)->startOfDay();
        $today = now($timezone)->startOfDay();
        $cursor = $startDate->greaterThan($today) ? $startDate : $today;
        $throughLocal = $through->copy()->setTimezone($timezone)->endOfDay();
        $endsOn = $plan->ends_on
            ? Carbon::parse($plan->ends_on->toDateString(), $timezone)->endOfDay()
            : null;
        if ($endsOn && $endsOn->lessThan($throughLocal)) {
            $throughLocal = $endsOn;
        }

        $slots = $plan->weeklyScheduleSlots();
        $occurrences = [];

        for ($date = $cursor->copy(); $date->lte($throughLocal); $date->addDay()) {
            $slot = WeeklySchedule::forDay($slots, (int) $date->dayOfWeek);
            if (! $slot || $this->dateIsPaused($plan, $date)) {
                continue;
            }

            $start = $this->localDateTime($date, $slot['start_time'], $timezone);
            if ($start->lte(now())) {
                continue;
            }

            $end = $this->localDateTime($date, $slot['end_time'], $timezone);
            $localDate = $date->toDateString();
            $occurrences[] = [
                'key' => $this->occurrenceKey($plan, $localDate, $slot['start_time'], 'regular'),
                'start' => $start,
                'end' => $end,
                'local_date' => $localDate,
                'kind' => 'regular',
            ];
        }

        return $occurrences;
    }

    /**
     * @return array{key:string,start:Carbon,end:Carbon,local_date:string,kind:string}
     */
    public function extraVisitOccurrence(CarePlan $plan, Carbon $start, Carbon $end): array
    {
        $timezone = $plan->timezone ?: (string) config('app.timezone', 'America/New_York');
        $localStart = $start->copy()->setTimezone($timezone);

        return [
            'key' => $this->occurrenceKey($plan, $localStart->toDateString(), $localStart->format('H:i:s'), 'extra'),
            'start' => $start,
            'end' => $end,
            'local_date' => $localStart->toDateString(),
            'kind' => 'extra',
        ];
    }

    public function createExtraVisit(CarePlan $plan, Carbon $start, Carbon $end): CareBooking
    {
        $occurrence = $this->extraVisitOccurrence($plan, $start, $end);
        $existing = CareBooking::query()->where('occurrence_key', $occurrence['key'])->first();

        if ($existing) {
            if ((int) $existing->care_plan_id !== (int) $plan->id
                || ! in_array($existing->plan_visit_kind, ['extra', 'completed_extra'], true)) {
                throw ValidationException::withMessages([
                    'extraVisitDate' => 'That time is already reserved by another recurring care visit.',
                ]);
            }
            if ($existing->status === CareBooking::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'extraVisitDate' => 'That extra visit was previously cancelled. Choose a new time or contact support to restore it.',
                ]);
            }

            return $existing;
        }

        if ($this->overlapsScheduledRegularOccurrence($plan, $start, $end)) {
            throw ValidationException::withMessages([
                'extraVisitDate' => 'That extra visit overlaps the plan regular schedule.',
            ]);
        }

        $overlapExists = $plan->generatedBookings()
            ->where('status', '!=', CareBooking::STATUS_CANCELLED)
            ->where('scheduled_start_at', '<', $end)
            ->where('scheduled_end_at', '>', $start)
            ->exists();
        if ($overlapExists) {
            throw ValidationException::withMessages([
                'extraVisitDate' => 'That extra visit overlaps an existing recurring care visit.',
            ]);
        }

        $booking = $this->createBooking($plan, $occurrence, 'extra');
        $this->health->reconcile($plan);

        return $booking;
    }

    public function attachSourceRequestAsFirstVisit(
        CarePlan $plan,
        CareRequest $request,
        CareRequestApplication $application
    ): CareBooking {
        $through = now($plan->timezone ?: config('app.timezone'))->addWeeks($this->visitWindowWeeks());
        $occurrence = $this->scheduledOccurrences($plan, $through)[0] ?? null;

        if (! $occurrence) {
            throw new \RuntimeException('No upcoming visit could be created from this regular schedule.');
        }

        $existing = CareBooking::query()->where('care_request_id', $request->id)->first();
        if ($existing) {
            $existing->forceFill([
                'care_plan_id' => $plan->id,
                'occurrence_key' => $existing->occurrence_key ?: $occurrence['key'],
                'plan_visit_kind' => $existing->plan_visit_kind ?: 'regular',
                'plan_schedule_version' => $existing->plan_schedule_version ?: $plan->schedule_version,
            ])->save();

            return $existing;
        }

        try {
            $booking = CareBooking::query()->create([
                'care_request_id' => $request->id,
                'care_plan_id' => $plan->id,
                'occurrence_key' => $occurrence['key'],
                'plan_visit_kind' => 'regular',
                'plan_schedule_version' => $plan->schedule_version,
                'care_request_application_id' => $application->id,
                'family_account_id' => $plan->family_account_id,
                'family_user_id' => $plan->family_user_id,
                'caregiver_user_id' => $plan->caregiver_user_id,
                'agreement_snapshot' => $this->trust->buildAgreementSnapshot($request->fresh(['recipient', 'tasks']), $application),
                'family_terms_accepted_at' => now(),
                'caregiver_terms_accepted_at' => now(),
                'status' => CareBooking::STATUS_SCHEDULED,
                'scheduled_start_at' => $occurrence['start'],
                'scheduled_end_at' => $occurrence['end'],
                'expected_minutes' => (int) $occurrence['start']->diffInMinutes($occurrence['end'], false),
            ]);
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $booking = CareBooking::query()->where('care_request_id', $request->id)->first();
            if (! $booking || (int) $booking->care_plan_id !== (int) $plan->id) {
                throw $exception;
            }

            return $booking;
        }

        $this->trust->seedTaskChecks($booking, $request->fresh(['tasks']));
        $this->trust->recordEvent($booking, $plan->family_user_id, 'family', 'regular_care_marketplace_hire', [
            'care_plan_id' => $plan->id,
            'occurrence_key' => $occurrence['key'],
        ]);

        return $booking;
    }

    public function reconcileNextBooking(CarePlan $plan): CarePlan
    {
        return $this->health->reconcile($plan);
    }

    /**
     * @param  array{key:string,start:Carbon,end:Carbon,local_date:string,kind:string}  $occurrence
     */
    private function findExistingOccurrence(CarePlan $plan, array $occurrence): ?CareBooking
    {
        return CareBooking::query()
            ->where(function ($query) use ($plan, $occurrence) {
                $query->where('occurrence_key', $occurrence['key'])
                    ->orWhere(function ($fallback) use ($plan, $occurrence) {
                        $fallback->where('care_plan_id', $plan->id)
                            ->where('scheduled_start_at', $occurrence['start'])
                            ->where(function ($kind): void {
                                $kind->whereNull('plan_visit_kind')
                                    ->orWhere('plan_visit_kind', 'regular');
                            });
                    });
            })
            ->first();
    }

    public function overlapsScheduledRegularOccurrence(CarePlan $plan, Carbon $start, Carbon $end): bool
    {
        $timezone = $plan->timezone ?: (string) config('app.timezone', 'America/New_York');
        $localDate = $start->copy()->setTimezone($timezone)->startOfDay();
        if ($localDate->lt(Carbon::parse($plan->starts_on->toDateString(), $timezone)->startOfDay())
            || ($plan->ends_on && $localDate->gt(Carbon::parse($plan->ends_on->toDateString(), $timezone)->endOfDay()))
            || $this->dateIsPaused($plan, $localDate)) {
            return false;
        }

        $slot = WeeklySchedule::forDay($plan->weeklyScheduleSlots(), (int) $localDate->dayOfWeek);
        if (! $slot) {
            return false;
        }

        $regularStart = $this->localDateTime($localDate, $slot['start_time'], $timezone);
        $regularEnd = $this->localDateTime($localDate, $slot['end_time'], $timezone);

        return $regularStart->lt($end) && $regularEnd->gt($start);
    }

    /**
     * @param  array{key:string,start:Carbon,end:Carbon,local_date:string,kind:string}  $occurrence
     */
    private function createBooking(CarePlan $plan, array $occurrence, string $kind): CareBooking
    {
        try {
            return DB::transaction(function () use ($plan, $occurrence, $kind): CareBooking {
                $existing = $this->findExistingOccurrence($plan, $occurrence);
                if ($existing) {
                    return $existing;
                }

                $address = $plan->address_snapshot ?? [];
                $recipient = $plan->recipient_snapshot ?? [];
                $hourlyRate = $this->pricing->hourlyRateForFamily($plan->family, (float) $plan->hourly_rate);

                $request = CareRequest::query()->create([
                    'family_account_id' => $plan->family_account_id,
                    'family_user_id' => $plan->family_user_id,
                    'care_plan_id' => $plan->id,
                    'is_system_generated' => true,
                    'title' => ($kind === 'extra' ? 'Extra visit: ' : 'Recurring care: ').$plan->recipientName().' with '.$plan->caregiver?->name,
                    'additional_info' => $plan->care_notes,
                    'scope_of_work' => $plan->care_notes,
                    'time_expectations' => $occurrence['start']->format('l, F j \f\r\o\m g:i A').' to '.$occurrence['end']->format('g:i A'),
                    'home_access_notes' => data_get($plan->metadata, 'home_access_notes'),
                    'preferred_response_hours' => 12,
                    'status' => CareRequest::STATUS_FILLED,
                    'request_type' => CareRequest::TYPE_ONE_TIME,
                    'budget_min' => $hourlyRate,
                    'budget_max' => $hourlyRate,
                    'requested_start_at' => $occurrence['start'],
                    'requested_end_at' => $occurrence['end'],
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
                    'care_recipient_profile_id' => $plan->care_recipient_profile_id,
                    'care_recipient_profile_version_id' => $plan->care_recipient_profile_version_id,
                ]);

                $taskPayload = collect($plan->task_snapshot ?? [])
                    ->filter(fn (array $task) => ! empty($task['id']))
                    ->mapWithKeys(fn (array $task) => [(int) $task['id'] => ['task_note' => $task['task_note'] ?? null]])
                    ->all();
                $request->tasks()->sync($taskPayload);

                $application = CareRequestApplication::query()->create([
                    'care_request_id' => $request->id,
                    'caregiver_user_id' => $plan->caregiver_user_id,
                    'status' => CareRequestApplication::STATUS_HIRED,
                    'proposed_rate' => $hourlyRate,
                    'cover_note' => 'Confirmed through recurring care.',
                ]);

                $booking = CareBooking::query()->create([
                    'care_request_id' => $request->id,
                    'care_plan_id' => $plan->id,
                    'occurrence_key' => $occurrence['key'],
                    'plan_visit_kind' => $kind,
                    'plan_schedule_version' => $plan->schedule_version,
                    'care_request_application_id' => $application->id,
                    'family_account_id' => $plan->family_account_id,
                    'family_user_id' => $plan->family_user_id,
                    'caregiver_user_id' => $plan->caregiver_user_id,
                    'agreement_snapshot' => $this->trust->buildAgreementSnapshot($request->fresh(['recipient', 'tasks']), $application),
                    'family_terms_accepted_at' => now(),
                    'caregiver_terms_accepted_at' => now(),
                    'status' => CareBooking::STATUS_SCHEDULED,
                    'scheduled_start_at' => $occurrence['start'],
                    'scheduled_end_at' => $occurrence['end'],
                    'expected_minutes' => (int) $occurrence['start']->diffInMinutes($occurrence['end'], false),
                ]);

                $this->trust->seedTaskChecks($booking, $request->fresh(['tasks']));
                $this->trust->recordEvent($booking, null, 'system', 'regular_care_visit_created', [
                    'care_plan_id' => $plan->id,
                    'occurrence_key' => $occurrence['key'],
                    'visit_kind' => $kind,
                    'schedule_version' => $plan->schedule_version,
                ]);

                $plan->relationship?->forceFill([
                    'last_care_request_id' => $request->id,
                    'last_care_booking_id' => $booking->id,
                ])->save();

                return $booking;
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $existing = $this->findExistingOccurrence($plan, $occurrence);
            if (! $existing) {
                throw $exception;
            }

            return $existing;
        }
    }

    /**
     * @param  array{key:string,start:Carbon,end:Carbon,local_date:string,kind:string}  $occurrence
     */
    private function reconcileRegularOccurrence(CareBooking $booking, CarePlan $plan, array $occurrence): CareBooking
    {
        $updates = [
            'occurrence_key' => $occurrence['key'],
            'plan_visit_kind' => 'regular',
            'plan_schedule_version' => $plan->schedule_version,
        ];

        if ($booking->status === CareBooking::STATUS_SCHEDULED) {
            $updates += [
                'scheduled_start_at' => $occurrence['start'],
                'scheduled_end_at' => $occurrence['end'],
                'expected_minutes' => (int) $occurrence['start']->diffInMinutes($occurrence['end'], false),
            ];
        } elseif ($booking->status === CareBooking::STATUS_CANCELLED
            && ! $this->isIntentionallySuppressedOccurrence($booking)) {
            $updates += [
                'status' => CareBooking::STATUS_SCHEDULED,
                'scheduled_start_at' => $occurrence['start'],
                'scheduled_end_at' => $occurrence['end'],
                'expected_minutes' => (int) $occurrence['start']->diffInMinutes($occurrence['end'], false),
                'cancelled_at' => null,
                'cancelled_by_user_id' => null,
                'cancellation_reason' => null,
            ];
        }

        $booking->forceFill($updates)->save();

        return $booking->fresh();
    }

    private function isIntentionallySuppressedOccurrence(CareBooking $booking): bool
    {
        $reason = (string) $booking->cancellation_reason;

        return $reason === 'Skipped by family. Regular care continues.'
            || $reason === 'Regular care paused by family.'
            || $reason === 'Regular care ended by family.'
            || str_starts_with($reason, 'Operations paused regular care:')
            || str_starts_with($reason, 'Operations ended regular care:');
    }

    private function dateIsPaused(CarePlan $plan, Carbon $date): bool
    {
        if (! $plan->pause_starts_on) {
            return false;
        }

        $pauseStart = $plan->pause_starts_on->toDateString();
        $resume = $plan->resumes_on?->toDateString();
        $value = $date->toDateString();

        return $value >= $pauseStart && ($resume === null || $value < $resume);
    }

    private function localDateTime(Carbon $date, string $time, string $timezone): Carbon
    {
        return Carbon::parse($date->toDateString().' '.substr($time, 0, 8), $timezone)
            ->setTimezone((string) config('app.timezone', $timezone));
    }

    private function occurrenceKey(CarePlan $plan, string $localDate, string $time, string $kind): string
    {
        return implode(':', [
            'regular-care',
            $plan->id,
            $kind,
            $localDate,
            substr($time, 0, 5),
        ]);
    }

    private function visitWindowWeeks(): int
    {
        return max(1, min(12, (int) config('marketplace.regular_care.visit_window_weeks', 6)));
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $message = strtolower($exception->getMessage());

        return in_array($sqlState, ['23000', '23505'], true)
            || str_contains($message, 'duplicate')
            || str_contains($message, 'unique constraint');
    }
}
