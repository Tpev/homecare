<?php

namespace App\Services\RegularCare;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareBooking;
use App\Models\CarePlan;
use App\Models\CareRelationship;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
    ) {
    }

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

        if ($source->care_plan_id) {
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

        return DB::transaction(function () use ($source, $family, $caregiver, $application, $payload, $schedule, $hourlyRate): CarePlan {
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
                'hourly_rate' => $hourlyRate,
                'family_message' => trim((string) ($payload['family_message'] ?? '')) ?: null,
                'offered_at' => now(),
                'expires_at' => now()->addHours(72),
                'payment_status' => CarePlan::PAYMENT_UNCHECKED,
            ]);

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

            return $plan;
        });
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

        [$activatedPlan, $booking] = DB::transaction(function () use ($plan, $caregiver): array {
            $plan->forceFill([
                'status' => CarePlan::STATUS_ACTIVE,
                'responded_at' => now(),
                'accepted_at' => now(),
                'activated_at' => now(),
                'payment_status' => CarePlan::PAYMENT_UNCHECKED,
                'last_error' => null,
            ])->save();

            $booking = $this->generateNextBooking($plan->fresh(), $caregiver->id, 'caregiver');

            return [$plan->fresh(['family', 'caregiver', 'nextBooking']), $booking];
        });

        $this->authorizeGeneratedBooking($activatedPlan, $booking);

        $this->notifications->notify(
            recipients: $activatedPlan->family,
            eventKey: MarketplaceEvent::REGULAR_CARE_ACCEPTED,
            title: 'Regular care accepted',
            body: $caregiver->name.' accepted your regular care schedule.',
            url: route('family.care.show', $activatedPlan->id),
            payload: ['care_plan_id' => $activatedPlan->id, 'care_booking_id' => $booking->id],
            subject: $activatedPlan,
            dedupeKey: 'regular-care-accepted:plan-'.$activatedPlan->id.'-user-'.$activatedPlan->family_user_id
        );

        return $activatedPlan->fresh(['nextBooking.payment', 'family', 'caregiver']);
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

        [$activatedPlan, $booking] = DB::transaction(function () use ($plan, $family): array {
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

            $booking = $this->generateNextBooking($plan->fresh(), $family->id, 'family');

            return [$plan->fresh(['family', 'caregiver', 'nextBooking']), $booking];
        });

        $this->authorizeGeneratedBooking($activatedPlan, $booking);

        $this->notifications->notify(
            recipients: $activatedPlan->caregiver,
            eventKey: MarketplaceEvent::REGULAR_CARE_ACCEPTED,
            title: 'Counteroffer accepted',
            body: $family->name.' accepted your regular care schedule.',
            url: route('caregiver.regular-clients.index'),
            payload: ['care_plan_id' => $activatedPlan->id, 'care_booking_id' => $booking->id],
            subject: $activatedPlan,
            dedupeKey: 'regular-care-counter-accepted:plan-'.$activatedPlan->id.'-user-'.$activatedPlan->caregiver_user_id
        );

        return $activatedPlan->fresh(['nextBooking.payment', 'family', 'caregiver']);
    }

    public function endPlan(CarePlan $plan, User $family): CarePlan
    {
        if ((int) $plan->family_user_id !== (int) $family->id) {
            abort(403);
        }

        if (! $plan->isLive()) {
            return $plan->fresh();
        }

        $plan->forceFill([
            'status' => CarePlan::STATUS_ENDED,
            'ended_at' => now(),
        ])->save();

        $plan->loadMissing('caregiver');
        if ($plan->caregiver) {
            $this->notifications->notify(
                recipients: $plan->caregiver,
                eventKey: MarketplaceEvent::REGULAR_CARE_ENDED,
                title: 'Regular care plan ended',
                body: $family->name.' ended a regular care plan.',
                url: route('caregiver.regular-clients.index'),
                payload: ['care_plan_id' => $plan->id],
                subject: $plan,
                dedupeKey: 'regular-care-ended:plan-'.$plan->id.'-user-'.$plan->caregiver_user_id
            );
        }

        return $plan->fresh(['family', 'caregiver', 'nextBooking']);
    }

    /**
     * @return list<array{start:Carbon,end:Carbon,label:string}>
     */
    public function upcomingVisits(CarePlan $plan, int $limit = 4, bool $useCounter = false): array
    {
        $schedule = $this->scheduleFromPlan($plan, $useCounter);
        $visits = [];
        $cursor = Carbon::parse($schedule['starts_on'])->startOfDay();
        if ($cursor->lt(now()->startOfDay())) {
            $cursor = now()->startOfDay();
        }

        for ($offset = 0; $offset <= 90 && count($visits) < $limit; $offset++) {
            $date = $cursor->copy()->addDays($offset);
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
     * @return array{days:list<int>,start_time:string,end_time:string,starts_on:string}
     */
    private function scheduleFromPlan(CarePlan $plan, bool $useCounter = false): array
    {
        return [
            'days' => $useCounter && $plan->counter_schedule_days ? $this->normalizeDays($plan->counter_schedule_days) : $this->normalizeDays($plan->schedule_days),
            'start_time' => $useCounter && $plan->counter_schedule_start_time ? $plan->counter_schedule_start_time : $plan->schedule_start_time,
            'end_time' => $useCounter && $plan->counter_schedule_end_time ? $plan->counter_schedule_end_time : $plan->schedule_end_time,
            'starts_on' => (string) (($useCounter && $plan->counter_starts_on) ? $plan->counter_starts_on->toDateString() : $plan->starts_on->toDateString()),
        ];
    }

    private function generateNextBooking(CarePlan $plan, int $actorUserId, string $actorRole): CareBooking
    {
        $plan->loadMissing(['family', 'caregiver']);
        $occurrence = $this->nextOccurrence($plan);
        $address = $plan->address_snapshot ?? [];
        $recipient = $plan->recipient_snapshot ?? [];
        $hourlyRate = $this->pricing->hourlyRateForFamily(
            $plan->family,
            (float) $plan->hourly_rate
        );

        $request = CareRequest::query()->create([
            'family_user_id' => $plan->family_user_id,
            'care_plan_id' => $plan->id,
            'title' => 'Regular care: '.$plan->recipientName().' with '.$plan->caregiver?->name,
            'additional_info' => $plan->care_notes,
            'scope_of_work' => $plan->care_notes,
            'time_expectations' => $this->scheduleLabel($plan),
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
        ]);

        $taskPayload = collect($plan->task_snapshot ?? [])
            ->filter(fn (array $task) => ! empty($task['id']))
            ->mapWithKeys(fn (array $task) => [
                (int) $task['id'] => ['task_note' => $task['task_note'] ?? null],
            ])
            ->all();
        $request->tasks()->sync($taskPayload);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $plan->caregiver_user_id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => $hourlyRate,
            'cover_note' => 'Accepted through regular care plan #'.$plan->id.'.',
        ]);

        CareRequestConversation::findOrCreateForApplication($application->load('careRequest'), $actorUserId);

        $booking = CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_plan_id' => $plan->id,
            'care_request_application_id' => $application->id,
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
        $this->trust->recordEvent(
            $booking,
            $actorUserId,
            $actorRole,
            'care_plan_booking_generated',
            ['care_plan_id' => $plan->id]
        );

        $plan->forceFill([
            'next_booking_id' => $booking->id,
            'last_generated_at' => now(),
        ])->save();

        $plan->relationship?->forceFill([
            'last_care_request_id' => $request->id,
            'last_care_booking_id' => $booking->id,
        ])->save();

        return $booking;
    }

    private function authorizeGeneratedBooking(CarePlan $plan, CareBooking $booking): void
    {
        try {
            $this->payments->authorizeForBooking($booking->fresh(['application', 'family', 'caregiver.caregiverProfile']));

            $plan->forceFill([
                'status' => CarePlan::STATUS_ACTIVE,
                'payment_status' => CarePlan::PAYMENT_AUTHORIZED,
                'last_error' => null,
            ])->save();
        } catch (PaymentException $exception) {
            $plan->forceFill([
                'status' => CarePlan::STATUS_PAYMENT_ATTENTION,
                'payment_status' => CarePlan::PAYMENT_ACTION_REQUIRED,
                'last_error' => $exception->userMessage,
            ])->save();

            $plan->loadMissing('family');
            if ($plan->family) {
                $this->notifications->notify(
                    recipients: $plan->family,
                    eventKey: MarketplaceEvent::REGULAR_CARE_PAYMENT_ATTENTION,
                    title: 'Regular care payment needs attention',
                    body: $exception->userMessage,
                    url: route('family.care.show', $plan->id),
                    payload: ['care_plan_id' => $plan->id, 'care_booking_id' => $booking->id],
                    subject: $plan,
                    dedupeKey: 'regular-care-payment-attention:plan-'.$plan->id.'-booking-'.$booking->id
                );
            }
        }
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

        return [
            'days' => $days,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
        ];
    }

    /**
     * @param mixed $days
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

    /**
     * @return array{start:Carbon,end:Carbon}
     */
    private function nextOccurrence(CarePlan $plan): array
    {
        $schedule = $this->scheduleFromPlan($plan);
        $cursor = Carbon::parse($schedule['starts_on'])->startOfDay();
        if ($cursor->lt(now()->startOfDay())) {
            $cursor = now()->startOfDay();
        }

        for ($offset = 0; $offset <= 90; $offset++) {
            $date = $cursor->copy()->addDays($offset);
            if (! in_array((int) $date->dayOfWeek, $schedule['days'], true)) {
                continue;
            }

            $start = $this->combineDateAndTime($date, $schedule['start_time']);
            if ($start->lte(now())) {
                continue;
            }

            return [
                'start' => $start,
                'end' => $this->combineDateAndTime($date, $schedule['end_time']),
            ];
        }

        throw ValidationException::withMessages([
            'schedule_days' => 'No upcoming visit could be generated from this schedule.',
        ]);
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

    private function timeToMinutes(string $time): int
    {
        $normalized = $this->normalizeTimeString($time);

        return ((int) substr($normalized, 0, 2) * 60) + (int) substr($normalized, 3, 2);
    }

    private function formatTime(string $time): string
    {
        return Carbon::parse($time)->format('g:i A');
    }
}
