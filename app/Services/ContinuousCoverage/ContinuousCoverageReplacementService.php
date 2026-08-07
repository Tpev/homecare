<?php

namespace App\Services\ContinuousCoverage;

use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\ContinuousCoverageReplacementCase;
use App\Models\ContinuousCoverageRosterMember;
use App\Models\ContinuousCoverageShift;
use App\Models\ContinuousCoverageShiftOffer;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Booking\BookingTrustService;
use App\Services\Payments\BookingPaymentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ContinuousCoverageReplacementService
{
    public function __construct(
        private readonly ContinuousCoverageNotificationService $notifications,
        private readonly ContinuousCoverageEventRecorder $events,
        private readonly ContinuousCoverageBookingAdapter $bookings,
        private readonly BookingPaymentService $payments,
        private readonly BookingTrustService $trust,
        private readonly ContinuousCoverageAccess $access,
    ) {}

    public function release(ContinuousCoverageShift $shift, User $caregiver, string $reason): ContinuousCoverageReplacementCase
    {
        $this->assertEnabledFor($caregiver);
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => 'Provide a brief release reason between 5 and 1,000 characters.']);
        }
        $releasedBookingId = null;
        $case = DB::transaction(function () use ($shift, $caregiver, $reason, &$releasedBookingId): ContinuousCoverageReplacementCase {
            $locked = ContinuousCoverageShift::query()->lockForUpdate()->with(['plan', 'booking.payment'])->findOrFail($shift->id);
            if ((int) $locked->assigned_caregiver_user_id !== (int) $caregiver->id
                || $locked->scheduled_start_at->lte(now())
                || ! in_array($locked->status, [ContinuousCoverageShift::STATUS_CONFIRMED, ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION], true)) {
                throw ValidationException::withMessages(['shift' => 'Only your own future confirmed coverage shift can be released.']);
            }
            if ($locked->booking && $locked->booking->status !== CareBooking::STATUS_SCHEDULED) {
                throw ValidationException::withMessages(['shift' => 'This visit has already started or closed and cannot enter replacement coverage.']);
            }
            if ($locked->booking?->payment && in_array($locked->booking->payment->status, [
                CareBookingPayment::STATUS_CAPTURED,
                CareBookingPayment::STATUS_TRANSFERRED,
                CareBookingPayment::STATUS_TRANSFER_FAILED,
                CareBookingPayment::STATUS_PARTIALLY_REFUNDED,
                CareBookingPayment::STATUS_REFUNDED,
            ], true)) {
                throw ValidationException::withMessages(['shift' => 'This visit already has a finalized charge. Contact LoLo Care support before changing its caregiver.']);
            }

            $releasedBookingId = $locked->care_booking_id;
            $metadata = (array) $locked->metadata;
            if ($releasedBookingId) {
                $metadata['released_booking_ids'] = array_values(array_unique(array_merge(
                    (array) data_get($metadata, 'released_booking_ids', []),
                    [(int) $releasedBookingId],
                )));
            }
            $metadata['assignment_version'] = max(1, (int) data_get($metadata, 'assignment_version', 1)) + 1;

            $locked->forceFill([
                'assigned_caregiver_user_id' => null,
                'care_booking_id' => null,
                'status' => ContinuousCoverageShift::STATUS_REPLACEMENT_NEEDED,
                'released_by_user_id' => $caregiver->id,
                'released_at' => now(),
                'release_reason' => $reason,
                'caregiver_accepted_at' => null,
                'family_confirmed_at' => null,
                'confirmed_at' => null,
                'metadata' => $metadata,
            ])->save();

            $case = ContinuousCoverageReplacementCase::query()->create([
                'continuous_coverage_shift_id' => $locked->id,
                'original_caregiver_user_id' => $caregiver->id,
                'winning_offer_id' => null,
                'status' => ContinuousCoverageReplacementCase::STATUS_OPEN,
                'reason' => $reason,
                'opened_at' => now(),
                'resolved_at' => null,
            ]);

            $eligible = $locked->plan->rosterMembers()
                ->with('caregiver.caregiverProfile.availabilities')
                ->where('status', ContinuousCoverageRosterMember::STATUS_ACTIVE)
                ->where('replacement_opt_in', true)
                ->where('caregiver_user_id', '!=', $caregiver->id)
                ->get()
                ->filter(fn (ContinuousCoverageRosterMember $member) => $this->matchesShift($member, $locked));

            foreach ($eligible as $member) {
                ContinuousCoverageShiftOffer::query()->firstOrCreate(
                    [
                        'replacement_case_id' => $case->id,
                        'caregiver_user_id' => $member->caregiver_user_id,
                    ],
                    [
                        'continuous_coverage_shift_id' => $locked->id,
                        'roster_member_id' => $member->id,
                        'status' => ContinuousCoverageShiftOffer::STATUS_PENDING,
                        'expires_at' => now()->addHours((int) config('marketplace.continuous_coverage.offer_expires_hours', 12)),
                    ],
                );
            }

            $this->events->record($locked->plan, 'shift_released', $caregiver, $locked, [
                'replacement_case_id' => $case->id,
                'eligible_backup_count' => $eligible->count(),
            ]);

            return $case->fresh(['shift.plan.family', 'offers.caregiver']);
        });

        if ($case->offers->isNotEmpty()) {
            $this->events->record($case->shift->plan, 'replacement_offers_created', shift: $case->shift, payload: [
                'replacement_case_id' => $case->id,
                'offer_ids' => $case->offers->pluck('id')->map(fn ($id) => (int) $id)->all(),
            ]);
        }

        if ($releasedBookingId) {
            $booking = CareBooking::query()->with('payment')->find($releasedBookingId);
            if ($booking && ! in_array($booking->status, [CareBooking::STATUS_CANCELLED, CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED], true)) {
                $lateCancel = $this->trust->markLateCancelFlag($booking);
                $cancellationException = null;
                try {
                    $this->payments->cancelForBooking($booking);
                } catch (Throwable $exception) {
                    $cancellationException = $exception;
                    report($exception);
                }
                $booking->refresh()->load('payment');
                $paymentCancellationNeedsAttention = $cancellationException !== null
                    || ($booking->payment
                        && $booking->payment->stripe_payment_intent_id
                        && in_array($booking->payment->status, [
                            CareBookingPayment::STATUS_AUTHORIZED,
                            CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED,
                            CareBookingPayment::STATUS_REAUTH_REQUIRED,
                            CareBookingPayment::STATUS_FAILED,
                        ], true));
                $booking->forceFill([
                    'status' => CareBooking::STATUS_CANCELLED,
                    'late_cancel_flag' => $lateCancel,
                    'cancelled_at' => now(),
                    'cancelled_by_user_id' => $caregiver->id,
                    'cancellation_reason' => 'Released from a Continuous Coverage shift: '.$reason,
                ])->save();
                $this->trust->recordEvent($booking, $caregiver->id, 'caregiver', 'continuous_coverage_shift_released', [
                    'continuous_coverage_shift_id' => $case->continuous_coverage_shift_id,
                    'replacement_case_id' => $case->id,
                    'late_cancel' => $lateCancel,
                    'payment_cancellation_attention' => $paymentCancellationNeedsAttention,
                ]);
                $this->trust->recomputeReliabilityForBooking($booking);

                if ($paymentCancellationNeedsAttention) {
                    $releasedShift = $case->shift()->firstOrFail();
                    $releasedShift->forceFill([
                        'metadata' => array_merge((array) $releasedShift->metadata, [
                            'released_booking_payment_attention' => [
                                'care_booking_id' => $booking->id,
                                'payment_status' => $booking->payment?->status,
                                'recorded_at' => now()->toIso8601String(),
                            ],
                        ]),
                    ])->save();
                    $this->events->record($releasedShift->plan, 'released_booking_payment_attention', $caregiver, $releasedShift, [
                        'care_booking_id' => $booking->id,
                        'payment_status' => $booking->payment?->status,
                        'exception' => $cancellationException ? class_basename($cancellationException) : null,
                    ]);
                    Log::warning('Continuous Coverage released booking payment cancellation needs review.', [
                        'continuous_coverage_plan_id' => $releasedShift->continuous_coverage_plan_id,
                        'continuous_coverage_shift_id' => $releasedShift->id,
                        'care_booking_id' => $booking->id,
                        'payment_status' => $booking->payment?->status,
                    ]);
                }
            }
        }

        $this->notifications->shiftReleased($case->shift, $caregiver);
        if ($case->offers->isEmpty()) {
            $case->forceFill(['status' => ContinuousCoverageReplacementCase::STATUS_UNRESOLVED])->save();
            $this->notifications->gapUnresolved($case->shift);
        } else {
            foreach ($case->offers as $offer) {
                $this->notifications->replacementOffer($offer);
            }
        }

        return $case->fresh(['offers']);
    }

    public function respond(ContinuousCoverageShiftOffer $offer, User $caregiver, bool $accept): ContinuousCoverageShiftOffer
    {
        $this->assertEnabledFor($caregiver);
        $result = DB::transaction(function () use ($offer, $caregiver, $accept): ContinuousCoverageShiftOffer {
            $lockedOffer = ContinuousCoverageShiftOffer::query()->lockForUpdate()->findOrFail($offer->id);
            $case = ContinuousCoverageReplacementCase::query()->lockForUpdate()->findOrFail($lockedOffer->replacement_case_id);
            $shift = ContinuousCoverageShift::query()->lockForUpdate()->with('plan')->findOrFail($lockedOffer->continuous_coverage_shift_id);

            if ((int) $lockedOffer->caregiver_user_id !== (int) $caregiver->id
                || $lockedOffer->status !== ContinuousCoverageShiftOffer::STATUS_PENDING
                || ($lockedOffer->expires_at && $lockedOffer->expires_at->isPast())
                || $case->status !== ContinuousCoverageReplacementCase::STATUS_OPEN
                || $shift->status !== ContinuousCoverageShift::STATUS_REPLACEMENT_NEEDED) {
                throw ValidationException::withMessages(['offer' => 'This backup offer is no longer available.']);
            }

            $member = $lockedOffer->rosterMember()->lockForUpdate()->firstOrFail();
            if (! $member->isActive() || ! $member->replacement_opt_in) {
                throw ValidationException::withMessages(['offer' => 'Your approved backup membership is not active.']);
            }
            if ($accept && ! $this->matchesShift($member, $shift)) {
                throw ValidationException::withMessages(['offer' => 'This shift no longer matches your approved coverage eligibility or current availability.']);
            }

            if (! $accept) {
                $lockedOffer->forceFill(['status' => ContinuousCoverageShiftOffer::STATUS_DECLINED, 'responded_at' => now()])->save();
                $this->events->record($shift->plan, 'replacement_offer_declined', $caregiver, $shift, [
                    'offer_id' => $lockedOffer->id,
                ]);

                return $lockedOffer->fresh(['shift.plan.family', 'caregiver']);
            }

            $lockedOffer->forceFill(['status' => ContinuousCoverageShiftOffer::STATUS_ACCEPTED, 'responded_at' => now()])->save();
            $case->offers()->where('id', '!=', $lockedOffer->id)->where('status', ContinuousCoverageShiftOffer::STATUS_PENDING)
                ->update(['status' => ContinuousCoverageShiftOffer::STATUS_CLOSED, 'responded_at' => now(), 'updated_at' => now()]);
            $case->winning_offer_id = $lockedOffer->id;

            if ($shift->plan->replacementRequiresFamilyConfirmation()) {
                $case->status = ContinuousCoverageReplacementCase::STATUS_AWAITING_FAMILY;
                $shift->status = ContinuousCoverageShift::STATUS_AWAITING_FAMILY;
            } else {
                $this->confirmLocked($shift, $case, $lockedOffer, now());
            }
            $case->save();
            $shift->save();
            $this->events->record($shift->plan, 'replacement_offer_accepted', $caregiver, $shift, [
                'offer_id' => $lockedOffer->id,
                'requires_family_confirmation' => $shift->plan->replacementRequiresFamilyConfirmation(),
            ]);

            return $lockedOffer->fresh(['shift.plan.family', 'caregiver']);
        });

        if (! $accept) {
            return $result;
        }

        $this->notifications->replacementAccepted($result);
        if (! $result->shift->plan->replacementRequiresFamilyConfirmation()) {
            $this->linkIfNear($result->shift);
            $this->notifications->replacementConfirmed($result->shift->fresh(['plan.family', 'assignedCaregiver']));
        }

        return $result;
    }

    public function familyConfirm(ContinuousCoverageReplacementCase $case, User $family): ContinuousCoverageShift
    {
        $this->assertEnabledFor($family);
        $shift = DB::transaction(function () use ($case, $family): ContinuousCoverageShift {
            $lockedCase = ContinuousCoverageReplacementCase::query()->lockForUpdate()->findOrFail($case->id);
            $shift = ContinuousCoverageShift::query()->lockForUpdate()->with('plan')->findOrFail($lockedCase->continuous_coverage_shift_id);
            abort_unless($family->role === 'family' && app(FamilyAccountContext::class)->canAccessRecord($family, $shift->plan), 403);
            if ($lockedCase->status !== ContinuousCoverageReplacementCase::STATUS_AWAITING_FAMILY || ! $lockedCase->winning_offer_id) {
                throw ValidationException::withMessages(['replacement' => 'This replacement is no longer waiting for confirmation.']);
            }
            $offer = ContinuousCoverageShiftOffer::query()
                ->lockForUpdate()
                ->with('rosterMember.caregiver.caregiverProfile')
                ->findOrFail($lockedCase->winning_offer_id);
            if ((int) $offer->replacement_case_id !== (int) $lockedCase->id
                || (int) $offer->continuous_coverage_shift_id !== (int) $shift->id
                || $offer->status !== ContinuousCoverageShiftOffer::STATUS_ACCEPTED
                || ! $offer->responded_at
                || (int) $offer->rosterMember?->continuous_coverage_plan_id !== (int) $shift->continuous_coverage_plan_id
                || (int) $offer->rosterMember?->caregiver_user_id !== (int) $offer->caregiver_user_id
                || ! $offer->rosterMember?->isActive()
                || ! $offer->rosterMember?->replacement_opt_in
                || $offer->rosterMember?->caregiver?->caregiverProfile?->status !== 'active'
                || ! $this->matchesShift($offer->rosterMember, $shift)
                || $shift->status !== ContinuousCoverageShift::STATUS_AWAITING_FAMILY) {
                throw ValidationException::withMessages(['replacement' => 'This accepted replacement is no longer valid for confirmation.']);
            }
            $this->confirmLocked($shift, $lockedCase, $offer, now());
            $lockedCase->save();
            $shift->save();
            $this->events->record($shift->plan, 'replacement_family_confirmed', $family, $shift, ['offer_id' => $offer->id]);

            return $shift->fresh(['plan.family', 'assignedCaregiver']);
        });

        $this->linkIfNear($shift);
        $this->notifications->replacementConfirmed($shift);

        return $shift->fresh();
    }

    public function familyDecline(ContinuousCoverageReplacementCase $case, User $family): ContinuousCoverageShift
    {
        $this->assertEnabledFor($family);
        [$shift, $notSelectedOffer, $reopenedOfferIds] = DB::transaction(function () use ($case, $family): array {
            $lockedCase = ContinuousCoverageReplacementCase::query()->lockForUpdate()->findOrFail($case->id);
            $shift = ContinuousCoverageShift::query()->lockForUpdate()->with('plan')->findOrFail($lockedCase->continuous_coverage_shift_id);
            abort_unless($family->role === 'family' && app(FamilyAccountContext::class)->canAccessRecord($family, $shift->plan), 403);
            if ($lockedCase->status !== ContinuousCoverageReplacementCase::STATUS_AWAITING_FAMILY || ! $lockedCase->winning_offer_id) {
                throw ValidationException::withMessages(['replacement' => 'This replacement is no longer waiting for a family decision.']);
            }

            $winningOffer = ContinuousCoverageShiftOffer::query()->lockForUpdate()->findOrFail($lockedCase->winning_offer_id);
            if ((int) $winningOffer->replacement_case_id !== (int) $lockedCase->id
                || (int) $winningOffer->continuous_coverage_shift_id !== (int) $shift->id
                || $winningOffer->status !== ContinuousCoverageShiftOffer::STATUS_ACCEPTED) {
                throw ValidationException::withMessages(['replacement' => 'The accepted replacement offer is no longer valid.']);
            }
            $winningOffer->forceFill([
                'status' => ContinuousCoverageShiftOffer::STATUS_NOT_SELECTED,
                'responded_at' => now(),
            ])->save();

            $reopenedOfferIds = [];
            $otherOffers = $lockedCase->offers()
                ->lockForUpdate()
                ->with('rosterMember.caregiver.caregiverProfile.availabilities')
                ->where('id', '!=', $winningOffer->id)
                ->where('status', ContinuousCoverageShiftOffer::STATUS_CLOSED)
                ->get();
            foreach ($otherOffers as $otherOffer) {
                if (! $otherOffer->rosterMember?->isActive()
                    || ! $otherOffer->rosterMember?->replacement_opt_in
                    || ! $this->matchesShift($otherOffer->rosterMember, $shift)) {
                    continue;
                }
                $otherOffer->forceFill([
                    'status' => ContinuousCoverageShiftOffer::STATUS_PENDING,
                    'expires_at' => now()->addHours((int) config('marketplace.continuous_coverage.offer_expires_hours', 12)),
                    'responded_at' => null,
                ])->save();
                $reopenedOfferIds[] = $otherOffer->id;
            }

            $lockedCase->forceFill([
                'winning_offer_id' => null,
                'status' => $reopenedOfferIds === []
                    ? ContinuousCoverageReplacementCase::STATUS_UNRESOLVED
                    : ContinuousCoverageReplacementCase::STATUS_OPEN,
                'resolved_at' => null,
            ])->save();
            $shift->forceFill([
                'status' => ContinuousCoverageShift::STATUS_REPLACEMENT_NEEDED,
                'assigned_caregiver_user_id' => null,
                'caregiver_accepted_at' => null,
                'family_confirmed_at' => null,
                'confirmed_at' => null,
            ])->save();
            $this->events->record($shift->plan, 'replacement_family_declined', $family, $shift, [
                'not_selected_offer_id' => $winningOffer->id,
                'reopened_offer_ids' => $reopenedOfferIds,
            ]);

            return [
                $shift->fresh(['plan.family']),
                $winningOffer->fresh(['shift.plan', 'caregiver']),
                $reopenedOfferIds,
            ];
        });

        $this->notifications->replacementNotSelected($notSelectedOffer);
        if ($reopenedOfferIds === []) {
            $this->notifications->gapUnresolved($shift);
        } else {
            ContinuousCoverageShiftOffer::query()
                ->with(['shift.plan', 'caregiver'])
                ->whereKey($reopenedOfferIds)
                ->get()
                ->each(fn (ContinuousCoverageShiftOffer $offer) => $this->notifications->replacementOffer($offer));
        }

        return $shift->fresh();
    }

    /** @return Collection<int, ContinuousCoverageShiftOffer> */
    public function retryMatching(ContinuousCoverageReplacementCase $case, User $family): Collection
    {
        $this->assertEnabledFor($family);
        $offers = DB::transaction(function () use ($case, $family) {
            $lockedCase = ContinuousCoverageReplacementCase::query()->lockForUpdate()->findOrFail($case->id);
            $shift = ContinuousCoverageShift::query()->lockForUpdate()->with('plan')->findOrFail($lockedCase->continuous_coverage_shift_id);
            abort_unless($family->role === 'family' && app(FamilyAccountContext::class)->canAccessRecord($family, $shift->plan), 403);
            if ($shift->status !== ContinuousCoverageShift::STATUS_REPLACEMENT_NEEDED
                || ! in_array($lockedCase->status, [
                    ContinuousCoverageReplacementCase::STATUS_OPEN,
                    ContinuousCoverageReplacementCase::STATUS_UNRESOLVED,
                ], true)) {
                throw ValidationException::withMessages(['replacement' => 'This shift is no longer waiting for replacement offers.']);
            }

            $existingCaregiverIds = $lockedCase->offers()->pluck('caregiver_user_id');
            $eligible = $shift->plan->rosterMembers()
                ->with('caregiver.caregiverProfile.availabilities')
                ->where('status', ContinuousCoverageRosterMember::STATUS_ACTIVE)
                ->where('replacement_opt_in', true)
                ->where('caregiver_user_id', '!=', $lockedCase->original_caregiver_user_id)
                ->whereNotIn('caregiver_user_id', $existingCaregiverIds)
                ->get()
                ->filter(fn (ContinuousCoverageRosterMember $member) => $this->matchesShift($member, $shift));

            $created = collect();
            foreach ($eligible as $member) {
                $created->push(ContinuousCoverageShiftOffer::query()->create([
                    'replacement_case_id' => $lockedCase->id,
                    'continuous_coverage_shift_id' => $shift->id,
                    'roster_member_id' => $member->id,
                    'caregiver_user_id' => $member->caregiver_user_id,
                    'status' => ContinuousCoverageShiftOffer::STATUS_PENDING,
                    'expires_at' => now()->addHours((int) config('marketplace.continuous_coverage.offer_expires_hours', 12)),
                ]));
            }

            if ($created->isNotEmpty()) {
                $lockedCase->forceFill([
                    'status' => ContinuousCoverageReplacementCase::STATUS_OPEN,
                    'winning_offer_id' => null,
                    'resolved_at' => null,
                ])->save();
            }
            $this->events->record($shift->plan, 'replacement_matching_retried', $family, $shift, [
                'replacement_case_id' => $lockedCase->id,
                'new_offer_ids' => $created->pluck('id')->map(fn ($id) => (int) $id)->all(),
            ]);

            return $created->map(fn (ContinuousCoverageShiftOffer $offer) => $offer->fresh(['shift.plan', 'caregiver']));
        });

        if ($offers->isEmpty()) {
            $this->notifications->gapUnresolved($case->shift()->with('plan.family')->firstOrFail());
        } else {
            $offers->each(fn (ContinuousCoverageShiftOffer $offer) => $this->notifications->replacementOffer($offer));
        }

        return $offers;
    }

    public function decline(ContinuousCoverageShiftOffer $offer, User $caregiver): ContinuousCoverageShiftOffer
    {
        $declined = $this->respond($offer, $caregiver, false);
        $unresolvedShift = DB::transaction(function () use ($declined): ?ContinuousCoverageShift {
            $case = ContinuousCoverageReplacementCase::query()
                ->lockForUpdate()
                ->with('shift.plan')
                ->findOrFail($declined->replacement_case_id);
            if ($case->status !== ContinuousCoverageReplacementCase::STATUS_OPEN
                || $case->offers()->where('status', ContinuousCoverageShiftOffer::STATUS_PENDING)->exists()) {
                return null;
            }

            $case->forceFill(['status' => ContinuousCoverageReplacementCase::STATUS_UNRESOLVED])->save();

            return $case->shift;
        });
        if ($unresolvedShift) {
            $this->notifications->gapUnresolved($unresolvedShift);
        }

        return $declined;
    }

    private function confirmLocked(ContinuousCoverageShift $shift, ContinuousCoverageReplacementCase $case, ContinuousCoverageShiftOffer $offer, $at): void
    {
        $shift->forceFill([
            'assigned_caregiver_user_id' => $offer->caregiver_user_id,
            'status' => ContinuousCoverageShift::STATUS_CONFIRMED,
            'caregiver_accepted_at' => $offer->responded_at ?: $at,
            'family_confirmed_at' => $at,
            'confirmed_at' => $at,
        ]);
        $case->forceFill([
            'winning_offer_id' => $offer->id,
            'status' => ContinuousCoverageReplacementCase::STATUS_RESOLVED,
            'resolved_at' => $at,
        ]);
    }

    private function matchesShift(ContinuousCoverageRosterMember $member, ContinuousCoverageShift $shift): bool
    {
        $profile = $member->caregiver?->caregiverProfile;
        if (! $profile || $profile->status !== 'active') {
            return false;
        }

        $localStart = $shift->scheduled_start_at->copy()->setTimezone($shift->plan->timezone);
        $localEnd = $shift->scheduled_end_at->copy()->setTimezone($shift->plan->timezone);
        $day = $localStart->dayOfWeek;
        $eligibleDays = array_map('intval', (array) $member->eligible_days);
        if ($eligibleDays !== [] && ! in_array($day, $eligibleDays, true)) {
            return false;
        }

        $eligibleShiftTypes = array_map('strval', (array) $member->eligible_shift_types);
        if ($eligibleShiftTypes !== []) {
            $isOvernight = $localEnd->toDateString() !== $localStart->toDateString()
                || $localStart->hour >= 18
                || $localStart->hour < 6;
            $shiftTypes = [
                $isOvernight ? 'overnight' : 'daytime',
                ((int) round($shift->scheduled_minutes / 60)).'_hour',
            ];
            if (array_intersect($eligibleShiftTypes, $shiftTypes) === []) {
                return false;
            }
        }

        $availability = $profile->availabilities;
        if ($availability->isNotEmpty()) {
            if (! $this->fitsWeeklyAvailability($availability, $localStart, (int) $shift->scheduled_minutes)) {
                return false;
            }
        }

        $hasCoverageConflict = ContinuousCoverageShift::query()
            ->where('id', '!=', $shift->id)
            ->where('assigned_caregiver_user_id', $member->caregiver_user_id)
            ->whereIn('status', [
                ContinuousCoverageShift::STATUS_CONFIRMED,
                ContinuousCoverageShift::STATUS_IN_PROGRESS,
                ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION,
            ])
            ->where('scheduled_start_at', '<', $shift->scheduled_end_at)
            ->where('scheduled_end_at', '>', $shift->scheduled_start_at)
            ->exists();
        if ($hasCoverageConflict) {
            return false;
        }

        return ! CareBooking::query()
            ->where('caregiver_user_id', $member->caregiver_user_id)
            ->whereIn('status', [CareBooking::STATUS_SCHEDULED, CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED])
            ->where('scheduled_start_at', '<', $shift->scheduled_end_at)
            ->where('scheduled_end_at', '>', $shift->scheduled_start_at)
            ->exists();
    }

    private function fitsWeeklyAvailability(Collection $availability, Carbon $localStart, int $shiftMinutes): bool
    {
        $weekMinutes = 7 * 1440;
        $intervals = [];
        foreach ($availability as $slot) {
            $slotStartTime = (((int) substr((string) $slot->start_time, 0, 2)) * 60)
                + (int) substr((string) $slot->start_time, 3, 2);
            $slotEndTime = (((int) substr((string) $slot->end_time, 0, 2)) * 60)
                + (int) substr((string) $slot->end_time, 3, 2);
            $slotStart = ((int) $slot->day_of_week * 1440) + $slotStartTime;
            $slotEnd = ((int) $slot->day_of_week * 1440) + $slotEndTime;
            if ($slotEndTime <= $slotStartTime) {
                $slotEnd += 1440;
            }
            foreach ([-$weekMinutes, 0, $weekMinutes] as $offset) {
                $intervals[] = [$slotStart + $offset, $slotEnd + $offset];
            }
        }

        usort($intervals, fn (array $left, array $right): int => $left[0] <=> $right[0]);
        $merged = [];
        foreach ($intervals as [$start, $end]) {
            $last = array_key_last($merged);
            // The existing availability editor cannot express 24:00. Treat a
            // 23:59/00:00 pair as contiguous rather than creating a false gap.
            if ($last === null || $start > $merged[$last][1] + 1) {
                $merged[] = [$start, $end];

                continue;
            }
            $merged[$last][1] = max($merged[$last][1], $end);
        }

        $shiftStart = ((int) $localStart->dayOfWeek * 1440) + ((int) $localStart->hour * 60) + (int) $localStart->minute;
        $shiftEnd = $shiftStart + $shiftMinutes;

        return collect($merged)->contains(
            fn (array $interval): bool => $interval[0] <= $shiftStart && $interval[1] >= $shiftEnd,
        );
    }

    private function linkIfNear(ContinuousCoverageShift $shift): void
    {
        if ($shift->scheduled_start_at->lte(now()->addHours((int) config('marketplace.continuous_coverage.booking_horizon_hours', 48)))) {
            $this->bookings->linkConfirmedShift($shift);
        }
    }

    private function assertEnabledFor(User $user): void
    {
        if (! $this->access->allows($user)) {
            throw ValidationException::withMessages(['coverage' => 'Continuous Coverage is not currently available for this account.']);
        }
    }
}
