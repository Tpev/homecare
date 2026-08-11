<?php

namespace App\Services\Notifications;

use App\Models\CareBooking;
use App\Models\CareBookingChangeRequest;
use App\Models\CareBookingTimeCorrection;
use App\Models\CarePlan;
use App\Models\CarePlanScheduleChange;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareRequestInvitation;
use App\Models\CompletedExtraVisitRequest;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\MarketplaceEvent;
use App\Support\MarketplaceNotificationPresentation;
use App\Support\WeeklySchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MarketplaceNotificationContextBuilder
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function enrich(
        User $recipient,
        string $eventKey,
        string $title,
        string $body,
        array $payload,
        ?Model $subject,
    ): array {
        $derivedDetails = $this->details($recipient, $eventKey, $subject, $payload);
        $explicitDetails = collect((array) ($payload['email_details'] ?? []))
            ->filter(fn ($detail): bool => is_array($detail))
            ->values();

        $details = $explicitDetails
            ->concat($derivedDetails)
            ->filter(fn (array $detail): bool => trim((string) ($detail['label'] ?? '')) !== ''
                && trim((string) ($detail['value'] ?? '')) !== '')
            ->unique(fn (array $detail): string => Str::lower(trim((string) $detail['label'])))
            ->take(10)
            ->values()
            ->all();

        return array_merge($payload, [
            'first_name' => $payload['first_name'] ?? $this->firstName($recipient->name),
            'preheader' => $payload['preheader'] ?? $this->preheader($title, $body),
            'email_details' => $details,
            'email_next_steps' => array_values(array_filter(
                (array) ($payload['email_next_steps'] ?? MarketplaceNotificationPresentation::nextSteps($eventKey, (string) $recipient->role)),
                fn ($step): bool => is_string($step) && trim($step) !== '',
            )),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return Collection<int, array{label:string,value:string}>
     */
    private function details(User $recipient, string $eventKey, ?Model $subject, array $payload): Collection
    {
        return match (true) {
            $subject instanceof CareRequestInvitation => $this->invitationDetails($recipient, $subject),
            $subject instanceof CareRequestApplication => $this->applicationDetails($recipient, $subject),
            $subject instanceof CareRequest => $this->requestDetails($recipient, $subject, $eventKey),
            $subject instanceof CareBooking => $this->bookingDetails($recipient, $subject, $eventKey, $payload),
            $subject instanceof CareBookingChangeRequest => $this->bookingChangeDetails($recipient, $subject),
            $subject instanceof CarePlanScheduleChange => $this->scheduleChangeDetails($recipient, $subject),
            $subject instanceof CarePlan => $this->planDetails($recipient, $subject),
            $subject instanceof CareBookingTimeCorrection => $this->timeCorrectionDetails($recipient, $subject),
            $subject instanceof CompletedExtraVisitRequest => $this->completedExtraVisitDetails($recipient, $subject),
            $subject instanceof SupportTicket => $this->supportDetails($subject),
            $subject instanceof CareRequestConversation => $this->conversationDetails($subject),
            default => collect(),
        };
    }

    /** @return Collection<int, array{label:string,value:string}> */
    private function invitationDetails(User $recipient, CareRequestInvitation $invitation): Collection
    {
        $invitation->loadMissing(['careRequest.tasks:id,name', 'careRequest.recipient:id,care_request_id,full_name', 'application']);
        $request = $invitation->careRequest;
        if (! $request) {
            return collect();
        }

        $details = $this->requestDetails($recipient, $request, MarketplaceEvent::INVITATION_RECEIVED);
        if ((int) $recipient->id === (int) $invitation->caregiver_user_id) {
            $recipientName = $this->firstName((string) $request->recipient?->full_name);
            if ($recipientName !== '') {
                $details->prepend($this->detail('Care for', $recipientName));
            }
        }

        $responseDue = $invitation->expires_at ?: $invitation->responseDueAt();
        if ($responseDue) {
            $details->push($this->detail('Please respond by', $this->formatDateTime($responseDue, $this->requestTimezone($request))));
        }

        return $details;
    }

    /** @return Collection<int, array{label:string,value:string}> */
    private function applicationDetails(User $recipient, CareRequestApplication $application): Collection
    {
        $application->loadMissing(['careRequest.tasks:id,name', 'caregiver:id,name']);
        $details = collect();

        if ($recipient->role === 'family' && $application->caregiver) {
            $details->push($this->detail('Caregiver', $application->caregiver->name));
        }

        if ($application->proposed_rate !== null) {
            $details->push($this->detail('Proposed rate', $this->moneyFromDecimal($application->proposed_rate).'/hour'));
        }

        if ($application->careRequest) {
            return $details->concat($this->requestDetails($recipient, $application->careRequest, MarketplaceEvent::NEW_APPLICANT));
        }

        return $details;
    }

    /** @return Collection<int, array{label:string,value:string}> */
    private function requestDetails(User $recipient, CareRequest $request, string $eventKey): Collection
    {
        $request->loadMissing('tasks:id,name');
        $details = collect([$this->detail('Care request', '#'.$request->id.' · '.$request->title)]);
        $details->push($this->detail('Care type', $request->isRecurring() ? 'Regular care' : 'One-time care'));

        $timezone = $this->requestTimezone($request);
        if ($request->requested_start_at) {
            $when = $this->formatDateTime($request->requested_start_at, $timezone);
            if ($request->requested_end_at) {
                $when .= ' – '.$request->requested_end_at->copy()->setTimezone($timezone)->format('g:i A T');
            }
            $details->push($this->detail('When', $when));
        } elseif ($request->recurring_starts_on) {
            $schedule = $this->recurringRequestSchedule($request);
            if ($schedule !== '') {
                $details->push($this->detail('Schedule', $schedule));
            }
        }

        $duration = $this->durationBetween($request->requested_start_at, $request->requested_end_at);
        if ($duration !== '') {
            $details->push($this->detail('Expected duration', $duration));
        }

        $location = $this->approximateLocation($request->city, $request->state, $request->zip);
        if ($location !== '') {
            $details->push($this->detail('Location', $location));
        }

        $tasks = $request->tasks->pluck('name')->filter()->take(5)->implode(', ');
        if ($tasks !== '') {
            $details->push($this->detail('Care requested', $tasks));
        }

        if ($request->budget_min !== null || $request->budget_max !== null) {
            $details->push($this->detail('Family budget', $this->budgetLabel($request).'/hour'));
        }

        return $details;
    }

    /** @return Collection<int, array{label:string,value:string}> */
    private function bookingDetails(User $recipient, CareBooking $booking, string $eventKey, array $payload): Collection
    {
        $booking->loadMissing([
            'family:id,name',
            'caregiver:id,name',
            'careRequest.tasks:id,name',
            'carePlan',
            'payment',
        ]);

        $timezone = $booking->carePlan?->timezone ?: (string) config('app.timezone', 'America/New_York');
        $details = collect([$this->detail('Visit', '#'.$booking->id)]);
        $otherPerson = $recipient->role === 'caregiver' ? $booking->family?->name : $booking->caregiver?->name;
        if ($otherPerson) {
            $details->push($this->detail($recipient->role === 'caregiver' ? 'Family' : 'Caregiver', $otherPerson));
        }

        if ($booking->scheduled_start_at) {
            $when = $this->formatDateTime($booking->scheduled_start_at, $timezone);
            if ($booking->scheduled_end_at) {
                $when .= ' – '.$booking->scheduled_end_at->copy()->setTimezone($timezone)->format('g:i A T');
            }
            $details->push($this->detail('Scheduled time', $when));
        }

        if ($booking->worked_minutes) {
            $details->push($this->detail('Recorded care time', $this->durationLabel((int) $booking->worked_minutes)));
        }

        $address = $this->bookingLocation($booking);
        if ($address !== '' && in_array($recipient->role, ['family', 'caregiver'], true)) {
            $details->push($this->detail('Care location', $address));
        }

        $tasks = $booking->careRequest?->tasks?->pluck('name')->filter()->take(5)->implode(', ') ?: '';
        if ($tasks !== '') {
            $details->push($this->detail('Care plan', $tasks));
        }

        $amount = $this->paymentAmount($recipient, $booking, $eventKey, $payload);
        if ($amount !== null) {
            $details->push($amount);
        }

        return $details;
    }

    /** @return Collection<int, array{label:string,value:string}> */
    private function bookingChangeDetails(User $recipient, CareBookingChangeRequest $change): Collection
    {
        $change->loadMissing('booking.family:id,name', 'booking.caregiver:id,name');
        $details = $change->booking
            ? $this->bookingDetails($recipient, $change->booking, MarketplaceEvent::BOOKING_CHANGE_REQUESTED, [])
            : collect();

        $details->prepend($this->detail('Requested change', Str::headline($change->type)));
        if ($change->proposed_start_at) {
            $timezone = $change->booking?->carePlan?->timezone ?: (string) config('app.timezone', 'America/New_York');
            $value = $this->formatDateTime($change->proposed_start_at, $timezone);
            if ($change->proposed_end_at) {
                $value .= ' – '.$change->proposed_end_at->copy()->setTimezone($timezone)->format('g:i A T');
            }
            $details->push($this->detail('Proposed time', $value));
        }
        if (trim((string) $change->reason) !== '') {
            $details->push($this->detail('Reason', Str::limit(trim((string) $change->reason), 180)));
        }

        return $details;
    }

    /** @return Collection<int, array{label:string,value:string}> */
    private function planDetails(User $recipient, CarePlan $plan): Collection
    {
        $plan->loadMissing(['family:id,name', 'caregiver:id,name']);
        $details = collect([$this->detail('Regular care', '#'.$plan->id.' · '.($plan->title ?: $plan->recipientName()))]);
        $otherPerson = $recipient->role === 'caregiver' ? $plan->family?->name : $plan->caregiver?->name;
        if ($otherPerson) {
            $details->push($this->detail($recipient->role === 'caregiver' ? 'Family' : 'Caregiver', $otherPerson));
        }
        $schedule = $this->planSchedule($plan);
        if ($schedule !== '') {
            $details->push($this->detail('Schedule', $schedule));
        }
        if ($plan->hourly_rate !== null) {
            $details->push($this->detail('Hourly rate', $this->moneyFromDecimal($plan->hourly_rate).'/hour'));
        }

        return $details;
    }

    /** @return Collection<int, array{label:string,value:string}> */
    private function scheduleChangeDetails(User $recipient, CarePlanScheduleChange $change): Collection
    {
        $change->loadMissing('plan.family:id,name', 'plan.caregiver:id,name');
        $details = $change->plan ? $this->planDetails($recipient, $change->plan) : collect();
        $details->prepend($this->detail('Requested update', $change->type === CarePlanScheduleChange::TYPE_EXTRA_VISIT ? 'Additional visit' : 'Future schedule change'));
        $proposed = $change->proposed_schedule ?? [];
        $timezone = $change->plan?->timezone ?: (string) config('app.timezone', 'America/New_York');
        if ($change->type === CarePlanScheduleChange::TYPE_EXTRA_VISIT && data_get($proposed, 'start_at')) {
            $start = Carbon::parse((string) data_get($proposed, 'start_at'));
            $value = $this->formatDateTime($start, $timezone);
            if (data_get($proposed, 'end_at')) {
                $value .= ' – '.Carbon::parse((string) data_get($proposed, 'end_at'))->setTimezone($timezone)->format('g:i A T');
            }
            $details->push($this->detail('Proposed visit', $value));
        } elseif ($change->effective_on) {
            $details->push($this->detail('Effective', $change->effective_on->format('F j, Y')));
        }
        if (trim((string) $change->note) !== '') {
            $details->push($this->detail('Note', Str::limit(trim((string) $change->note), 180)));
        }

        return $details;
    }

    /** @return Collection<int, array{label:string,value:string}> */
    private function timeCorrectionDetails(User $recipient, CareBookingTimeCorrection $correction): Collection
    {
        $correction->loadMissing('booking.family:id,name', 'booking.caregiver:id,name');
        $timezone = $correction->booking?->carePlan?->timezone ?: (string) config('app.timezone', 'America/New_York');
        $details = collect([
            $this->detail('Visit', '#'.$correction->care_booking_id),
            $this->detail('Corrected start', $this->formatDateTime($correction->proposed_started_at, $timezone)),
            $this->detail('Corrected end', $this->formatDateTime($correction->proposed_completed_at, $timezone)),
            $this->detail('Unpaid break', ((int) $correction->proposed_break_minutes).' minutes'),
            $this->detail('Corrected care time', $correction->durationLabel()),
            $this->detail('Reason', $correction->reasonLabel()),
        ]);

        $financial = (array) $correction->financial_preview;
        $cents = $recipient->role === 'family'
            ? data_get($financial, 'total_charge_cents')
            : data_get($financial, 'caregiver_amount_cents');
        if (is_numeric($cents)) {
            $details->push($this->detail($recipient->role === 'family' ? 'Estimated charge' : 'Estimated earnings', $this->moneyFromCents((int) $cents)));
        }

        return $details;
    }

    /** @return Collection<int, array{label:string,value:string}> */
    private function completedExtraVisitDetails(User $recipient, CompletedExtraVisitRequest $request): Collection
    {
        $request->loadMissing('plan.family:id,name', 'plan.caregiver:id,name');
        $details = collect([
            $this->detail('Regular care plan', '#'.$request->care_plan_id),
            $this->detail('Visit start', $this->formatDateTime($request->proposed_started_at, $request->timezone)),
            $this->detail('Visit end', $this->formatDateTime($request->proposed_completed_at, $request->timezone)),
            $this->detail('Unpaid break', ((int) $request->proposed_break_minutes).' minutes'),
            $this->detail('Care time', $request->durationLabel()),
            $this->detail('Reason', $request->reasonLabel()),
        ]);
        if (trim((string) $request->care_notes) !== '') {
            $details->push($this->detail('Care provided', Str::limit(trim((string) $request->care_notes), 180)));
        }

        $financial = (array) ($request->final_financial_preview ?: $request->financial_preview);
        $cents = $recipient->role === 'family'
            ? data_get($financial, 'total_charge_cents', data_get($financial, 'amount_captured_cents'))
            : data_get($financial, 'caregiver_amount_cents');
        if (is_numeric($cents)) {
            $details->push($this->detail($recipient->role === 'family' ? 'Estimated charge' : 'Estimated earnings', $this->moneyFromCents((int) $cents)));
        }

        return $details;
    }

    /** @return Collection<int, array{label:string,value:string}> */
    private function supportDetails(SupportTicket $ticket): Collection
    {
        return collect([
            $this->detail('Support request', '#'.$ticket->id),
            $this->detail('Subject', $ticket->subject),
            $this->detail('Category', Str::headline($ticket->category)),
            $this->detail('Priority', Str::headline($ticket->priority)),
        ]);
    }

    /** @return Collection<int, array{label:string,value:string}> */
    private function conversationDetails(CareRequestConversation $conversation): Collection
    {
        $conversation->loadMissing('careRequest:id,title');

        return collect([
            $this->detail('Conversation', '#'.$conversation->id),
            $this->detail('Care request', $conversation->careRequest ? '#'.$conversation->care_request_id.' · '.$conversation->careRequest->title : '#'.$conversation->care_request_id),
        ]);
    }

    /** @return array{label:string,value:string}|null */
    private function paymentAmount(User $recipient, CareBooking $booking, string $eventKey, array $payload): ?array
    {
        $payment = $booking->payment;
        $currency = strtoupper((string) ($payment?->currency ?: 'USD'));
        $mapping = match ($eventKey) {
            MarketplaceEvent::PAYMENT_AUTHORIZED => ['Authorized amount', data_get($payload, 'amount_authorized_cents', $payment?->amount_authorized_cents)],
            MarketplaceEvent::PAYMENT_CAPTURED => ['Amount billed', data_get($payload, 'amount_captured_cents', $payment?->amount_captured_cents)],
            MarketplaceEvent::PAYMENT_REFUNDED => $recipient->role === 'caregiver'
                ? ['Payout adjustment', data_get($payload, 'caregiver_refund_cents')]
                : ['Amount returned', data_get($payload, 'amount_refunded_cents', $payment?->amount_refunded_cents)],
            MarketplaceEvent::PAYOUT_TRANSFERRED => ['Payout amount', data_get($payload, 'caregiver_amount_cents', $payment?->caregiver_amount_cents)],
            MarketplaceEvent::PAYOUT_TRANSFER_FAILED => ['Pending payout', $payment?->caregiver_amount_cents],
            MarketplaceEvent::PAYMENT_ACTION_REQUIRED => ['Amount still due', data_get($payload, 'overage_pending_cents', $payment?->overage_pending_cents)],
            default => null,
        };

        if (! $mapping || ! is_numeric($mapping[1]) || (int) $mapping[1] <= 0) {
            return null;
        }

        return $this->detail($mapping[0], $this->moneyFromCents((int) $mapping[1], $currency));
    }

    private function requestTimezone(CareRequest $request): string
    {
        return $request->carePlan?->timezone ?: (string) config('app.timezone', 'America/New_York');
    }

    private function bookingLocation(CareBooking $booking): string
    {
        $snapshot = (array) $booking->carePlan?->address_snapshot;
        $parts = $snapshot !== []
            ? [data_get($snapshot, 'address_line1'), data_get($snapshot, 'address_line2'), data_get($snapshot, 'city'), data_get($snapshot, 'state'), data_get($snapshot, 'zip')]
            : [$booking->careRequest?->address_line1, $booking->careRequest?->address_line2, $booking->careRequest?->city, $booking->careRequest?->state, $booking->careRequest?->zip];

        return collect($parts)->map(fn ($part) => trim((string) $part))->filter()->implode(', ');
    }

    private function approximateLocation(?string $city, ?string $state, ?string $zip): string
    {
        $cityState = collect([$city, $state])->map(fn ($part) => trim((string) $part))->filter()->implode(', ');

        return collect([$cityState, trim((string) $zip)])->filter()->implode(' ');
    }

    private function recurringRequestSchedule(CareRequest $request): string
    {
        return $request->recurringScheduleLabel();
    }

    private function planSchedule(CarePlan $plan): string
    {
        return collect([WeeklySchedule::label($plan->weeklyScheduleSlots()), $plan->timezone])
            ->filter()
            ->implode(' · ');
    }

    private function formatDateTime(?Carbon $date, string $timezone): string
    {
        if (! $date) {
            return '';
        }

        return $date->copy()->setTimezone($timezone)->format('l, F j, Y · g:i A T');
    }

    private function durationBetween(?Carbon $start, ?Carbon $end): string
    {
        if (! $start || ! $end || $end->lte($start)) {
            return '';
        }

        return $this->durationLabel($start->diffInMinutes($end));
    }

    private function durationLabel(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return collect([
            $hours > 0 ? $hours.' '.Str::plural('hour', $hours) : null,
            $remaining > 0 ? $remaining.' '.Str::plural('minute', $remaining) : null,
        ])->filter()->implode(' ') ?: '0 minutes';
    }

    private function budgetLabel(CareRequest $request): string
    {
        if ($request->budget_min !== null && $request->budget_max !== null && (float) $request->budget_min !== (float) $request->budget_max) {
            return $this->moneyFromDecimal($request->budget_min).'–'.$this->moneyFromDecimal($request->budget_max);
        }

        return $this->moneyFromDecimal($request->budget_min ?? $request->budget_max ?? 0);
    }

    private function moneyFromDecimal(mixed $amount): string
    {
        return '$'.number_format((float) $amount, 2);
    }

    private function moneyFromCents(int $cents, string $currency = 'USD'): string
    {
        $amount = number_format($cents / 100, 2);

        return strtoupper($currency) === 'USD' ? '$'.$amount : $amount.' '.strtoupper($currency);
    }

    /** @return array{label:string,value:string} */
    private function detail(string $label, mixed $value): array
    {
        return ['label' => trim($label), 'value' => trim((string) $value)];
    }

    private function firstName(string $name): string
    {
        return (string) Str::of(trim($name))->before(' ');
    }

    private function preheader(string $title, string $body): string
    {
        return Str::limit(trim($title.'. '.$body), 145, '…');
    }
}
