<?php

namespace App\Services\RegularCare;

use App\Models\CareArrangement;
use App\Models\CareArrangementNeed;
use App\Models\CareBooking;
use App\Models\CarePlan;
use App\Models\CarePlanEvent;
use App\Models\CarePlanScheduleChange;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Payments\BookingPaymentService;
use App\Support\WeeklySchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SharedCareService
{
    public function fingerprint(CareRequest $request): string
    {
        return hash('sha256', json_encode([$request->recurringScheduleSlots(), $request->recurring_starts_on?->toDateString(), $request->recurring_ends_on?->toDateString()]));
    }

    public function availableSlots(CareRequest $request): array
    {
        $slots = $request->recurringScheduleSlots();
        if (! $request->care_arrangement_id) {
            return $slots;
        }
        $plans = CarePlan::where('care_arrangement_id', $request->care_arrangement_id)
            ->whereIn('status', [CarePlan::STATUS_ACTIVE, CarePlan::STATUS_PAYMENT_ATTENTION, CarePlan::STATUS_PAUSED])->get();

        return array_values(array_filter($slots, function ($slot) use ($plans, $request) {
            foreach ($request->careArrangement?->exceptions ?? [] as $exception) {
                if (! ($exception['until'] ?? null) && in_array($slot['day'], $exception['days'])) {
                    return false;
                }
            }
            foreach ($plans as $plan) {
                if (in_array($slot['day'], $plan->schedule_days ?? [])
                    && (! $plan->ends_on || ($request->recurring_ends_on && $plan->ends_on->gte($request->recurring_ends_on)))) {
                    return false;
                }
            }

            return true;
        }));
    }

    public function offer(CareRequest $request, array $days): array
    {
        $request->refresh();
        $days = array_values(array_unique(array_map('intval', $days)));
        sort($days);
        $slots = array_values(array_filter($this->availableSlots($request), fn ($slot) => in_array($slot['day'], $days, true)));
        $this->ensure($days !== [] && count($slots) === count($days), 'Choose at least one currently available requested day.', 'selectedDays');
        $timezone = config('app.timezone', 'America/New_York');
        $start = Carbon::parse($request->recurring_starts_on, $timezone)->max(now($timezone)->startOfDay());
        if ($request->care_arrangement_id) {
            foreach (CarePlan::where('care_arrangement_id', $request->care_arrangement_id)->get() as $plan) {
                if (array_intersect($days, $plan->schedule_days ?? []) && $plan->ends_on) {
                    $start = $start->max(Carbon::parse($plan->ends_on->toDateString(), $timezone)->addDay());
                }
            }
        }
        while (! in_array($start->dayOfWeek, $days, true)
            || $start->copy()->setTimeFromTimeString(WeeklySchedule::forDay($slots, $start->dayOfWeek)['start_time'] ?? '00:00')->lte(now())) {
            $start->addDay();
        }
        $this->ensure(! $request->recurring_ends_on || $start->toDateString() <= $request->recurring_ends_on->toDateString(), 'This request has no future days available.', 'selectedDays');

        return ['slots' => $slots, 'starts_on' => $start->toDateString(), 'ends_on' => $request->recurring_ends_on?->toDateString(), 'timezone' => $timezone];
    }

    public function hire(CareRequest $request, CareRequestApplication $application, User $family): CarePlan
    {
        return DB::transaction(function () use ($request, $application, $family) {
            app(\App\Services\Screening\CaregiverWorkEligibility::class)->assertUserCanStartNewWork((int) $application->caregiver_user_id);
            $source = CareRequest::lockForUpdate()->findOrFail($request->id);
            $applicant = CareRequestApplication::lockForUpdate()->findOrFail($application->id);
            abort_unless(app(FamilyAccountContext::class)->canAccessRecord($family, $source) && $applicant->care_request_id === $source->id, 403);
            if ($source->care_arrangement_id) {
                CareArrangement::lockForUpdate()->findOrFail($source->care_arrangement_id);
            }
            User::lockForUpdate()->findOrFail($applicant->caregiver_user_id);
            $existing = CarePlan::where('care_arrangement_id', $source->care_arrangement_id)
                ->where('metadata->shared_application_id', $applicant->id)->where('metadata->shared_offer_version', $applicant->offer_version)->first();
            if ($source->care_arrangement_id && $existing) {
                return $existing;
            }
            $this->ensure($source->status === CareRequest::STATUS_OPEN && in_array($applicant->status, ['applied', 'shortlisted']), 'This application is no longer available to hire.');
            $this->ensure($application->offer_version === $applicant->offer_version, 'The caregiver changed their application. Review it again before hiring.');
            $this->ensure($applicant->caregiver?->caregiverProfile?->status === 'active', 'This caregiver is no longer available for new care.');
            $this->ensure(! $applicant->schedule_fingerprint || hash_equals($this->fingerprint($source), $applicant->schedule_fingerprint), 'The requested schedule changed. Ask the caregiver to update their application.');
            $offer = $this->offer($source, $applicant->selected_days ?? WeeklySchedule::days($source->recurringScheduleSlots()));
            if ($applicant->offered_schedule) {
                $this->ensure($offer['slots'] === $applicant->offered_schedule['slots'], 'Some offered days are no longer available. Ask the caregiver to update their application.');
                $this->ensure($offer['starts_on'] === $applicant->offered_schedule['starts_on'], 'The first available date changed. Ask the caregiver to update their application.');
            }
            $this->assertScheduleAvailable((int) $applicant->caregiver_user_id, $source->recipient?->care_recipient_profile_id, $offer['slots'], $offer['starts_on'], $offer['ends_on'], $offer['timezone'], arrangementId: $source->care_arrangement_id);
            $arrangement = CareArrangement::firstOrCreate(['source_care_request_id' => $source->id], [
                'family_account_id' => $source->family_account_id, 'family_user_id' => $source->family_user_id,
                'title' => $source->title, 'schedule_slots' => $source->recurringScheduleSlots(),
                'starts_on' => $source->recurring_starts_on, 'ends_on' => $source->recurring_ends_on, 'timezone' => $offer['timezone'],
            ]);
            $source->forceFill(['care_arrangement_id' => $arrangement->id])->save();
            // Each caregiver retains the existing isolated agreement/payment lifecycle.
            $child = $source->replicate(['care_plan_id', 'first_hire_at', 'first_applicant_at', 'first_shortlist_at']);
            $child->forceFill(['is_system_generated' => true, 'origin' => 'shared_regular_care', 'status' => CareRequest::STATUS_OPEN,
                'recurring_schedule' => $offer['slots'], 'recurring_days' => WeeklySchedule::days($offer['slots']),
                'recurring_starts_on' => $offer['starts_on'], 'recurring_ends_on' => $offer['ends_on']])->save();
            if ($source->recipient) {
                $recipient = $source->recipient->replicate();
                $recipient->care_request_id = $child->id;
                $recipient->save();
            }
            $child->tasks()->sync($source->tasks->mapWithKeys(fn ($task) => [$task->id => ['task_note' => $task->pivot->task_note]])->all());
            $childApplication = $applicant->replicate();
            $childApplication->forceFill(['care_request_id' => $child->id, 'selected_days' => null, 'status' => 'applied'])->save();
            $plan = app(CarePlanService::class)->activateFromRecurringRequest($child, $childApplication, $family);
            $plan->forceFill(['metadata' => array_merge($plan->metadata ?? [], ['shared_application_id' => $applicant->id, 'shared_offer_version' => $applicant->offer_version])])->save();
            $applicant->update(['status' => 'hired']);
            CareRequestConversation::findOrCreateForApplication($applicant->loadMissing('careRequest'), $family->id);
            $source->forceFill(['first_hire_at' => $source->first_hire_at ?: now()])->save();
            $this->refreshRecruitment($arrangement);
            $this->syncNeeds($arrangement, now($arrangement->timezone)->startOfDay(), now($arrangement->timezone)->addWeeks(6));

            return $plan->fresh();
        }, 3);
    }

    public function refreshRecruitment(CareArrangement $arrangement): void
    {
        $source = $arrangement->sourceRequest->fresh();
        if (in_array($source->status, [CareRequest::STATUS_CANCELLED, CareRequest::STATUS_EXPIRED])) {
            return;
        }
        $source->update(['status' => $arrangement->status === 'active' && $this->availableSlots($source) !== [] ? CareRequest::STATUS_OPEN : CareRequest::STATUS_FILLED]);
    }

    public function syncNeeds(CareArrangement $arrangement, Carbon $from, Carbon $through): Collection
    {
        $from = $from->copy()->setTimezone($arrangement->timezone)->startOfDay();
        $through = $through->copy()->setTimezone($arrangement->timezone)->endOfDay();
        for ($date = $from->copy(); $date->lte($through); $date->addDay()) {
            if ($arrangement->status !== 'active' || $date->toDateString() < $arrangement->starts_on->toDateString()
                || ($arrangement->ends_on && $date->toDateString() > $arrangement->ends_on->toDateString())) {
                continue;
            }
            $slot = WeeklySchedule::forDay($arrangement->schedule_slots, $date->dayOfWeek);
            if (! $slot) {
                continue;
            }
            $start = $date->copy()->setTimeFromTimeString($slot['start_time'])->setTimezone(config('app.timezone'));
            $end = $date->copy()->setTimeFromTimeString($slot['end_time'])->setTimezone(config('app.timezone'));
            $waived = collect($arrangement->exceptions ?? [])->contains(fn ($exception) => in_array($date->dayOfWeek, $exception['days']) && ! in_array($date->toDateString(), $exception['restored_dates'] ?? []) && $date->toDateString() >= $exception['from'] && (! ($exception['until'] ?? null) || $date->toDateString() < $exception['until']));
            $need = $arrangement->needs()->firstOrCreate(['need_key' => 'regular:'.$date->toDateString().':'.$slot['start_time']], ['starts_at' => $start, 'ends_at' => $end, 'status' => $waived ? 'waived' : 'needed']);
            if ($waived && $need->status !== 'waived') {
                $need->update(['status' => 'waived']);
            }
        }
        foreach (CareBooking::whereHas('carePlan', fn ($q) => $q->where('care_arrangement_id', $arrangement->id))
            ->whereBetween('scheduled_start_at', [$from, $through])->get() as $booking) {
            $this->linkBooking($booking);
        }

        return $arrangement->needs()->whereBetween('starts_at', [$from, $through])->with(['bookings.caregiver', 'bookings.payment', 'offers.carePlan.caregiver'])->orderBy('starts_at')->get();
    }

    public function linkBooking(CareBooking $booking): void
    {
        if ($booking->care_arrangement_need_id || ! $booking->carePlan?->care_arrangement_id) {
            return;
        }
        $arrangement = CareArrangement::findOrFail($booking->carePlan->care_arrangement_id);
        $start = $booking->scheduled_start_at;
        $end = $booking->scheduled_end_at;
        if (! $start || ! $end) {
            return;
        }
        $local = $start->copy()->setTimezone($arrangement->timezone);
        $kind = $booking->plan_visit_kind === 'regular' ? 'regular' : 'extra';
        $need = $arrangement->needs()->where('starts_at', $start)->where('ends_at', $end)->first()
            ?? $arrangement->needs()->create(['need_key' => $kind.':'.$local->toDateString().':'.$local->format('H:i'), 'kind' => $kind, 'starts_at' => $start, 'ends_at' => $end]);
        $booking->forceFill(['care_arrangement_need_id' => $need->id])->save();
    }

    public function assertFamily(CareArrangement $arrangement, User $family): void
    {
        abort_unless($family->role === 'family' && app(FamilyAccountContext::class)->canAccessRecord($family, $arrangement), 403);
    }

    public function endAssignment(CarePlan $plan, User $family, string $from, bool $stillNeeded = true, bool $recruit = true): void
    {
        DB::transaction(function () use ($plan, $family, $from, $stillNeeded, $recruit) {
            $arrangement = CareArrangement::lockForUpdate()->findOrFail($plan->care_arrangement_id);
            $this->assertFamily($arrangement, $family);
            $plan = CarePlan::lockForUpdate()->findOrFail($plan->id);
            $cutoff = Carbon::parse($from, $arrangement->timezone)->startOfDay();
            $this->ensure($cutoff->gte(now($arrangement->timezone)->startOfDay()), 'Choose today or a future date.');
            $this->ensure($plan->isLive(), 'This caregiver agreement has already ended.');
            $this->ensure(! $plan->ends_on || $cutoff->toDateString() <= $plan->ends_on->copy()->addDay()->toDateString(), 'Choose a date on or before the current end. Extending care needs a new agreement.');
            $this->ensure(! $plan->generatedBookings()->whereIn('status', ['in_progress', 'paused'])->where('scheduled_start_at', '>=', $cutoff)->exists(), 'A visit is in progress. Finish or resolve that visit before ending care from today.');
            $this->syncNeeds($arrangement, $cutoff, $cutoff->copy()->addWeeks(6));
            foreach ($plan->generatedBookings()->where('status', 'scheduled')->where('scheduled_start_at', '>=', $cutoff)->get() as $booking) {
                $this->cancelBooking($booking, $family, 'Shared care: caregiver assignment ended.');
            }
            $plan->forceFill(['ends_on' => $cutoff->copy()->subDay()->toDateString(),
                'status' => $cutoff->lte(now()) ? CarePlan::STATUS_ENDED : $plan->status,
                'ended_at' => $cutoff->lte(now()) ? now() : null])->save();
            $plan->scheduleChanges()->where('status', 'pending')->where('effective_on', '>=', $cutoff->toDateString())->update(['status' => 'withdrawn', 'responded_at' => now(), 'responded_by_user_id' => $family->id]);
            if (! $stillNeeded) {
                $arrangement->update(['exceptions' => [...($arrangement->exceptions ?? []), ['days' => $plan->schedule_days, 'from' => $from, 'until' => null]]]);
                $this->syncNeeds($arrangement->fresh(), $cutoff, $cutoff->copy()->addWeeks(6));
            }
            CarePlanEvent::create(['care_plan_id' => $plan->id, 'actor_user_id' => $family->id, 'event_type' => 'shared_care_assignment_ended',
                'reason' => 'Family ended this caregiver assignment.', 'payload' => ['from' => $from, 'care_still_needed' => $stillNeeded, 'recruit' => $recruit]]);
            if ($stillNeeded && $recruit) {
                $arrangement->sourceRequest->update(['status' => CareRequest::STATUS_OPEN]);
            } elseif ($this->availableSlots($arrangement->sourceRequest->fresh()) === []) {
                $arrangement->sourceRequest->update(['status' => CareRequest::STATUS_FILLED]);
            }
            $this->notify($plan, 'Care schedule updated', 'Your visits from '.$cutoff->format('F j, Y').' have been cancelled. Earlier visits and outstanding payments remain in your history.');
        }, 3);
    }

    public function cancelNeed(CareArrangementNeed $need, User $family, bool $stillNeeded): void
    {
        DB::transaction(function () use ($need, $family, $stillNeeded) {
            $arrangement = CareArrangement::lockForUpdate()->findOrFail($need->care_arrangement_id);
            $this->assertFamily($arrangement, $family);
            $need = CareArrangementNeed::lockForUpdate()->findOrFail($need->id);
            $this->ensure($need->starts_at->isFuture(), 'This visit has started or passed. Open the visit for time corrections or support.');
            $this->ensure(! $need->bookings()->whereIn('status', ['in_progress', 'paused', 'completed'])->exists(), 'This visit has recorded care. Open the visit for review.');
            foreach ($need->bookings()->where('status', 'scheduled')->get() as $booking) {
                $this->cancelBooking($booking, $family, 'Shared care: '.($stillNeeded ? 'replacement needed.' : 'care not needed.'));
                $this->notify($booking->carePlan, 'Visit cancelled', 'Your visit on '.$need->starts_at->format('F j, g:i A').' was cancelled. Your other regular visits continue.');
            }
            $need->offers()->where('status', 'pending')->update(['status' => 'withdrawn', 'responded_at' => now(), 'responded_by_user_id' => $family->id]);
            $suppressed = $arrangement->plans()->get()->filter(fn ($plan) => app(CarePlanOccurrenceService::class)->overlapsScheduledRegularOccurrence($plan, $need->starts_at, $need->ends_at))->pluck('id')->all();
            $need->update(['status' => $stillNeeded ? 'needed' : 'waived', 'suppressed_plan_ids' => array_values(array_unique([...($need->suppressed_plan_ids ?? []), ...$suppressed]))]);
        }, 3);
    }

    private function cancelBooking(CareBooking $booking, User $family, string $reason): void
    {
        app(BookingPaymentService::class)->cancelForBooking($booking);
        $booking->forceFill(['status' => CareBooking::STATUS_CANCELLED, 'cancelled_at' => now(), 'cancelled_by_user_id' => $family->id,
            'cancellation_reason' => $reason, 'late_cancel_flag' => app(\App\Services\Booking\BookingTrustService::class)->markLateCancelFlag($booking)])->save();
        app(\App\Services\Booking\BookingTrustService::class)->recordEvent($booking, $family->id, 'family', 'shared_care_visit_cancelled', ['reason' => $reason]);
    }

    public function requestVisit(CareArrangement $arrangement, User $family, int $planId, Carbon $start, Carbon $end, ?int $needId = null, ?string $note = null): CarePlanScheduleChange
    {
        $caregiverUserId = $arrangement->plans()->whereKey($planId)->value('caregiver_user_id');
        return DB::transaction(function () use ($arrangement, $family, $planId, $start, $end, $needId, $note, $caregiverUserId) {
            \App\Models\CaregiverProfile::where('user_id', $caregiverUserId)->lockForUpdate()->first();
            $arrangement = CareArrangement::lockForUpdate()->findOrFail($arrangement->id);
            $this->assertFamily($arrangement, $family);
            $plan = $arrangement->plans()->lockForUpdate()->findOrFail($planId);
            $this->ensure($plan->isLive() && $start->isFuture() && $end->gt($start), 'Choose an active caregiver and a future visit with valid hours.');
            $this->ensure(! $plan->ends_on || $start->copy()->setTimezone($arrangement->timezone)->toDateString() <= $plan->ends_on->toDateString(), 'This visit is after the caregiver agreement ends.');
            $this->syncNeeds($arrangement, $start->copy()->startOfDay(), $end->copy()->endOfDay());
            if ($needId) {
                $need = $arrangement->needs()->lockForUpdate()->findOrFail($needId);
                $this->ensure($need->status === 'needed' && $need->starts_at->eq($start) && $need->ends_at->eq($end), 'This care need changed. Review its date and hours.');
            } else {
                $need = $arrangement->needs()->where('starts_at', $start)->where('ends_at', $end)->first();
                $overlap = $arrangement->needs()->where('status', 'needed')->where('starts_at', '<', $end)->where('ends_at', '>', $start)->when($need, fn ($q) => $q->whereKeyNot($need->id))->exists();
                $this->ensure(! $overlap, 'These hours overlap requested care. Open that visit to change it or find cover.');
                $need ??= $arrangement->needs()->create(['need_key' => 'extra:'.$start->timestamp.':'.$end->timestamp, 'kind' => 'extra', 'starts_at' => $start, 'ends_at' => $end]);
                $this->ensure($need->status !== 'waived', 'Care was marked not needed for these hours. Restore the need before requesting a visit.');
            }
            $this->ensure(! $need->bookings()->where('status', '!=', 'cancelled')->exists(), 'This visit already has a caregiver.');
            $this->ensure(! $need->offers()->where('status', 'pending')->exists(), 'A caregiver is already considering this visit. Withdraw that offer before asking someone else.');
            $this->assertVisitAvailable($plan, $start, $end, $need->id);
            $change = app(CarePlanService::class)->requestExtraVisit($plan, $family, $start, $end, $note, $need->id);
            $change->forceFill(['care_arrangement_need_id' => $need->id])->save();

            return $change;
        }, 3);
    }

    public function withdrawOffer(CareArrangementNeed $need, User $family): void
    {
        DB::transaction(function () use ($need, $family) {
            $arrangement = CareArrangement::lockForUpdate()->findOrFail($need->care_arrangement_id);
            $this->assertFamily($arrangement, $family);
            $need->offers()->where('status', 'pending')->update(['status' => 'withdrawn', 'responded_at' => now(), 'responded_by_user_id' => $family->id]);
        });
    }

    public function restoreNeed(CareArrangementNeed $need, User $family): void
    {
        DB::transaction(function () use ($need, $family) {
            $arrangement = CareArrangement::lockForUpdate()->findOrFail($need->care_arrangement_id);
            $this->assertFamily($arrangement, $family);
            $this->ensure($need->starts_at->isFuture(), 'Only future care can be restored.');
            $date = $need->starts_at->copy()->setTimezone($arrangement->timezone)->toDateString();
            $arrangement->update(['exceptions' => array_map(function ($exception) use ($date) {
                $exception['restored_dates'] = array_values(array_unique([...($exception['restored_dates'] ?? []), $date]));

                return $exception;
            }, $arrangement->exceptions ?? [])]);
            $need->update(['status' => 'needed']);
        });
    }

    public function assertVisitAvailable(CarePlan $plan, Carbon $start, Carbon $end, ?int $needId = null): void
    {
        $recipientId = $plan->care_recipient_profile_id;
        $conflict = CareBooking::withoutGlobalScope('authenticated_family_account')->where('status', '!=', 'cancelled')->where('scheduled_start_at', '<', $end)->where('scheduled_end_at', '>', $start)
            ->where(fn ($q) => $q->where('caregiver_user_id', $plan->caregiver_user_id)
                ->orWhereHas('carePlan', fn ($q) => $q->where('care_arrangement_id', $plan->care_arrangement_id)->when($recipientId, fn ($q) => $q->orWhere('care_recipient_profile_id', $recipientId))))->exists();
        $this->ensure(! $conflict, 'These hours overlap an existing caregiver or recipient visit.');
        $plans = CarePlan::withoutGlobalScope('authenticated_family_account')->whereIn('status', [CarePlan::STATUS_ACTIVE, CarePlan::STATUS_PAYMENT_ATTENTION, CarePlan::STATUS_PAUSED])
            ->where(fn ($q) => $q->where('caregiver_user_id', $plan->caregiver_user_id)->orWhere('care_arrangement_id', $plan->care_arrangement_id)
                ->when($recipientId, fn ($q) => $q->orWhere('care_recipient_profile_id', $recipientId)))->get();
        foreach ($plans as $other) {
            if (! app(CarePlanOccurrenceService::class)->overlapsScheduledRegularOccurrence($other, $start, $end)) {
                continue;
            }
            $cancelled = $needId && (in_array($other->id, CareArrangementNeed::find($needId)?->suppressed_plan_ids ?? []) || CareBooking::where('care_arrangement_need_id', $needId)->where('care_plan_id', $other->id)->where('status', 'cancelled')->exists());
            $this->ensure((bool) $cancelled, 'These hours are part of an existing regular schedule. Request cover from that visit first.');
        }
    }

    private function notify(CarePlan $plan, string $title, string $body): void
    {
        DB::afterCommit(function () use ($plan, $title, $body) {
            app(\App\Services\Notifications\MarketplaceNotificationService::class)->notify(
                recipients: $plan->caregiver, eventKey: \App\Support\MarketplaceEvent::REGULAR_CARE_SCHEDULE_CHANGE_REQUESTED,
                title: $title, body: $body, url: route('caregiver.regular-clients.index'),
                payload: ['care_plan_id' => $plan->id], subject: $plan,
            );
        });
    }

    public function assertScheduleAvailable(int $caregiverId, ?int $recipientId, array $slots, string $startsOn, ?string $endsOn, string $timezone, ?int $excludePlanId = null, ?int $arrangementId = null): void
    {
        $plans = CarePlan::withoutGlobalScope('authenticated_family_account')->whereIn('status', [CarePlan::STATUS_ACTIVE, CarePlan::STATUS_PAYMENT_ATTENTION, CarePlan::STATUS_PAUSED])
            ->when($excludePlanId, fn ($q) => $q->whereKeyNot($excludePlanId))
            ->where(fn ($q) => $q->where('caregiver_user_id', $caregiverId)->when($recipientId, fn ($q) => $q->orWhere('care_recipient_profile_id', $recipientId))->when($arrangementId, fn ($q) => $q->orWhere('care_arrangement_id', $arrangementId)))->get();
        foreach ($plans as $plan) {
            $first = Carbon::parse($startsOn, $timezone)->max(Carbon::parse($plan->starts_on->toDateString(), $timezone));
            $last = collect([$endsOn, $plan->ends_on?->toDateString()])->filter()->sort()->first();
            foreach ($slots as $slot) {
                $other = WeeklySchedule::forDay($plan->weeklyScheduleSlots(), $slot['day']);
                if (! $other || $slot['start_time'] >= $other['end_time'] || $slot['end_time'] <= $other['start_time']) {
                    continue;
                }
                $date = $first->copy();
                while ($date->dayOfWeek !== $slot['day']) {
                    $date->addDay();
                }
                $this->ensure($last && $date->toDateString() > $last, 'This schedule overlaps an existing caregiver or recipient commitment.');
            }
        }
        // Also consider one-off and retained final visits before a repeating assignment starts.
        $bookings = CareBooking::withoutGlobalScope('authenticated_family_account')->whereIn('status', ['scheduled', 'in_progress', 'paused'])
            ->where(fn ($q) => $q->where('caregiver_user_id', $caregiverId)
                ->when($arrangementId, fn ($q) => $q->orWhereHas('carePlan', fn ($q) => $q->where('care_arrangement_id', $arrangementId)))
                ->when($recipientId, fn ($q) => $q->orWhereHas('carePlan', fn ($q) => $q->where('care_recipient_profile_id', $recipientId))))
            ->where('scheduled_end_at', '>=', Carbon::parse($startsOn, $timezone))
            ->when($excludePlanId, fn ($q) => $q->where(fn ($q) => $q->whereNull('care_plan_id')->orWhere('care_plan_id', '!=', $excludePlanId)))->get();
        foreach ($bookings as $booking) {
            $local = $booking->scheduled_start_at->copy()->setTimezone($timezone);
            if ($endsOn && $local->toDateString() > $endsOn) {
                continue;
            }
            $slot = WeeklySchedule::forDay($slots, $local->dayOfWeek);
            $this->ensure(! $slot || $local->format('H:i') >= $slot['end_time'] || $booking->scheduled_end_at->copy()->setTimezone($timezone)->format('H:i') <= $slot['start_time'], 'This caregiver already has a visit during the selected hours.');
        }
    }

    private function ensure(bool $condition, string $message, string $field = 'sharedCare'): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
