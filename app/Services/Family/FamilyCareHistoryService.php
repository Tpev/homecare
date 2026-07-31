<?php

namespace App\Services\Family;

use App\Models\CareBooking;
use App\Models\CareBookingCorrection;
use App\Models\CareBookingPayment;
use App\Models\CarePlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FamilyCareHistoryService
{
    /**
     * @param  array<string, string>  $filters
     */
    public function query(User $family, array $filters): Builder
    {
        return $this->applyFilters($this->baseQuery($family), $filters)
            ->with([
                'careRequest:id,care_plan_id,title,request_type,status',
                'careRequest.recipient:id,care_request_id,full_name,relationship_to_family',
                'caregiver:id,name',
                'caregiver.caregiverProfile:id,user_id,slug,profile_photo_path',
                'carePlan:id,title',
                'payment:id,care_booking_id,status,currency,amount_authorized_cents,amount_captured_cents,amount_refunded_cents,amount_overage_cents,overage_pending_cents,authorized_at,captured_at',
                'corrections:id,care_booking_id,status,action,applied_at,created_at',
                'taskChecks:id,care_booking_id,label,is_completed,notes',
            ])
            ->orderByDesc('care_bookings.scheduled_start_at')
            ->orderByDesc('care_bookings.id');
    }

    /**
     * @param  array<string, string>  $filters
     * @return array{care_provided:int,worked_minutes:int,money:array<int,array{currency:string,net_billed_cents:int,refunded_cents:int,net_billed_label:string,refunded_label:string}>}
     */
    public function summary(User $family, array $filters): array
    {
        $query = $this->applyFilters($this->baseQuery($family), $filters);

        $careProvided = (clone $query)
            ->reorder()
            ->where('care_bookings.status', '!=', CareBooking::STATUS_CANCELLED)
            ->where(function (Builder $actualCare): void {
                $actualCare
                    ->whereNotNull('care_bookings.completed_at')
                    ->orWhereNotNull('care_bookings.timesheet_submitted_at')
                    ->orWhere('care_bookings.worked_minutes', '>', 0);
            })
            ->count('care_bookings.id');

        $workedMinutes = (int) (clone $query)
            ->reorder()
            ->sum('care_bookings.worked_minutes');

        $money = (clone $query)
            ->reorder()
            ->join('care_booking_payments as history_payments', 'history_payments.care_booking_id', '=', 'care_bookings.id')
            ->selectRaw('LOWER(history_payments.currency) as history_currency')
            ->selectRaw('SUM(CASE WHEN COALESCE(history_payments.amount_captured_cents, 0) > COALESCE(history_payments.amount_refunded_cents, 0) THEN COALESCE(history_payments.amount_captured_cents, 0) - COALESCE(history_payments.amount_refunded_cents, 0) ELSE 0 END) as history_net_billed_cents')
            ->selectRaw('SUM(COALESCE(history_payments.amount_refunded_cents, 0)) as history_refunded_cents')
            ->groupBy('history_payments.currency')
            ->get()
            ->map(function ($row): array {
                $currency = strtoupper((string) ($row->history_currency ?: config('services.stripe.currency', 'usd')));
                $netBilled = max(0, (int) $row->history_net_billed_cents);
                $refunded = max(0, (int) $row->history_refunded_cents);

                return [
                    'currency' => $currency,
                    'net_billed_cents' => $netBilled,
                    'refunded_cents' => $refunded,
                    'net_billed_label' => $this->moneyLabel($netBilled, $currency),
                    'refunded_label' => $this->moneyLabel($refunded, $currency),
                ];
            })
            ->values()
            ->all();

        if ($money === []) {
            $currency = strtoupper((string) config('services.stripe.currency', 'usd'));
            $money[] = [
                'currency' => $currency,
                'net_billed_cents' => 0,
                'refunded_cents' => 0,
                'net_billed_label' => $this->moneyLabel(0, $currency),
                'refunded_label' => $this->moneyLabel(0, $currency),
            ];
        }

        return [
            'care_provided' => $careProvided,
            'worked_minutes' => $workedMinutes,
            'money' => $money,
        ];
    }

    /**
     * @return array{caregivers:Collection<int, array{label:string,value:string}>,recipients:Collection<int, array{label:string,value:string}>,plans:Collection<int, array{label:string,value:string}>}
     */
    public function filterOptions(User $family): array
    {
        $history = $this->baseQuery($family);

        $caregivers = User::query()
            ->whereIn('id', (clone $history)->reorder()->select('care_bookings.caregiver_user_id'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $caregiver): array => [
                'label' => $caregiver->name,
                'value' => (string) $caregiver->id,
            ]);

        $recipients = (clone $history)
            ->reorder()
            ->join('care_requests as history_requests', 'history_requests.id', '=', 'care_bookings.care_request_id')
            ->join('care_request_recipients as history_recipients', 'history_recipients.care_request_id', '=', 'history_requests.id')
            ->whereNotNull('history_recipients.full_name')
            ->where('history_recipients.full_name', '!=', '')
            ->select('history_recipients.full_name')
            ->distinct()
            ->orderBy('history_recipients.full_name')
            ->pluck('history_recipients.full_name')
            ->map(fn (string $name): array => ['label' => $name, 'value' => $name])
            ->values();

        $plans = CarePlan::query()
            ->where('family_user_id', $family->id)
            ->whereIn('id', (clone $history)->reorder()->whereNotNull('care_bookings.care_plan_id')->select('care_bookings.care_plan_id'))
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn (CarePlan $plan): array => [
                'label' => $plan->title,
                'value' => (string) $plan->id,
            ]);

        return compact('caregivers', 'recipients', 'plans');
    }

    /**
     * @return array<string, mixed>
     */
    public function present(CareBooking $booking): array
    {
        $payment = $this->paymentDetails($booking->payment);
        $visit = $this->visitDetails($booking);
        $careType = match (true) {
            $booking->care_plan_id === null => ['key' => 'one_time', 'label' => 'One-time'],
            $booking->plan_visit_kind === 'extra' => ['key' => 'extra', 'label' => 'Extra visit'],
            default => ['key' => 'regular', 'label' => 'Regular care'],
        };
        $workedMinutes = is_null($booking->worked_minutes) ? null : max(0, (int) $booking->worked_minutes);
        $scheduledMinutes = $booking->scheduled_start_at && $booking->scheduled_end_at
            ? max(0, (int) $booking->scheduled_start_at->diffInMinutes($booking->scheduled_end_at, false))
            : null;
        $taskTotal = $booking->taskChecks->count();
        $taskCompleted = $booking->taskChecks->where('is_completed', true)->count();

        $action = match (true) {
            $visit['key'] === 'check_in_missing' => ['label' => 'Report completed work', 'tab' => 'support'],
            $visit['key'] === 'awaiting_approval' => ['label' => 'Review hours', 'tab' => 'shift'],
            $visit['key'] === 'disputed' => ['label' => 'Open support review', 'tab' => 'support'],
            $payment['key'] === 'payment_issue' => ['label' => 'Get payment help', 'tab' => 'support'],
            $payment['gross_cents'] > 0 || in_array($booking->status, [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED], true) => ['label' => 'View receipt', 'tab' => 'shift'],
            default => ['label' => 'Open visit record', 'tab' => 'shift'],
        };

        return [
            'booking_id' => $booking->id,
            'care_request_id' => $booking->care_request_id,
            'care_plan_id' => $booking->care_plan_id,
            'plan_title' => $booking->carePlan?->title,
            'request_title' => $booking->careRequest?->title ?: 'Care visit #'.$booking->id,
            'recipient_name' => $booking->careRequest?->recipient?->full_name ?: 'Care recipient',
            'recipient_relationship' => $booking->careRequest?->recipient?->relationship_to_family,
            'caregiver_name' => $booking->caregiver?->name ?: 'Caregiver',
            'caregiver_profile_url' => $booking->caregiver?->caregiverProfile?->slug
                ? route('caregivers.show', $booking->caregiver->caregiverProfile->slug)
                : null,
            'care_type_key' => $careType['key'],
            'care_type_label' => $careType['label'],
            'visit_status_key' => $visit['key'],
            'visit_status_label' => $visit['label'],
            'visit_status_help' => $visit['help'],
            'adjusted' => $visit['adjusted'],
            'reference_at' => $booking->scheduled_start_at ?: $booking->completed_at ?: $booking->cancelled_at ?: $booking->created_at,
            'scheduled_start_at' => $booking->scheduled_start_at,
            'scheduled_end_at' => $booking->scheduled_end_at,
            'started_at' => $booking->started_at,
            'completed_at' => $booking->completed_at,
            'worked_minutes' => $workedMinutes,
            'worked_label' => is_null($workedMinutes) ? null : $this->durationLabel($workedMinutes),
            'scheduled_minutes' => $scheduledMinutes,
            'scheduled_duration_label' => is_null($scheduledMinutes) ? null : $this->durationLabel($scheduledMinutes),
            'break_label' => $this->durationLabel((int) floor(max(0, (int) $booking->total_paused_seconds) / 60)),
            'task_total' => $taskTotal,
            'task_completed' => $taskCompleted,
            'task_checks' => $booking->taskChecks,
            'payment' => $payment,
            'action_label' => $action['label'],
            'action_url' => route('family.requests.show', [
                'careRequest' => $booking->care_request_id,
                'tab' => $action['tab'],
            ]),
        ];
    }

    private function baseQuery(User $family): Builder
    {
        $expiredCheckInCutoff = now()->subMinutes(CareBooking::regularCareCheckInGraceMinutes());

        return CareBooking::query()
            ->where('care_bookings.family_user_id', $family->id)
            ->whereNotIn('care_bookings.status', [CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED])
            ->where(function (Builder $history) use ($expiredCheckInCutoff): void {
                $history
                    ->where(function (Builder $closedBooking): void {
                        $closedBooking
                            ->where('care_bookings.status', '!=', CareBooking::STATUS_SCHEDULED)
                            ->where('care_bookings.scheduled_start_at', '<', now());
                    })
                    ->orWhere(function (Builder $expiredScheduled) use ($expiredCheckInCutoff): void {
                        $expiredScheduled
                            ->where('care_bookings.status', CareBooking::STATUS_SCHEDULED)
                            ->where('care_bookings.scheduled_start_at', '<', $expiredCheckInCutoff);
                    })
                    ->orWhere(function (Builder $withoutSchedule): void {
                        $withoutSchedule
                            ->whereNull('care_bookings.scheduled_start_at')
                            ->where(function (Builder $closed): void {
                                $closed
                                    ->whereNotNull('care_bookings.completed_at')
                                    ->orWhereNotNull('care_bookings.timesheet_submitted_at')
                                    ->orWhereNotNull('care_bookings.cancelled_at')
                                    ->orWhere('care_bookings.status', CareBooking::STATUS_DISPUTED);
                            });
                    });
            });
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        [$from, $to] = $this->dateBounds($filters);
        if ($from) {
            $query->where(function (Builder $dateQuery) use ($from): void {
                $dateQuery
                    ->where('care_bookings.scheduled_start_at', '>=', $from)
                    ->orWhere(function (Builder $fallback) use ($from): void {
                        $fallback->whereNull('care_bookings.scheduled_start_at')->where('care_bookings.created_at', '>=', $from);
                    });
            });
        }
        if ($to) {
            $query->where(function (Builder $dateQuery) use ($to): void {
                $dateQuery
                    ->where('care_bookings.scheduled_start_at', '<=', $to)
                    ->orWhere(function (Builder $fallback) use ($to): void {
                        $fallback->whereNull('care_bookings.scheduled_start_at')->where('care_bookings.created_at', '<=', $to);
                    });
            });
        }

        if (($filters['caregiver'] ?? '') !== '') {
            $query->where('care_bookings.caregiver_user_id', (int) $filters['caregiver']);
        }
        if (($filters['plan'] ?? '') !== '') {
            $query->where('care_bookings.care_plan_id', (int) $filters['plan']);
        }
        if (($filters['recipient'] ?? '') !== '') {
            $recipient = $filters['recipient'];
            $query->whereHas('careRequest.recipient', fn (Builder $recipientQuery) => $recipientQuery->where('full_name', $recipient));
        }

        match ($filters['type'] ?? 'all') {
            'one_time' => $query->whereNull('care_bookings.care_plan_id'),
            'regular' => $query->whereNotNull('care_bookings.care_plan_id')->where(function (Builder $type): void {
                $type->whereNull('care_bookings.plan_visit_kind')->orWhere('care_bookings.plan_visit_kind', '!=', 'extra');
            }),
            'extra' => $query->where('care_bookings.plan_visit_kind', 'extra'),
            default => null,
        };

        $this->applyVisitStatusFilter($query, $filters['visit'] ?? 'all');
        $this->applyPaymentStatusFilter($query, $filters['payment'] ?? 'all');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $numeric = ltrim($search, '#');
            $query->where(function (Builder $searchQuery) use ($like, $numeric): void {
                if (ctype_digit($numeric)) {
                    $searchQuery->orWhere('care_bookings.id', (int) $numeric);
                }
                $searchQuery
                    ->orWhereHas('caregiver', fn (Builder $caregiver) => $caregiver->where('name', 'like', $like))
                    ->orWhereHas('careRequest', fn (Builder $request) => $request->where('title', 'like', $like))
                    ->orWhereHas('careRequest.recipient', fn (Builder $recipient) => $recipient->where('full_name', 'like', $like));
            });
        }

        return $query;
    }

    private function applyVisitStatusFilter(Builder $query, string $status): void
    {
        match ($status) {
            'completed' => $query->whereIn('care_bookings.status', [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED])->whereNotNull('care_bookings.family_confirmed_at'),
            'awaiting_approval' => $query->whereIn('care_bookings.status', [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED])->whereNull('care_bookings.family_confirmed_at'),
            'disputed' => $query->where('care_bookings.status', CareBooking::STATUS_DISPUTED),
            'cancelled' => $query->where('care_bookings.status', CareBooking::STATUS_CANCELLED)->where('care_bookings.no_show_flag', false),
            'no_show' => $query->where('care_bookings.status', CareBooking::STATUS_CANCELLED)->where('care_bookings.no_show_flag', true),
            'check_in_missing' => $query->where('care_bookings.status', CareBooking::STATUS_SCHEDULED),
            'adjusted' => $query->whereHas('corrections', fn (Builder $corrections) => $corrections->where('status', CareBookingCorrection::STATUS_SUCCEEDED)),
            default => null,
        };
    }

    private function applyPaymentStatusFilter(Builder $query, string $status): void
    {
        match ($status) {
            'charged' => $query->whereHas('payment', fn (Builder $payment) => $payment->where('amount_captured_cents', '>', 0)),
            'paid' => $query->whereHas('payment', fn (Builder $payment) => $payment->whereIn('status', [
                CareBookingPayment::STATUS_CAPTURED,
                CareBookingPayment::STATUS_TRANSFERRED,
                CareBookingPayment::STATUS_TRANSFER_FAILED,
            ])),
            'authorized' => $query->whereHas('payment', fn (Builder $payment) => $payment->where('status', CareBookingPayment::STATUS_AUTHORIZED)),
            'payment_issue' => $query->whereHas('payment', fn (Builder $payment) => $payment->whereIn('status', [
                CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED,
                CareBookingPayment::STATUS_REAUTH_REQUIRED,
                CareBookingPayment::STATUS_FAILED,
            ])),
            'partially_refunded' => $query->whereHas('payment', fn (Builder $payment) => $payment->where('status', CareBookingPayment::STATUS_PARTIALLY_REFUNDED)),
            'refunded' => $query->whereHas('payment', fn (Builder $payment) => $payment->where('status', CareBookingPayment::STATUS_REFUNDED)),
            'not_charged' => $query->where(function (Builder $paymentQuery): void {
                $paymentQuery
                    ->whereDoesntHave('payment')
                    ->orWhereHas('payment', fn (Builder $payment) => $payment->whereIn('status', [
                        CareBookingPayment::STATUS_DRAFT,
                        CareBookingPayment::STATUS_CANCELLED,
                    ]));
            }),
            default => null,
        };
    }

    /**
     * @param  array<string, string>  $filters
     * @return array{0:?Carbon,1:?Carbon}
     */
    private function dateBounds(array $filters): array
    {
        $range = $filters['range'] ?? 'all';

        return match ($range) {
            '30_days' => [now()->subDays(30)->startOfDay(), now()->endOfDay()],
            '3_months' => [now()->subMonths(3)->startOfDay(), now()->endOfDay()],
            'year' => [now()->subYear()->startOfDay(), now()->endOfDay()],
            'custom' => [
                $this->safeDate($filters['from'] ?? '', false),
                $this->safeDate($filters['to'] ?? '', true),
            ],
            default => [null, null],
        };
    }

    private function safeDate(string $date, bool $endOfDay): ?Carbon
    {
        if ($date === '') {
            return null;
        }

        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $date);
        } catch (\Throwable) {
            return null;
        }

        return $endOfDay ? $parsed->endOfDay() : $parsed->startOfDay();
    }

    /**
     * @return array{key:string,label:string,help:string,adjusted:bool}
     */
    private function visitDetails(CareBooking $booking): array
    {
        $adjusted = $booking->corrections->contains('status', CareBookingCorrection::STATUS_SUCCEEDED);

        if ($booking->status === CareBooking::STATUS_CANCELLED && $booking->no_show_flag) {
            return ['key' => 'no_show', 'label' => 'Caregiver no-show', 'help' => 'This visit was closed as a caregiver no-show.', 'adjusted' => $adjusted];
        }
        if ($booking->status === CareBooking::STATUS_CANCELLED) {
            return ['key' => 'cancelled', 'label' => 'Cancelled', 'help' => 'This visit was cancelled.', 'adjusted' => $adjusted];
        }
        if ($booking->status === CareBooking::STATUS_DISPUTED) {
            return ['key' => 'disputed', 'label' => 'Disputed', 'help' => 'LoLo support is reviewing this visit.', 'adjusted' => $adjusted];
        }
        if ($booking->status === CareBooking::STATUS_SCHEDULED && $booking->scheduled_start_at?->isPast()) {
            return ['key' => 'check_in_missing', 'label' => 'Check-in missing', 'help' => 'If care was provided, report the completed work from this exact visit.', 'adjusted' => $adjusted];
        }
        if (in_array($booking->status, [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED], true) && ! $booking->family_confirmed_at) {
            return ['key' => 'awaiting_approval', 'label' => 'Awaiting hours approval', 'help' => 'Review the caregiver hours before payment is finalized.', 'adjusted' => $adjusted];
        }
        if (in_array($booking->status, [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED], true)) {
            return ['key' => 'completed', 'label' => 'Completed', 'help' => 'The final care record is available.', 'adjusted' => $adjusted];
        }

        return [
            'key' => (string) $booking->status,
            'label' => ucfirst(str_replace('_', ' ', (string) $booking->status)),
            'help' => 'Open the visit record for details.',
            'adjusted' => $adjusted,
        ];
    }

    /**
     * @return array{key:string,label:string,help:string,tone:string,currency:string,gross_cents:int,refunded_cents:int,net_cents:int,overage_cents:int,gross_label:string,refunded_label:string,net_label:string,overage_label:string,amount_label:string,captured_at:?Carbon,authorized_at:?Carbon}
     */
    private function paymentDetails(?CareBookingPayment $payment): array
    {
        $currency = strtoupper((string) ($payment?->currency ?: config('services.stripe.currency', 'usd')));
        $gross = max(0, (int) ($payment?->amount_captured_cents ?? 0));
        $refunded = max(0, (int) ($payment?->amount_refunded_cents ?? 0));
        $net = max(0, $gross - $refunded);
        $overage = max(0, (int) ($payment?->amount_overage_cents ?? 0));

        [$key, $label, $help, $tone] = match ($payment?->status) {
            CareBookingPayment::STATUS_AUTHORIZED => ['authorized', 'Card authorized', 'Card authorized — not charged yet.', 'blue'],
            CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED,
            CareBookingPayment::STATUS_REAUTH_REQUIRED,
            CareBookingPayment::STATUS_FAILED => ['payment_issue', 'Payment issue', 'The payment method needs attention.', 'amber'],
            CareBookingPayment::STATUS_CAPTURED,
            CareBookingPayment::STATUS_TRANSFERRED => ['paid', 'Paid', 'Payment was captured for this visit.', 'green'],
            CareBookingPayment::STATUS_TRANSFER_FAILED => ['paid', 'Paid — payout processing', 'Your payment succeeded. No action is needed from you.', 'green'],
            CareBookingPayment::STATUS_PARTIALLY_REFUNDED => ['partially_refunded', 'Partially refunded', 'Part of the captured payment was refunded.', 'amber'],
            CareBookingPayment::STATUS_REFUNDED => ['refunded', 'Refunded', 'The captured payment was refunded.', 'slate'],
            default => ['not_charged', 'Not charged', 'No payment was captured for this visit.', 'slate'],
        };

        return [
            'key' => $key,
            'label' => $label,
            'help' => $help,
            'tone' => $tone,
            'currency' => $currency,
            'gross_cents' => $gross,
            'refunded_cents' => $refunded,
            'net_cents' => $net,
            'overage_cents' => $overage,
            'gross_label' => $this->moneyLabel($gross, $currency),
            'refunded_label' => $this->moneyLabel($refunded, $currency),
            'net_label' => $this->moneyLabel($net, $currency),
            'overage_label' => $this->moneyLabel($overage, $currency),
            'amount_label' => match ($key) {
                'paid' => $this->moneyLabel($net, $currency),
                'partially_refunded' => 'Net '.$this->moneyLabel($net, $currency),
                'refunded' => $this->moneyLabel($refunded, $currency).' refunded',
                default => 'Not charged',
            },
            'captured_at' => $payment?->captured_at,
            'authorized_at' => $payment?->authorized_at,
        ];
    }

    private function moneyLabel(int $cents, string $currency): string
    {
        $amount = number_format(max(0, $cents) / 100, 2);

        return match (strtoupper($currency)) {
            'USD' => '$'.$amount,
            'EUR' => '€'.$amount,
            'GBP' => '£'.$amount,
            default => strtoupper($currency).' '.$amount,
        };
    }

    private function durationLabel(int $minutes): string
    {
        $minutes = max(0, $minutes);

        return intdiv($minutes, 60).'h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).'m';
    }
}
