<?php

namespace App\Livewire\Family;

use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Support\FamilyActionInboxBuilder;
use App\Support\WeeklySchedule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CareJourney extends Component
{
    public string $resourceType;

    public int $resourceId;

    public function mount(string $resourceType, int $resourceId): void
    {
        abort_unless(auth()->user()?->role === 'family', 403);
        abort_unless(in_array($resourceType, ['request', 'regular'], true), 404);

        $this->resourceType = $resourceType;
        $this->resourceId = $resourceId;
    }

    public function render(FamilyActionInboxBuilder $actionInbox)
    {
        $account = app(FamilyAccountContext::class)->account(auth()->user());
        $journey = $this->resourceType === 'regular'
            ? $this->regularCareJourney($account)
            : $this->requestJourney($account);

        $action = $actionInbox->buildForAccount($account)
            ->first(fn (array $item): bool => ($item['resource_type'] ?? null) === ($this->resourceType === 'regular' ? 'care_plan' : 'care_request')
                && (int) ($item['resource_id'] ?? 0) === $this->resourceId);

        if ($action) {
            $journey['primary_action'] = [
                'label' => $action['label'],
                'url' => $action['href'],
                'consequence' => $this->actionConsequence((string) ($action['type'] ?? '')),
                'urgent' => true,
            ];
        }

        return view('livewire.family.care-journey', ['journey' => $journey]);
    }

    private function requestJourney(mixed $account): array
    {
        $request = CareRequest::query()
            ->with([
                'recipient',
                'tasks:id,name',
                'applications.caregiver:id,name',
                'booking.caregiver:id,name',
                'booking.payment:id,care_booking_id,status,amount_authorized_cents,amount_captured_cents,authorized_at,captured_at,last_error',
                'carePlan:id,source_care_request_id,status,recipient_snapshot,caregiver_user_id',
            ])
            ->forFamilyAccount($account)
            ->findOrFail($this->resourceId);

        $hired = $request->applications->firstWhere('status', CareRequestApplication::STATUS_HIRED);
        $booking = $request->booking;
        $payment = $booking?->payment;
        $recipient = trim((string) ($request->recipient?->full_name ?? '')) ?: 'Care recipient';
        $caregiver = trim((string) ($hired?->caregiver?->name ?? $booking?->caregiver?->name ?? '')) ?: null;
        $requestType = $request->request_type === CareRequest::TYPE_RECURRING ? 'Recurring care request' : 'One-time care';

        $visits = collect($booking ? [$this->visitCard($booking)] : []);
        $timeline = collect([
            $this->stage('Care requested', 'complete', $requestType.' created for '.$recipient.'.', $request->created_at),
            $this->stage(
                'Caregiver selected',
                $hired || $booking ? 'complete' : ($request->status === CareRequest::STATUS_OPEN ? 'current' : 'pending'),
                $caregiver ? $caregiver.' was selected.' : 'Waiting for the family to choose a caregiver.',
                $request->first_hire_at,
            ),
            $this->stage(
                'Visit scheduled',
                $booking ? 'complete' : ($request->status === CareRequest::STATUS_OPEN ? 'pending' : 'current'),
                $booking?->scheduled_start_at
                    ? $booking->scheduled_start_at->format('l, F j · g:i A').'–'.$booking->scheduled_end_at?->format('g:i A')
                    : 'A confirmed date will appear here after a caregiver is selected.',
                $booking?->created_at,
            ),
            $this->deliveryStage($booking),
            $this->paymentStage($payment),
        ]);

        return [
            'kind' => 'request',
            'type_label' => $requestType,
            'title' => $recipient.($caregiver ? ' with '.$caregiver : ''),
            'subtitle' => $request->request_type === CareRequest::TYPE_RECURRING
                ? ($request->recurringScheduleLabel() ?: 'Regular schedule being arranged')
                : $this->dateTime($request->requested_start_at, $request->requested_end_at),
            'status' => ucfirst(str_replace('_', ' ', (string) $request->status)),
            'status_tone' => in_array($request->status, [CareRequest::STATUS_CANCELLED, CareRequest::STATUS_EXPIRED], true) ? 'slate' : 'green',
            'recipient' => $recipient,
            'caregiver' => $caregiver ?: 'Not selected yet',
            'reference' => 'Request #'.$request->id,
            'schedule' => $request->request_type === CareRequest::TYPE_RECURRING
                ? ($request->recurringScheduleLabel() ?: 'Schedule pending')
                : $this->dateTime($request->requested_start_at, $request->requested_end_at),
            'timeline' => $timeline,
            'visits' => $visits,
            'tasks' => $request->tasks->pluck('name')->values(),
            'manage_url' => route('family.requests.show', $request->id),
            'primary_action' => [
                'label' => $booking ? 'Open visit details' : 'Manage request',
                'url' => route('family.requests.show', $request->id),
                'consequence' => $booking
                    ? 'See the visit, messages, submitted hours, and payment in one place.'
                    : 'Review replies and move this request toward confirmed care.',
                'urgent' => false,
            ],
        ];
    }

    private function regularCareJourney(mixed $account): array
    {
        $plan = CarePlan::query()
            ->with([
                'caregiver:id,name',
                'sourceCareRequest:id,created_at,title',
            ])
            ->forFamilyAccount($account)
            ->findOrFail($this->resourceId);

        $bookingRelations = [
            'caregiver:id,name',
            'payment:id,care_booking_id,status,amount_authorized_cents,amount_captured_cents,authorized_at,captured_at,last_error',
        ];
        $canHaveUpcomingVisits = in_array($plan->status, [
            CarePlan::STATUS_ACTIVE,
            CarePlan::STATUS_PAYMENT_ATTENTION,
            CarePlan::STATUS_PAUSED,
        ], true);
        $upcomingBookings = $canHaveUpcomingVisits
            ? CareBooking::query()
                ->with($bookingRelations)
                ->forFamilyAccount($account)
                ->where('care_plan_id', $plan->id)
                ->whereIn('status', [CareBooking::STATUS_SCHEDULED, CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED])
                ->where('scheduled_start_at', '>=', now()->subMinutes(CareBooking::regularCareCheckInGraceMinutes()))
                ->orderBy('scheduled_start_at')
                ->limit(12)
                ->get()
            : collect();
        $recentBookings = CareBooking::query()
            ->with($bookingRelations)
            ->forFamilyAccount($account)
            ->where('care_plan_id', $plan->id)
            ->whereIn('status', [
                CareBooking::STATUS_COMPLETED,
                CareBooking::STATUS_REVIEWED,
                CareBooking::STATUS_DISPUTED,
                CareBooking::STATUS_CANCELLED,
            ])
            ->orderByDesc('scheduled_start_at')
            ->limit(6)
            ->get();
        $next = $upcomingBookings->first();
        $lastDelivered = $recentBookings->first(fn (CareBooking $booking): bool => in_array($booking->status, [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED], true));
        $visibleBookings = $upcomingBookings->take(4)
            ->concat($recentBookings->take(2))
            ->unique('id')
            ->values();
        $caregiver = trim((string) ($plan->caregiver?->name ?? '')) ?: 'Caregiver pending';
        $active = in_array($plan->status, [CarePlan::STATUS_ACTIVE, CarePlan::STATUS_PAYMENT_ATTENTION, CarePlan::STATUS_PAUSED], true);

        return [
            'kind' => 'regular',
            'type_label' => 'Recurring care',
            'title' => $plan->recipientName().' with '.$caregiver,
            'subtitle' => WeeklySchedule::label($plan->weeklyScheduleSlots()) ?: 'Schedule pending',
            'status' => match ((string) $plan->status) {
                CarePlan::STATUS_PAYMENT_ATTENTION => 'Payment needs attention',
                CarePlan::STATUS_PENDING_CAREGIVER => 'Waiting for caregiver',
                default => ucfirst(str_replace('_', ' ', (string) $plan->status)),
            },
            'status_tone' => $plan->status === CarePlan::STATUS_PAYMENT_ATTENTION ? 'amber' : ($active ? 'green' : 'slate'),
            'recipient' => $plan->recipientName(),
            'caregiver' => $caregiver,
            'reference' => 'Recurring care #'.$plan->id,
            'schedule' => WeeklySchedule::label($plan->weeklyScheduleSlots()) ?: 'Schedule pending',
            'timeline' => collect([
                $this->stage('Care proposed', 'complete', 'The family created this recurring care arrangement.', $plan->offered_at ?: $plan->created_at),
                $this->stage(
                    'Caregiver confirmed',
                    $plan->accepted_at || $active ? 'complete' : 'current',
                    $plan->accepted_at || $active ? $caregiver.' accepted the schedule.' : 'Waiting for '.$caregiver.' to respond.',
                    $plan->accepted_at,
                ),
                $this->stage(
                    'Recurring care active',
                    $active ? 'complete' : ($plan->status === CarePlan::STATUS_PENDING_CAREGIVER ? 'pending' : 'current'),
                    $active ? 'Future visits follow the agreed weekly schedule.' : 'The arrangement is not currently generating visits.',
                    $plan->activated_at,
                ),
                $this->stage(
                    'Next visit',
                    $next ? 'current' : 'pending',
                    $next?->scheduled_start_at
                        ? $next->scheduled_start_at->format('l, F j · g:i A').'–'.$next->scheduled_end_at?->format('g:i A')
                        : 'No upcoming visit is currently confirmed.',
                    $next?->scheduled_start_at,
                ),
                $this->stage(
                    'Latest completed visit',
                    $lastDelivered ? 'complete' : 'pending',
                    $lastDelivered?->scheduled_start_at
                        ? $lastDelivered->scheduled_start_at->format('l, F j').' · '.ucfirst(str_replace('_', ' ', $lastDelivered->status))
                        : 'Completed visits will build the care history here.',
                    $lastDelivered?->family_confirmed_at ?: $lastDelivered?->completed_at,
                ),
            ]),
            'visits' => $visibleBookings->map(fn (CareBooking $booking): array => $this->visitCard($booking))->values(),
            'tasks' => collect($plan->task_snapshot)
                ->map(fn (mixed $task): ?string => is_array($task) ? ($task['name'] ?? null) : (is_string($task) ? $task : null))
                ->filter()
                ->values(),
            'manage_url' => route('family.care.show', $plan->id),
            'primary_action' => [
                'label' => 'Manage recurring care',
                'url' => route('family.care.show', $plan->id),
                'consequence' => 'Change future visits, add an extra visit, pause care, or review payment.',
                'urgent' => false,
            ],
        ];
    }

    private function deliveryStage(?CareBooking $booking): array
    {
        if (! $booking) {
            return $this->stage('Care delivered', 'pending', 'Visit completion and submitted hours will appear here.');
        }

        $complete = in_array($booking->status, [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED], true);
        $live = in_array($booking->status, [CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED], true);

        return $this->stage(
            'Care delivered',
            $booking->family_confirmed_at ? 'complete' : (($complete || $live) ? 'current' : 'pending'),
            $booking->family_confirmed_at
                ? 'The family approved the submitted visit hours.'
                : ($complete ? 'The caregiver submitted hours for family review.' : ($live ? 'The visit is happening now.' : 'Waiting for the scheduled visit.')),
            $booking->family_confirmed_at ?: $booking->completed_at,
        );
    }

    private function paymentStage(mixed $payment): array
    {
        if (! $payment) {
            return $this->stage('Payment', 'pending', 'Payment details appear after a visit is confirmed.');
        }

        $settled = in_array($payment->status, [CareBookingPayment::STATUS_CAPTURED, CareBookingPayment::STATUS_TRANSFERRED], true);
        $needsAction = $payment->requiresFamilyAction();

        return $this->stage(
            'Payment',
            $settled ? 'complete' : ($needsAction ? 'current' : 'pending'),
            match (true) {
                $settled => 'Payment completed'.($payment->amount_captured_cents ? ' · $'.number_format($payment->amount_captured_cents / 100, 2) : '').'.',
                $needsAction => 'Family payment confirmation is required before this can finish.',
                $payment->status === CareBookingPayment::STATUS_AUTHORIZED => 'Payment is authorized and will finalize after approved hours.',
                default => 'Payment is being prepared safely.',
            },
            $payment->captured_at ?: $payment->authorized_at,
        );
    }

    private function visitCard(CareBooking $booking): array
    {
        $payment = $booking->payment;

        return [
            'id' => $booking->id,
            'date' => $booking->scheduled_start_at?->format('D, M j, Y') ?: 'Date pending',
            'time' => $this->dateTime($booking->scheduled_start_at, $booking->scheduled_end_at, false),
            'status' => ucfirst(str_replace('_', ' ', (string) $booking->status)),
            'payment' => $payment ? ucfirst(str_replace('_', ' ', (string) $payment->status)) : 'Payment not started',
            'caregiver' => $booking->caregiver?->name ?: 'Caregiver pending',
            'url' => $booking->care_request_id ? route('family.requests.show', $booking->care_request_id) : null,
        ];
    }

    private function stage(string $label, string $state, string $detail, mixed $at = null): array
    {
        return compact('label', 'state', 'detail', 'at');
    }

    private function dateTime(mixed $start, mixed $end, bool $includeDate = true): string
    {
        if (! $start) {
            return 'Date and time pending';
        }

        $label = $includeDate ? $start->format('l, F j · ') : '';

        return $label.$start->format('g:i A').($end ? '–'.$end->format('g:i A') : '');
    }

    private function actionConsequence(string $type): string
    {
        return match ($type) {
            'payment' => 'Complete this now so the visit remains financially protected and care can continue.',
            'time_correction', 'timesheet' => 'The caregiver’s earnings and final family charge wait for this review.',
            'completed_extra_visit' => 'Confirm the reported visit before any charge and caregiver earnings are finalized.',
            'applicants' => 'Choose a caregiver before the requested care can become a confirmed visit.',
            'request_date_passed' => 'Close the old request or choose a new date so it no longer remains unresolved.',
            'visit_change' => 'The current visit stays unchanged until you decide.',
            default => 'Review this item to keep the care record accurate and moving forward.',
        };
    }
}
