<?php

namespace App\Services\RegularCare;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CarePlan;
use App\Models\CarePlanEvent;
use App\Models\CarePlanScheduleChange;
use App\Models\CareRelationship;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\Booking\BookingTrustService;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\FamilyBillingService;
use App\Services\Payments\StripeClient;
use App\Support\CaregiverPrelaunch;
use App\Support\FunnelTracker;
use App\Support\MarketplaceEvent;
use App\Support\MarketplacePricing;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CarePlanService
{
    private const DAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    public function __construct(
        private readonly BookingPaymentService $payments,
        private readonly BookingTrustService $trust,
        private readonly FamilyBillingService $billing,
        private readonly StripeClient $stripe,
        private readonly MarketplaceNotificationService $notifications,
        private readonly MarketplacePricing $pricing,
        private readonly CarePlanOccurrenceService $occurrences,
        private readonly CarePlanPaymentWindowService $paymentWindow,
        private readonly CarePlanHealthService $health,
    ) {}

    public function platformHourlyRate(): float
    {
        return $this->pricing->rateForTier((string) config('marketplace.default_pricing_tier', 'standard'));
    }

    public function hourlyRateForFamily(User $family): float
    {
        return $this->pricing->hourlyRateForFamily($family, $this->platformHourlyRate());
    }

    /**
     * @return array{ready:bool,customer_id:string|null,card:array<string,mixed>|null}
     */
    public function billingSummaryFor(User $family): array
    {
        if ((bool) config('services.stripe.bypass', false) && ! $family->stripe_customer_id) {
            $this->stripe->ensureFamilyCustomer($family);
            $family->refresh();
        }

        return $this->billing->summaryFor($family);
    }

    public function hiredApplicationFor(CareRequest $source): ?CareRequestApplication
    {
        $source->loadMissing([
            'applications.caregiver.caregiverProfile',
            'booking',
        ]);

        $booking = $source->booking;
        if ($booking?->care_request_application_id) {
            return $source->applications->firstWhere('id', $booking->care_request_application_id);
        }

        return $source->applications->firstWhere('status', CareRequestApplication::STATUS_HIRED);
    }

    public function sourceIsEligible(CareRequest $source, User $family): bool
    {
        if ((int) $source->family_user_id !== (int) $family->id) {
            return false;
        }

        if ($source->care_plan_id || CarePlan::query()->where('source_care_request_id', $source->id)->exists()) {
            return false;
        }

        $source->loadMissing(['booking', 'applications']);
        $booking = $source->booking;
        $application = $this->hiredApplicationFor($source);

        return $booking
            && $application
            && ! in_array((string) $booking->status, [
                CareBooking::STATUS_CANCELLED,
                CareBooking::STATUS_DISPUTED,
            ], true);
    }

    /**
     * @return array<string,mixed>
     */
    public function defaultsFromRequest(CareRequest $source): array
    {
        $source->loadMissing([
            'recipient',
            'tasks',
            'family:id,email',
            'booking',
            'applications.caregiver.caregiverProfile',
        ]);

        $application = $this->hiredApplicationFor($source);
        $caregiver = $application?->caregiver;
        $startAt = $source->requested_start_at ?: $source->booking?->scheduled_start_at;
        $endAt = $source->requested_end_at ?: $source->booking?->scheduled_end_at;

        if ($source->request_type === CareRequest::TYPE_RECURRING && $source->recurring_days) {
            $days = collect($source->recurring_days)->map(fn ($day) => (int) $day)->values()->all();
            $startTime = $this->normalizeTimeString($source->recurring_start_time ?: '09:00');
            $endTime = $this->normalizeTimeString($source->recurring_end_time ?: '13:00');
            $startsOn = optional($source->recurring_starts_on)?->isFuture()
                ? $source->recurring_starts_on
                : now()->addDay()->toDateString();
        } else {
            $reference = $startAt ? Carbon::parse($startAt)->copy()->addWeek() : now()->addDay()->setTime(9, 0);
            $days = [(int) $reference->dayOfWeek];
            $startTime = $startAt ? Carbon::parse($startAt)->format('H:i:s') : '09:00:00';
            $endTime = $endAt ? Carbon::parse($endAt)->format('H:i:s') : '13:00:00';
            $startsOn = $reference->toDateString();
        }

        return [
            'title' => 'Regular care with '.($caregiver?->name ?: 'your caregiver'),
            'recipient_name' => $source->recipient?->full_name,
            'caregiver_name' => $caregiver?->name,
            'schedule_days' => $days,
            'schedule_start_time' => substr($startTime, 0, 5),
            'schedule_end_time' => substr($endTime, 0, 5),
            'starts_on' => (string) $startsOn,
            'hourly_rate' => $this->pricing->hourlyRateForFamily(
                $source->family,
                $this->platformHourlyRate()
            ),
            'care_notes' => $source->recipient?->care_notes ?: $source->scope_of_work,
            'family_message' => 'We would love to make this a regular visit.',
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function sendOfferFromRequest(CareRequest $source, User $family, array $payload): CarePlan
    {
        $existingPlan = CarePlan::query()->where('source_care_request_id', $source->id)->first();
        if ($existingPlan) {
            throw ValidationException::withMessages([
                'source' => $this->existingAgreementMessage($existingPlan),
            ]);
        }
        if (! $this->sourceIsEligible($source, $family)) {
            throw ValidationException::withMessages([
                'source' => 'This request does not have a hired caregiver available for regular care.',
            ]);
        }

        $billingSummary = $this->billingSummaryFor($family);
        if (! ($billingSummary['ready'] ?? false)) {
            throw new PaymentException('Add a payment method before sending a regular-care offer.');
        }

        $source->loadMissing([
            'recipient',
            'tasks',
            'booking',
            'applications.caregiver.caregiverProfile',
        ]);

        $application = $this->hiredApplicationFor($source);
        $caregiver = $application?->caregiver;
        if (! $application || ! $caregiver) {
            throw ValidationException::withMessages([
                'source' => 'The hired caregiver is no longer available on this request.',
            ]);
        }

        if (! CaregiverPrelaunch::familyCanProceedWithCaregiver($caregiver->email, $source, (int) $caregiver->id)) {
            throw ValidationException::withMessages([
                'source' => CaregiverPrelaunch::familyHireMessage(),
            ]);
        }

        $schedule = $this->normalizeSchedulePayload($payload);
        $hourlyRate = $this->hourlyRateForFamily($family);

        try {
            [$plan, $created] = DB::transaction(function () use ($source, $family, $caregiver, $payload, $schedule, $hourlyRate): array {
                $lockedSource = CareRequest::query()->lockForUpdate()->findOrFail($source->id);
                if ((int) $lockedSource->family_user_id !== (int) $family->id) {
                    abort(403);
                }

                $existing = CarePlan::query()->where('source_care_request_id', $lockedSource->id)->first();
                if ($existing) {
                    if (! $lockedSource->care_plan_id) {
                        $lockedSource->forceFill(['care_plan_id' => $existing->id])->save();
                    }

                    return [$existing, false];
                }

                $relationship = CareRelationship::query()->firstOrNew([
                    'family_user_id' => $family->id,
                    'caregiver_user_id' => $caregiver->id,
                    'recipient_name' => $source->recipient?->full_name,
                ]);

                $relationship->fill([
                    'source_care_request_id' => $relationship->source_care_request_id ?: $source->id,
                    'last_care_request_id' => $source->id,
                    'last_care_booking_id' => $source->booking?->id,
                    'status' => CareRelationship::STATUS_ACTIVE,
                    'last_visit_at' => $source->booking?->completed_at ?: $source->booking?->scheduled_start_at,
                ])->save();

                $plan = CarePlan::query()->create([
                    'care_relationship_id' => $relationship->id,
                    'family_user_id' => $family->id,
                    'caregiver_user_id' => $caregiver->id,
                    'source_care_request_id' => $source->id,
                    'source_care_booking_id' => $source->booking?->id,
                    'status' => CarePlan::STATUS_PENDING_CAREGIVER,
                    'title' => trim((string) ($payload['title'] ?? '')) ?: 'Regular care with '.$caregiver->name,
                    'recipient_snapshot' => $this->recipientSnapshot($source),
                    'address_snapshot' => $this->addressSnapshot($source),
                    'task_snapshot' => $this->taskSnapshot($source),
                    'care_notes' => trim((string) ($payload['care_notes'] ?? '')) ?: null,
                    'schedule_days' => $schedule['days'],
                    'schedule_start_time' => $schedule['start_time'],
                    'schedule_end_time' => $schedule['end_time'],
                    'starts_on' => $schedule['starts_on'],
                    'ends_on' => $schedule['ends_on'],
                    'timezone' => (string) config('app.timezone', 'America/New_York'),
                    'hourly_rate' => $hourlyRate,
                    'family_message' => trim((string) ($payload['family_message'] ?? '')) ?: null,
                    'offered_at' => now(),
                    'expires_at' => now()->addHours(72),
                    'payment_status' => CarePlan::PAYMENT_UNCHECKED,
                    'metadata' => [
                        'home_access_notes' => $source->home_access_notes,
                        'origin' => 'known_caregiver_offer',
                    ],
                ]);
                $lockedSource->forceFill(['care_plan_id' => $plan->id])->save();

                return [$plan, true];
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $plan = CarePlan::query()->where('source_care_request_id', $source->id)->first();
            if (! $plan) {
                throw $exception;
            }
            $created = false;
            $source->forceFill(['care_plan_id' => $plan->id])->save();
        }

        if ($created) {
            $this->notifications->notify(
                recipients: $caregiver,
                eventKey: MarketplaceEvent::REGULAR_CARE_OFFERED,
                title: 'Regular care offer',
                body: $family->name.' offered you a regular care schedule.',
                url: route('caregiver.regular-clients.index'),
                payload: ['care_plan_id' => $plan->id, 'care_request_id' => $source->id],
                subject: $plan,
                dedupeKey: 'regular-care-offered:plan-'.$plan->id.'-user-'.$caregiver->id
            );

            FunnelTracker::track('regular_care_offer_sent', $family, $plan, [
                'source_request_id' => $source->id,
                'caregiver_user_id' => $caregiver->id,
                'hourly_rate' => $hourlyRate,
                'source_application_id' => $application->id,
            ]);
        }

        return $plan->fresh(['caregiver']);
    }

    public function activateFromRecurringRequest(
        CareRequest $source,
        CareRequestApplication $application,
        User $family
    ): CarePlan {
        if ((int) $source->family_user_id !== (int) $family->id || ! $source->isRecurring()) {
            throw ValidationException::withMessages([
                'request' => 'This regular-care request cannot be activated.',
            ]);
        }

        $source->loadMissing(['recipient', 'tasks', 'family', 'applications', 'booking']);
        $application->loadMissing('caregiver.caregiverProfile');
        $caregiver = $application->caregiver;
        if (! $caregiver) {
            throw ValidationException::withMessages(['caregiver' => 'The selected caregiver is unavailable.']);
        }

        $schedule = $this->normalizeSchedulePayload([
            'schedule_days' => $source->recurring_days,
            'schedule_start_time' => $source->recurring_start_time,
            'schedule_end_time' => $source->recurring_end_time,
            'starts_on' => $source->recurring_starts_on?->toDateString(),
            'ends_on' => $source->recurring_ends_on?->toDateString(),
        ]);
        $schedule['starts_on'] = $this->alignStartDateToSchedule($schedule['starts_on'], $schedule['days']);
        $hourlyRate = $this->hourlyRateForFamily($family);

        try {
            $plan = DB::transaction(function () use (
                $source,
                $application,
                $family,
                $caregiver,
                $schedule,
                $hourlyRate
            ): CarePlan {
                $lockedSource = CareRequest::query()->lockForUpdate()->findOrFail($source->id);
                $existing = CarePlan::query()
                    ->where('source_care_request_id', $lockedSource->id)
                    ->first();
                if ($existing) {
                    if ($this->requiresNewAgreementSource($existing)) {
                        throw ValidationException::withMessages([
                            'request' => $this->existingAgreementMessage($existing),
                        ]);
                    }
                    if (! $lockedSource->care_plan_id) {
                        $lockedSource->forceFill(['care_plan_id' => $existing->id])->save();
                    }

                    return $existing;
                }

                $application->forceFill(['status' => CareRequestApplication::STATUS_HIRED])->save();
                $source->applications()
                    ->where('care_request_applications.id', '!=', $application->id)
                    ->whereIn('status', [
                        CareRequestApplication::STATUS_APPLIED,
                        CareRequestApplication::STATUS_SHORTLISTED,
                    ])
                    ->update(['status' => CareRequestApplication::STATUS_NOT_SELECTED]);

                $lockedSource->forceFill([
                    'status' => CareRequest::STATUS_FILLED,
                    'first_hire_at' => $lockedSource->first_hire_at ?: now(),
                    'recurring_starts_on' => $schedule['starts_on'],
                ])->save();

                $relationship = CareRelationship::query()->firstOrNew([
                    'family_user_id' => $family->id,
                    'caregiver_user_id' => $caregiver->id,
                    'recipient_name' => $source->recipient?->full_name,
                ]);
                $relationship->fill([
                    'source_care_request_id' => $relationship->source_care_request_id ?: $source->id,
                    'last_care_request_id' => $source->id,
                    'status' => CareRelationship::STATUS_ACTIVE,
                ])->save();

                $createdPlan = CarePlan::query()->create([
                    'care_relationship_id' => $relationship->id,
                    'family_user_id' => $family->id,
                    'caregiver_user_id' => $caregiver->id,
                    'source_care_request_id' => $source->id,
                    'status' => CarePlan::STATUS_ACTIVE,
                    'title' => 'Regular care with '.$caregiver->name,
                    'recipient_snapshot' => $this->recipientSnapshot($source),
                    'address_snapshot' => $this->addressSnapshot($source),
                    'task_snapshot' => $this->taskSnapshot($source),
                    'care_notes' => $source->recipient?->care_notes ?: $source->scope_of_work,
                    'schedule_days' => $schedule['days'],
                    'schedule_start_time' => $schedule['start_time'],
                    'schedule_end_time' => $schedule['end_time'],
                    'starts_on' => $schedule['starts_on'],
                    'ends_on' => $schedule['ends_on'],
                    'timezone' => (string) config('app.timezone', 'America/New_York'),
                    'hourly_rate' => $hourlyRate,
                    'accepted_at' => now(),
                    'activated_at' => now(),
                    'payment_status' => CarePlan::PAYMENT_UNCHECKED,
                    'metadata' => [
                        'home_access_notes' => $source->home_access_notes,
                        'origin' => 'recurring_marketplace_request',
                        'source_application_id' => $application->id,
                    ],
                ]);
                $lockedSource->forceFill(['care_plan_id' => $createdPlan->id])->save();

                return $createdPlan;
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $plan = CarePlan::query()->where('source_care_request_id', $source->id)->first();
            if (! $plan) {
                throw $exception;
            }
        }

        if (! $source->booking) {
            $firstBooking = $this->occurrences->attachSourceRequestAsFirstVisit($plan, $source, $application);
            $plan->forceFill(['source_care_booking_id' => $firstBooking->id])->save();
        }

        $this->occurrences->materialize($plan->fresh());
        $this->paymentWindow->preparePlan($plan->fresh());
        $plan = $this->health->reconcile($plan->fresh());

        $this->notifications->notify(
            recipients: $caregiver,
            eventKey: MarketplaceEvent::CAREGIVER_HIRED,
            title: 'Regular care confirmed',
            body: $family->name.' selected you for regular care. Your confirmed visits are in My Visits.',
            url: route('caregiver.shifts.index'),
            payload: ['care_plan_id' => $plan->id, 'care_request_id' => $source->id],
            subject: $plan,
            dedupeKey: 'regular-care-marketplace-hired:plan-'.$plan->id.'-user-'.$caregiver->id
        );

        return $plan->fresh(['nextBooking.payment', 'generatedBookings']);
    }

    public function acceptOffer(CarePlan $plan, User $caregiver): CarePlan
    {
        if ((int) $plan->caregiver_user_id !== (int) $caregiver->id) {
            abort(403);
        }

        if ($plan->status !== CarePlan::STATUS_PENDING_CAREGIVER) {
            return $plan->fresh();
        }

        $profile = $caregiver->caregiverProfile;
        if (! $profile || ! $profile->isMarketplaceReady()) {
            throw ValidationException::withMessages([
                'offer' => 'Complete your caregiver profile before accepting regular-care offers.',
            ]);
        }

        [$activatedPlan, $transitioned] = DB::transaction(function () use ($plan): array {
            $lockedPlan = CarePlan::query()->lockForUpdate()->findOrFail($plan->id);
            if ($lockedPlan->status !== CarePlan::STATUS_PENDING_CAREGIVER) {
                return [$lockedPlan, false];
            }

            $lockedPlan->forceFill([
                'status' => CarePlan::STATUS_ACTIVE,
                'responded_at' => now(),
                'accepted_at' => now(),
                'activated_at' => now(),
                'payment_status' => CarePlan::PAYMENT_UNCHECKED,
                'last_error' => null,
            ])->save();

            return [$lockedPlan->fresh(['family', 'caregiver']), true];
        });

        if (! $transitioned) {
            return $activatedPlan->fresh(['nextBooking.payment', 'family', 'caregiver']);
        }

        $generated = $this->occurrences->materialize($activatedPlan);
        $this->paymentWindow->preparePlan($activatedPlan);
        $activatedPlan = $this->health->reconcile($activatedPlan);
        $booking = $activatedPlan->nextBooking;

        $this->notifications->notify(
            recipients: $activatedPlan->family,
            eventKey: MarketplaceEvent::REGULAR_CARE_ACCEPTED,
            title: 'Regular care accepted',
            body: $caregiver->name.' accepted your regular care schedule.',
            url: route('family.care.show', $activatedPlan->id),
            payload: ['care_plan_id' => $activatedPlan->id, 'care_booking_id' => $booking?->id],
            subject: $activatedPlan,
            dedupeKey: 'regular-care-accepted:plan-'.$activatedPlan->id.'-user-'.$activatedPlan->family_user_id
        );

        return $activatedPlan->fresh(['nextBooking.payment', 'family', 'caregiver', 'generatedBookings']);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function counterOffer(CarePlan $plan, User $caregiver, array $payload): CarePlan
    {
        if ((int) $plan->caregiver_user_id !== (int) $caregiver->id) {
            abort(403);
        }

        if ($plan->status !== CarePlan::STATUS_PENDING_CAREGIVER) {
            return $plan->fresh();
        }

        $schedule = $this->normalizeSchedulePayload($payload);

        $plan->forceFill([
            'status' => CarePlan::STATUS_COUNTERED,
            'counter_schedule_days' => $schedule['days'],
            'counter_schedule_start_time' => $schedule['start_time'],
            'counter_schedule_end_time' => $schedule['end_time'],
            'counter_starts_on' => $schedule['starts_on'],
            'counter_note' => trim((string) ($payload['counter_note'] ?? '')) ?: null,
            'responded_at' => now(),
            'caregiver_note' => trim((string) ($payload['counter_note'] ?? '')) ?: null,
        ])->save();

        $plan->loadMissing(['family', 'caregiver']);
        $this->notifications->notify(
            recipients: $plan->family,
            eventKey: MarketplaceEvent::REGULAR_CARE_COUNTERED,
            title: 'Regular care counteroffer',
            body: $caregiver->name.' suggested a different schedule.',
            url: route('family.care.show', $plan->id),
            payload: ['care_plan_id' => $plan->id],
            subject: $plan,
            dedupeKey: 'regular-care-countered:plan-'.$plan->id.'-user-'.$plan->family_user_id
        );

        return $plan->fresh(['family', 'caregiver']);
    }

    public function declineOffer(CarePlan $plan, User $caregiver, ?string $note = null): CarePlan
    {
        if ((int) $plan->caregiver_user_id !== (int) $caregiver->id) {
            abort(403);
        }

        if (! in_array($plan->status, [CarePlan::STATUS_PENDING_CAREGIVER, CarePlan::STATUS_COUNTERED], true)) {
            return $plan->fresh();
        }

        $plan->forceFill([
            'status' => CarePlan::STATUS_DECLINED,
            'responded_at' => now(),
            'declined_at' => now(),
            'caregiver_note' => trim((string) $note) ?: $plan->caregiver_note,
        ])->save();

        $plan->loadMissing(['family', 'caregiver']);
        $this->notifications->notify(
            recipients: $plan->family,
            eventKey: MarketplaceEvent::REGULAR_CARE_DECLINED,
            title: 'Regular care declined',
            body: $caregiver->name.' declined the regular care offer.',
            url: route('family.care.show', $plan->id),
            payload: ['care_plan_id' => $plan->id],
            subject: $plan,
            dedupeKey: 'regular-care-declined:plan-'.$plan->id.'-user-'.$plan->family_user_id
        );

        return $plan->fresh(['family', 'caregiver']);
    }

    public function acceptCounter(CarePlan $plan, User $family): CarePlan
    {
        if ((int) $plan->family_user_id !== (int) $family->id) {
            abort(403);
        }

        if ($plan->status !== CarePlan::STATUS_COUNTERED) {
            return $plan->fresh();
        }

        $billingSummary = $this->billingSummaryFor($family);
        if (! ($billingSummary['ready'] ?? false)) {
            throw new PaymentException('Add a payment method before accepting this schedule.');
        }

        $activatedPlan = DB::transaction(function () use ($plan): CarePlan {
            $plan->forceFill([
                'status' => CarePlan::STATUS_ACTIVE,
                'schedule_days' => $plan->counter_schedule_days ?: $plan->schedule_days,
                'schedule_start_time' => $plan->counter_schedule_start_time ?: $plan->schedule_start_time,
                'schedule_end_time' => $plan->counter_schedule_end_time ?: $plan->schedule_end_time,
                'starts_on' => $plan->counter_starts_on ?: $plan->starts_on,
                'accepted_at' => now(),
                'activated_at' => now(),
                'payment_status' => CarePlan::PAYMENT_UNCHECKED,
                'last_error' => null,
            ])->save();

            return $plan->fresh(['family', 'caregiver']);
        });

        $this->occurrences->materialize($activatedPlan);
        $this->paymentWindow->preparePlan($activatedPlan);
        $activatedPlan = $this->health->reconcile($activatedPlan);
        $booking = $activatedPlan->nextBooking;

        $this->notifications->notify(
            recipients: $activatedPlan->caregiver,
            eventKey: MarketplaceEvent::REGULAR_CARE_ACCEPTED,
            title: 'Counteroffer accepted',
            body: $family->name.' accepted your regular care schedule.',
            url: route('caregiver.regular-clients.index'),
            payload: ['care_plan_id' => $activatedPlan->id, 'care_booking_id' => $booking?->id],
            subject: $activatedPlan,
            dedupeKey: 'regular-care-counter-accepted:plan-'.$activatedPlan->id.'-user-'.$activatedPlan->caregiver_user_id
        );

        return $activatedPlan->fresh(['nextBooking.payment', 'family', 'caregiver']);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function requestScheduleChange(CarePlan $plan, User $family, array $payload): CarePlanScheduleChange
    {
        $this->assertFamilyOwnsPlan($plan, $family);
        $schedule = $this->normalizeSchedulePayload($payload);
        $timezone = $plan->timezone ?: (string) config('app.timezone', 'America/New_York');
        $effectiveOn = Carbon::parse((string) ($payload['effective_on'] ?? $schedule['starts_on']), $timezone)->startOfDay();
        if ($effectiveOn->lt(now($timezone)->startOfDay())) {
            throw ValidationException::withMessages([
                'effectiveOn' => 'Choose today or a future date.',
            ]);
        }

        $existingStart = $plan->generatedBookings()
            ->whereIn('status', [CareBooking::STATUS_SCHEDULED, CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED])
            ->where('scheduled_start_at', '>=', $effectiveOn->copy()->setTimezone((string) config('app.timezone', $timezone)))
            ->orderBy('scheduled_start_at')
            ->value('scheduled_start_at');
        $proposedStart = $this->firstProposedOccurrenceStart($schedule, $effectiveOn, $timezone);
        $firstAffectedStart = collect([
            $existingStart ? Carbon::parse($existingStart) : null,
            $proposedStart,
        ])->filter()->sortBy(fn (Carbon $date) => $date->timestamp)->first();
        if ($firstAffectedStart && $firstAffectedStart->lt(now()->addHours(24))) {
            throw ValidationException::withMessages([
                'effectiveOn' => 'Choose a date at least 24 hours from now. Change a closer visit separately.',
            ]);
        }

        $change = CarePlanScheduleChange::query()->create([
            'care_plan_id' => $plan->id,
            'requested_by_user_id' => $family->id,
            'type' => CarePlanScheduleChange::TYPE_SCHEDULE,
            'status' => CarePlanScheduleChange::STATUS_PENDING,
            'effective_on' => $effectiveOn->toDateString(),
            'current_schedule' => $this->scheduleSnapshot($plan),
            'proposed_schedule' => [
                'days' => $schedule['days'],
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time'],
                'starts_on' => $schedule['starts_on'],
                'ends_on' => $schedule['ends_on'],
                'timezone' => $plan->timezone,
            ],
            'note' => trim((string) ($payload['note'] ?? '')) ?: null,
        ]);

        $plan->loadMissing('caregiver');
        if ($plan->caregiver) {
            $this->notifications->notify(
                recipients: $plan->caregiver,
                eventKey: MarketplaceEvent::REGULAR_CARE_SCHEDULE_CHANGE_REQUESTED,
                title: 'Regular care schedule change',
                body: $family->name.' asked to change future regular visits.',
                url: route('caregiver.regular-clients.index'),
                payload: ['care_plan_id' => $plan->id, 'schedule_change_id' => $change->id],
                subject: $change,
                dedupeKey: 'regular-care-schedule-change:'.$change->id.'-user-'.$plan->caregiver_user_id
            );
        }

        return $change;
    }

    public function requestExtraVisit(
        CarePlan $plan,
        User $family,
        Carbon $start,
        Carbon $end,
        ?string $note = null
    ): CarePlanScheduleChange {
        $this->assertFamilyOwnsPlan($plan, $family);
        if ($start->lte(now()) || $end->lte($start)) {
            throw ValidationException::withMessages(['extraVisitDate' => 'Choose a future visit with a valid duration.']);
        }

        $change = CarePlanScheduleChange::query()->create([
            'care_plan_id' => $plan->id,
            'requested_by_user_id' => $family->id,
            'type' => CarePlanScheduleChange::TYPE_EXTRA_VISIT,
            'status' => CarePlanScheduleChange::STATUS_PENDING,
            'effective_on' => $start->toDateString(),
            'current_schedule' => $this->scheduleSnapshot($plan),
            'proposed_schedule' => [
                'start_at' => $start->toIso8601String(),
                'end_at' => $end->toIso8601String(),
                'timezone' => $plan->timezone,
            ],
            'note' => trim((string) $note) ?: null,
        ]);

        $plan->loadMissing('caregiver');
        if ($plan->caregiver) {
            $this->notifications->notify(
                recipients: $plan->caregiver,
                eventKey: MarketplaceEvent::REGULAR_CARE_EXTRA_VISIT_REQUESTED,
                title: 'Extra visit requested',
                body: $family->name.' asked if you can add an extra visit.',
                url: route('caregiver.regular-clients.index'),
                payload: ['care_plan_id' => $plan->id, 'schedule_change_id' => $change->id],
                subject: $change,
                dedupeKey: 'regular-care-extra-visit:'.$change->id.'-user-'.$plan->caregiver_user_id
            );
        }

        return $change;
    }

    public function respondToScheduleChange(
        CarePlanScheduleChange $change,
        User $caregiver,
        bool $accept
    ): CarePlanScheduleChange {
        [$processedChange, $processed, $plan] = DB::transaction(function () use ($change, $caregiver, $accept): array {
            $lockedChange = CarePlanScheduleChange::query()->lockForUpdate()->findOrFail($change->id);
            $plan = CarePlan::query()->lockForUpdate()->find($lockedChange->care_plan_id);
            if (! $plan || (int) $plan->caregiver_user_id !== (int) $caregiver->id) {
                abort(403);
            }
            if ($lockedChange->status !== CarePlanScheduleChange::STATUS_PENDING) {
                return [$lockedChange, false, $plan];
            }

            if ($accept) {
                if ($lockedChange->type === CarePlanScheduleChange::TYPE_EXTRA_VISIT) {
                    $start = Carbon::parse((string) data_get($lockedChange->proposed_schedule, 'start_at'));
                    $end = Carbon::parse((string) data_get($lockedChange->proposed_schedule, 'end_at'));
                    $booking = $this->occurrences->createExtraVisit($plan, $start, $end);
                    $this->paymentWindow->prepareBookings(collect([$booking->load(['carePlan.family', 'family', 'caregiver.caregiverProfile', 'application', 'payment'])]));
                } else {
                    $timezone = $plan->timezone ?: (string) config('app.timezone', 'America/New_York');
                    $effectiveOn = $lockedChange->effective_on
                        ? Carbon::parse($lockedChange->effective_on->toDateString(), $timezone)->startOfDay()
                        : now($timezone)->addDay()->startOfDay();
                    $this->cancelFutureBookings(
                        $plan,
                        $effectiveOn->copy()->setTimezone((string) config('app.timezone', $timezone)),
                        'Schedule changed with caregiver approval.'
                    );
                    $proposal = $lockedChange->proposed_schedule ?? [];
                    $plan->forceFill([
                        'schedule_days' => data_get($proposal, 'days', $plan->schedule_days),
                        'schedule_start_time' => data_get($proposal, 'start_time', $plan->schedule_start_time),
                        'schedule_end_time' => data_get($proposal, 'end_time', $plan->schedule_end_time),
                        'starts_on' => $effectiveOn->toDateString(),
                        'ends_on' => data_get($proposal, 'ends_on', $plan->ends_on?->toDateString()),
                        'schedule_version' => (int) $plan->schedule_version + 1,
                        'last_error' => null,
                    ])->save();
                    $this->occurrences->materialize($plan->fresh());
                    $this->paymentWindow->preparePlan($plan->fresh());
                }
            }

            $lockedChange->forceFill([
                'status' => $accept
                    ? CarePlanScheduleChange::STATUS_ACCEPTED
                    : CarePlanScheduleChange::STATUS_DECLINED,
                'responded_by_user_id' => $caregiver->id,
                'responded_at' => now(),
            ])->save();

            return [$lockedChange, true, $plan];
        });

        if ($processed) {
            $this->health->reconcile($plan->fresh());
            $this->notifyScheduleChangeResponse($processedChange, $accept);
        }

        return $processedChange->fresh();
    }

    public function skipVisit(CarePlan $plan, CareBooking $booking, User $family): CareBooking
    {
        $this->assertFamilyOwnsPlan($plan, $family);
        if ((int) $booking->care_plan_id !== (int) $plan->id || $booking->status !== CareBooking::STATUS_SCHEDULED) {
            throw ValidationException::withMessages(['visit' => 'Only an upcoming visit can be skipped.']);
        }

        $lateCancel = $this->trust->markLateCancelFlag($booking);
        $this->payments->cancelForBooking($booking);
        $booking->forceFill([
            'status' => CareBooking::STATUS_CANCELLED,
            'late_cancel_flag' => $lateCancel,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $family->id,
            'cancellation_reason' => 'Skipped by family. Regular care continues.',
        ])->save();
        $this->trust->recordEvent($booking, $family->id, 'family', 'regular_care_visit_skipped', [
            'late_cancel' => $lateCancel,
        ]);
        $this->trust->recomputeReliabilityForBooking($booking);
        $this->health->reconcile($plan);

        $plan->loadMissing('caregiver');
        if ($plan->caregiver) {
            $this->notifications->notify(
                recipients: $plan->caregiver,
                eventKey: MarketplaceEvent::REGULAR_CARE_VISIT_SKIPPED,
                title: 'Regular visit skipped',
                body: $family->name.' skipped the visit on '.$booking->scheduled_start_at->format('l, F j').'. Regular care continues.',
                url: route('caregiver.shifts.index'),
                payload: ['care_plan_id' => $plan->id, 'care_booking_id' => $booking->id],
                subject: $booking,
                dedupeKey: 'regular-care-visit-skipped:booking-'.$booking->id.'-user-'.$plan->caregiver_user_id
            );
        }

        return $booking->fresh('payment');
    }

    public function pausePlan(CarePlan $plan, User $family, Carbon $from, ?Carbon $resumeOn = null): CarePlan
    {
        $this->assertFamilyOwnsPlan($plan, $family);
        $timezone = $plan->timezone ?: (string) config('app.timezone', 'America/New_York');
        $from = Carbon::parse($from->toDateString(), $timezone)->startOfDay();
        $resumeOn = $resumeOn ? Carbon::parse($resumeOn->toDateString(), $timezone)->startOfDay() : null;
        if ($resumeOn && $resumeOn->lte($from)) {
            throw ValidationException::withMessages(['resumeOn' => 'The return date must be after the pause starts.']);
        }

        [$plan, $changed] = DB::transaction(function () use ($plan, $family, $from, $resumeOn, $timezone): array {
            $lockedPlan = CarePlan::query()->lockForUpdate()->findOrFail($plan->id);
            if ($lockedPlan->pause_starts_on?->toDateString() === $from->toDateString()
                && $lockedPlan->resumes_on?->toDateString() === $resumeOn?->toDateString()) {
                return [$lockedPlan, false];
            }

            $startsNow = $from->lte(now($timezone)->endOfDay());
            $previousStatus = $lockedPlan->status;
            $lockedPlan->forceFill([
                'pause_starts_on' => $from->toDateString(),
                'resumes_on' => $resumeOn?->toDateString(),
                'status' => $startsNow ? CarePlan::STATUS_PAUSED : CarePlan::STATUS_ACTIVE,
                'paused_at' => $startsNow ? now() : null,
            ])->save();

            $this->cancelFutureBookings(
                $lockedPlan,
                $from->copy()->setTimezone((string) config('app.timezone', $timezone)),
                'Regular care paused by family.',
                $resumeOn?->copy()->setTimezone((string) config('app.timezone', $timezone))
            );
            CarePlanEvent::query()->create([
                'care_plan_id' => $lockedPlan->id,
                'actor_user_id' => $family->id,
                'event_type' => 'family_pause_scheduled',
                'reason' => 'Family paused regular care.',
                'payload' => [
                    'previous_status' => $previousStatus,
                    'pause_starts_on' => $from->toDateString(),
                    'resumes_on' => $resumeOn?->toDateString(),
                ],
            ]);

            return [$lockedPlan, true];
        });
        if ($changed) {
            $body = $from->isFuture()
                ? $family->name.' scheduled a regular-care pause starting '.$from->format('F j').'.'
                : $family->name.' paused regular care.';
            $this->notifyPlanState($plan, MarketplaceEvent::REGULAR_CARE_PAUSED, 'Regular care paused', $body);
        }

        return $this->health->reconcile($plan->fresh());
    }

    public function resumePlan(CarePlan $plan, User $family): CarePlan
    {
        $this->assertFamilyOwnsPlan($plan, $family);
        [$plan, $changed] = DB::transaction(function () use ($plan, $family): array {
            $lockedPlan = CarePlan::query()->lockForUpdate()->findOrFail($plan->id);
            if (! $lockedPlan->pause_starts_on && $lockedPlan->status === CarePlan::STATUS_ACTIVE) {
                return [$lockedPlan, false];
            }
            if (! $lockedPlan->isLive()) {
                throw ValidationException::withMessages(['plan' => 'This regular-care plan cannot be resumed.']);
            }

            $previousStatus = $lockedPlan->status;
            $lockedPlan->generatedBookings()
                ->where('status', CareBooking::STATUS_CANCELLED)
                ->where('scheduled_start_at', '>', now())
                ->where('cancellation_reason', 'Regular care paused by family.')
                ->get()
                ->each(fn (CareBooking $booking) => $booking->forceFill([
                    'status' => CareBooking::STATUS_SCHEDULED,
                    'cancelled_at' => null,
                    'cancelled_by_user_id' => null,
                    'cancellation_reason' => null,
                ])->save());
            $lockedPlan->forceFill([
                'status' => CarePlan::STATUS_ACTIVE,
                'pause_starts_on' => null,
                'resumes_on' => null,
                'paused_at' => null,
            ])->save();
            CarePlanEvent::query()->create([
                'care_plan_id' => $lockedPlan->id,
                'actor_user_id' => $family->id,
                'event_type' => 'family_resumed',
                'reason' => 'Family resumed regular care.',
                'payload' => ['previous_status' => $previousStatus],
            ]);

            return [$lockedPlan, true];
        });
        if (! $changed) {
            return $this->health->reconcile($plan->fresh());
        }
        $this->occurrences->materialize($plan->fresh());
        $this->paymentWindow->preparePlan($plan->fresh());
        $this->notifyPlanState($plan, MarketplaceEvent::REGULAR_CARE_RESUMED, 'Regular care resumed', $family->name.' resumed regular care.');

        return $this->health->reconcile($plan->fresh());
    }

    public function endPlan(CarePlan $plan, User $family, bool $cancelNextVisit = false): CarePlan
    {
        if ((int) $plan->family_user_id !== (int) $family->id) {
            abort(403);
        }

        [$plan, $changed, $keptNext] = DB::transaction(function () use ($plan, $family, $cancelNextVisit): array {
            $lockedPlan = CarePlan::query()->lockForUpdate()->findOrFail($plan->id);
            if (! $lockedPlan->isLive()) {
                return [$lockedPlan, false, null];
            }

            $previousStatus = $lockedPlan->status;
            $lockedPlan->forceFill([
                'status' => CarePlan::STATUS_ENDED,
                'ended_at' => now(),
            ])->save();

            $future = $lockedPlan->generatedBookings()
                ->where('status', CareBooking::STATUS_SCHEDULED)
                ->where('scheduled_start_at', '>', now())
                ->orderBy('scheduled_start_at')
                ->get();
            $keptNext = $cancelNextVisit ? null : $future->shift();
            foreach ($future as $booking) {
                $this->cancelPlanBooking($booking, $family->id, 'Regular care ended by family.');
            }
            CarePlanEvent::query()->create([
                'care_plan_id' => $lockedPlan->id,
                'actor_user_id' => $family->id,
                'event_type' => 'family_ended',
                'reason' => 'Family ended regular care.',
                'payload' => [
                    'previous_status' => $previousStatus,
                    'kept_next_booking_id' => $keptNext?->id,
                    'cancelled_next_visit' => $cancelNextVisit,
                ],
            ]);

            return [$lockedPlan, true, $keptNext];
        });
        if (! $changed) {
            return $plan->fresh();
        }

        $plan->loadMissing('caregiver');
        if ($plan->caregiver) {
            $this->notifications->notify(
                recipients: $plan->caregiver,
                eventKey: MarketplaceEvent::REGULAR_CARE_ENDED,
                title: 'Regular care plan ended',
                body: $family->name.' ended regular care.'.($keptNext ? ' The next confirmed visit will still happen.' : ''),
                url: route('caregiver.regular-clients.index'),
                payload: ['care_plan_id' => $plan->id],
                subject: $plan,
                dedupeKey: 'regular-care-ended:plan-'.$plan->id.'-user-'.$plan->caregiver_user_id
            );
        }

        return $this->health->reconcile($plan->fresh())->load(['family', 'caregiver', 'nextBooking']);
    }

    /**
     * @return list<array{start:Carbon,end:Carbon,label:string}>
     */
    public function upcomingVisits(CarePlan $plan, int $limit = 4, bool $useCounter = false): array
    {
        if (! $useCounter && $plan->isLive()) {
            return $plan->generatedBookings()
                ->whereIn('status', [
                    CareBooking::STATUS_SCHEDULED,
                    CareBooking::STATUS_IN_PROGRESS,
                    CareBooking::STATUS_PAUSED,
                ])
                ->where('scheduled_start_at', '>=', now()->subHours(2))
                ->with('payment')
                ->orderBy('scheduled_start_at')
                ->limit($limit)
                ->get()
                ->map(fn (CareBooking $booking) => [
                    'start' => $booking->scheduled_start_at,
                    'end' => $booking->scheduled_end_at,
                    'label' => $booking->scheduled_start_at->format('l, F j').' from '.$booking->scheduled_start_at->format('g:i A').' to '.$booking->scheduled_end_at->format('g:i A'),
                    'booking' => $booking,
                    'confirmed' => true,
                ])
                ->all();
        }

        $schedule = $this->scheduleFromPlan($plan, $useCounter);
        $visits = [];
        $cursor = Carbon::parse($schedule['starts_on'])->startOfDay();
        if ($cursor->lt(now()->startOfDay())) {
            $cursor = now()->startOfDay();
        }

        for ($offset = 0; $offset <= 90 && count($visits) < $limit; $offset++) {
            $date = $cursor->copy()->addDays($offset);
            if (! empty($schedule['ends_on']) && $date->gt(Carbon::parse($schedule['ends_on'])->endOfDay())) {
                break;
            }
            if (! in_array((int) $date->dayOfWeek, $schedule['days'], true)) {
                continue;
            }

            $start = $this->combineDateAndTime($date, $schedule['start_time']);
            $end = $this->combineDateAndTime($date, $schedule['end_time']);
            if ($start->lte(now())) {
                continue;
            }

            $visits[] = [
                'start' => $start,
                'end' => $end,
                'label' => $start->format('D, M j').' from '.$start->format('g:i A').' to '.$end->format('g:i A'),
                'booking' => null,
                'confirmed' => false,
            ];
        }

        return $visits;
    }

    public function scheduleLabel(CarePlan $plan, bool $useCounter = false): string
    {
        $schedule = $this->scheduleFromPlan($plan, $useCounter);
        $days = collect($schedule['days'])
            ->map(fn (int $day) => self::DAY_LABELS[$day] ?? null)
            ->filter()
            ->implode(', ');

        return $days.' '.$this->formatTime($schedule['start_time']).' - '.$this->formatTime($schedule['end_time']);
    }

    /**
     * @return array{days:list<int>,start_time:string,end_time:string,starts_on:string,ends_on:string|null}
     */
    private function scheduleFromPlan(CarePlan $plan, bool $useCounter = false): array
    {
        return [
            'days' => $useCounter && $plan->counter_schedule_days ? $this->normalizeDays($plan->counter_schedule_days) : $this->normalizeDays($plan->schedule_days),
            'start_time' => $useCounter && $plan->counter_schedule_start_time ? $plan->counter_schedule_start_time : $plan->schedule_start_time,
            'end_time' => $useCounter && $plan->counter_schedule_end_time ? $plan->counter_schedule_end_time : $plan->schedule_end_time,
            'starts_on' => (string) (($useCounter && $plan->counter_starts_on) ? $plan->counter_starts_on->toDateString() : $plan->starts_on->toDateString()),
            'ends_on' => $plan->ends_on?->toDateString(),
        ];
    }

    private function assertFamilyOwnsPlan(CarePlan $plan, User $family): void
    {
        if ((int) $plan->family_user_id !== (int) $family->id) {
            abort(403);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function scheduleSnapshot(CarePlan $plan): array
    {
        return [
            'days' => $this->normalizeDays($plan->schedule_days),
            'start_time' => (string) $plan->schedule_start_time,
            'end_time' => (string) $plan->schedule_end_time,
            'starts_on' => $plan->starts_on?->toDateString(),
            'ends_on' => $plan->ends_on?->toDateString(),
            'timezone' => $plan->timezone,
            'schedule_version' => $plan->schedule_version,
        ];
    }

    private function alignStartDateToSchedule(string $startsOn, array $days): string
    {
        $date = Carbon::parse($startsOn)->startOfDay();
        for ($offset = 0; $offset < 7; $offset++) {
            if (in_array((int) $date->dayOfWeek, $days, true)) {
                return $date->toDateString();
            }
            $date->addDay();
        }

        return $startsOn;
    }

    private function cancelFutureBookings(
        CarePlan $plan,
        Carbon $from,
        string $reason,
        ?Carbon $until = null
    ): void {
        $bookings = $plan->generatedBookings()
            ->where('status', CareBooking::STATUS_SCHEDULED)
            ->where('scheduled_start_at', '>=', $from)
            ->when($until, fn ($query) => $query->where('scheduled_start_at', '<', $until))
            ->get();

        foreach ($bookings as $booking) {
            $this->cancelPlanBooking($booking, $plan->family_user_id, $reason);
        }
    }

    private function cancelPlanBooking(CareBooking $booking, int $actorUserId, string $reason): void
    {
        $this->payments->cancelForBooking($booking);
        $booking->forceFill([
            'status' => CareBooking::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $actorUserId,
            'cancellation_reason' => $reason,
        ])->save();
        $this->trust->recordEvent($booking, $actorUserId, 'family', 'regular_care_visit_cancelled', [
            'reason' => $reason,
        ]);
    }

    private function notifyScheduleChangeResponse(CarePlanScheduleChange $change, bool $accepted): void
    {
        $change->loadMissing('plan.family');
        $family = $change->plan?->family;
        if (! $family) {
            return;
        }

        $this->notifications->notify(
            recipients: $family,
            eventKey: $accepted
                ? MarketplaceEvent::REGULAR_CARE_SCHEDULE_CHANGE_ACCEPTED
                : MarketplaceEvent::REGULAR_CARE_SCHEDULE_CHANGE_DECLINED,
            title: $accepted ? 'Regular care update accepted' : 'Regular care update declined',
            body: $accepted
                ? 'Your caregiver accepted the requested regular-care update.'
                : 'Your caregiver could not accept the requested regular-care update.',
            url: route('family.care.show', $change->care_plan_id),
            payload: ['care_plan_id' => $change->care_plan_id, 'schedule_change_id' => $change->id],
            subject: $change,
            dedupeKey: 'regular-care-change-response:'.$change->id.'-user-'.$family->id
        );
    }

    private function notifyPlanState(CarePlan $plan, string $eventKey, string $title, string $body): void
    {
        $plan->loadMissing('caregiver');
        if (! $plan->caregiver) {
            return;
        }

        $this->notifications->notify(
            recipients: $plan->caregiver,
            eventKey: $eventKey,
            title: $title,
            body: $body,
            url: route('caregiver.regular-clients.index'),
            payload: ['care_plan_id' => $plan->id],
            subject: $plan,
            dedupeKey: $eventKey.':plan-'.$plan->id.'-at-'.now()->format('YmdHi')
        );
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array{days:list<int>,start_time:string,end_time:string,starts_on:string,ends_on:string|null}
     */
    private function normalizeSchedulePayload(array $payload): array
    {
        $days = $this->normalizeDays($payload['schedule_days'] ?? []);
        if ($days === []) {
            throw ValidationException::withMessages([
                'schedule_days' => 'Choose at least one care day.',
            ]);
        }

        $startTime = $this->normalizeTimeString($payload['schedule_start_time'] ?? null);
        $endTime = $this->normalizeTimeString($payload['schedule_end_time'] ?? null);
        if ($this->timeToMinutes($endTime) <= $this->timeToMinutes($startTime)) {
            throw ValidationException::withMessages([
                'schedule_end_time' => 'End time must be after start time.',
            ]);
        }

        $startsOn = Carbon::parse((string) ($payload['starts_on'] ?? now()->addDay()->toDateString()))->toDateString();
        if (Carbon::parse($startsOn)->lt(now()->startOfDay())) {
            throw ValidationException::withMessages([
                'starts_on' => 'Start date must be today or later.',
            ]);
        }

        $endsOn = filled($payload['ends_on'] ?? null)
            ? Carbon::parse((string) $payload['ends_on'])->toDateString()
            : null;
        if ($endsOn && Carbon::parse($endsOn)->lt(Carbon::parse($startsOn))) {
            throw ValidationException::withMessages([
                'ends_on' => 'End date must be on or after the start date.',
            ]);
        }

        return [
            'days' => $days,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
        ];
    }

    /**
     * @return list<int>
     */
    private function normalizeDays(mixed $days): array
    {
        return collect($days)
            ->map(fn ($day) => (int) $day)
            ->filter(fn (int $day) => $day >= 0 && $day <= 6)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function recipientSnapshot(CareRequest $source): array
    {
        return [
            'recipient_is_requester' => (bool) ($source->recipient?->recipient_is_requester ?? false),
            'full_name' => $source->recipient?->full_name,
            'date_of_birth' => $source->recipient?->date_of_birth,
            'gender' => $source->recipient?->gender,
            'mobility_level' => $source->recipient?->mobility_level,
            'relationship_to_family' => $source->recipient?->relationship_to_family,
            'care_notes' => $source->recipient?->care_notes,
        ];
    }

    private function addressSnapshot(CareRequest $source): array
    {
        return [
            'address_line1' => $source->address_line1,
            'address_line2' => $source->address_line2,
            'city' => $source->city,
            'state' => $source->state,
            'zip' => $source->zip,
            'lat' => $source->lat,
            'lng' => $source->lng,
        ];
    }

    private function taskSnapshot(CareRequest $source): array
    {
        return $source->tasks
            ->map(fn ($task) => [
                'id' => $task->id,
                'name' => $task->name,
                'task_note' => $task->pivot?->task_note,
            ])
            ->values()
            ->all();
    }

    private function normalizeTimeString(mixed $time): string
    {
        if ($time instanceof Carbon) {
            return $time->format('H:i:s');
        }

        $value = trim((string) $time);
        if ($value === '') {
            throw ValidationException::withMessages([
                'schedule_start_time' => 'Choose a start and end time.',
            ]);
        }

        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            return $value.':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return $value;
        }

        return Carbon::parse($value)->format('H:i:s');
    }

    private function combineDateAndTime(Carbon $date, string $time): Carbon
    {
        return $date->copy()->setTimeFromTimeString($this->normalizeTimeString($time));
    }

    /**
     * @param  array{days:list<int>,start_time:string,end_time:string,starts_on:string,ends_on:string|null}  $schedule
     */
    private function firstProposedOccurrenceStart(array $schedule, Carbon $effectiveOn, string $timezone): ?Carbon
    {
        $days = array_map('intval', $schedule['days']);
        for ($offset = 0; $offset <= 7; $offset++) {
            $date = $effectiveOn->copy()->addDays($offset);
            if (! in_array((int) $date->dayOfWeek, $days, true)) {
                continue;
            }

            return Carbon::parse(
                $date->toDateString().' '.substr($schedule['start_time'], 0, 8),
                $timezone
            )->setTimezone((string) config('app.timezone', $timezone));
        }

        return null;
    }

    private function timeToMinutes(string $time): int
    {
        $normalized = $this->normalizeTimeString($time);

        return ((int) substr($normalized, 0, 2) * 60) + (int) substr($normalized, 3, 2);
    }

    private function formatTime(string $time): string
    {
        return Carbon::parse($time)->format('g:i A');
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $message = strtolower($exception->getMessage());

        return in_array($sqlState, ['23000', '23505'], true)
            || str_contains($message, 'duplicate')
            || str_contains($message, 'unique constraint');
    }

    private function requiresNewAgreementSource(CarePlan $plan): bool
    {
        return in_array($plan->status, [
            CarePlan::STATUS_DECLINED,
            CarePlan::STATUS_EXPIRED,
            CarePlan::STATUS_ENDED,
            CarePlan::STATUS_CANCELLED,
        ], true);
    }

    private function existingAgreementMessage(CarePlan $plan): string
    {
        if ($this->requiresNewAgreementSource($plan)) {
            return 'The previous regular-care agreement is '.str_replace('_', ' ', $plan->status).'. Create a new care request before starting a new agreement.';
        }

        return 'A regular-care agreement already exists for this request (plan #'.$plan->id.'). Open that plan instead of creating another.';
    }
}
