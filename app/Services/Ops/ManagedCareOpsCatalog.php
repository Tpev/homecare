<?php

namespace App\Services\Ops;

use App\Models as M;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ManagedCareOpsCatalog
{
    /** Only meaningful fields trigger events; free text is never copied into Slack. */
    public static function definitions(): array
    {
        return [
            M\CareRequest::class => ['request', ['status', 'staff_managed', 'staff_archived_at', 'title', 'scope_of_work', 'home_access_notes', 'address_line1', 'address_line2', 'city', 'state', 'zip', 'requested_start_at', 'requested_end_at', 'recurring_schedule', 'recurring_days', 'recurring_start_time', 'recurring_end_time', 'recurring_starts_on', 'recurring_ends_on']],
            M\CareRequestApplication::class => ['application', ['status', 'offer_version', 'selected_days', 'offered_schedule', 'proposed_rate', 'cover_note']],
            M\CareRequestInvitation::class => ['invitation', ['status']],
            M\CareRequestMessage::class => ['message', ['body', 'edited_at']],
            M\CareBooking::class => ['booking', ['status', 'scheduled_start_at', 'scheduled_end_at', 'caregiver_user_id', 'started_at', 'completed_at', 'paused_at', 'worked_minutes', 'timesheet_submitted_at', 'family_confirmed_at', 'no_show_flag', 'dispute_status', 'replacement_released_at']],
            M\CareBookingChangeRequest::class => ['visit_change', ['status', 'proposed_start_at', 'proposed_end_at']],
            M\CareBookingTimeCorrection::class => ['time_correction', ['status']],
            M\CareBookingIncident::class => ['incident', ['status', 'severity']],
            M\CarePlan::class => ['plan', ['status', 'schedule_days', 'schedule_start_time', 'schedule_end_time', 'schedule_slots', 'starts_on', 'ends_on', 'pause_starts_on', 'resumes_on', 'payment_status', 'counter_schedule_days', 'counter_schedule_slots', 'counter_schedule_start_time', 'counter_schedule_end_time', 'counter_starts_on']],
            M\CarePlanScheduleChange::class => ['plan_change', ['status']],
            M\CompletedExtraVisitRequest::class => ['extra_visit', ['status']],
            M\CareBookingPayment::class => ['payment', ['status', 'amount_refunded_cents', 'amount_captured_cents', 'amount_authorized_cents']],
            M\CareHourBookingFunding::class => ['care_hours', ['status', 'reserved_minutes', 'shortfall_minutes']],
            M\CareHourSettlement::class => ['settlement', ['status', 'prepaid_minutes', 'card_minutes', 'worked_minutes']],
            M\CareHourPaymentOperation::class => ['care_hours_payment', ['status']],
            M\SupportTicket::class => ['support', ['status', 'priority']],
            M\SupportTicketMessage::class => ['support_message', ['body']],
        ];
    }

    public function describe(Model $model, bool $created, array $changed, array $context): array
    {
        $kind = self::definitions()[$model::class][0];
        $status = (string) $model->getAttribute('status');
        $state = $this->label($status);
        $title = match ($kind) {
            'request' => $created ? ($status === 'draft' ? 'Managed request drafted' : 'Managed request posted') : match (true) {
                in_array('staff_managed', $changed) => 'Team management started',
                in_array('staff_archived_at', $changed) => $model->staff_archived_at ? 'Request archived' : 'Request restored',
                in_array('status', $changed) => match ($status) {
                    'open' => 'Request posted', 'cancelled' => 'Request withdrawn', 'filled' => 'Request filled', default => 'Request '.$state,
                },
                default => 'Request details updated',
            },
            'application' => $created ? 'New applicant' : ($model->wasChanged('status') ? match ($status) {
                'hired' => 'Caregiver hired', 'shortlisted' => 'Applicant shortlisted', 'rejected' => 'Applicant declined',
                'withdrawn' => 'Application withdrawn', 'not_selected' => 'Applicant not selected', default => 'Application '.$state,
            } : 'Application updated'),
            'invitation' => $created ? 'Caregiver invited' : 'Invitation '.$state,
            'message' => $created ? (($model->sender?->role === 'caregiver') ? 'Caregiver message received' : 'Team or family message sent') : 'Conversation message edited',
            'booking' => $created ? 'Booking created' : match (true) {
                $model->wasChanged('no_show_flag') && $model->no_show_flag => 'Caregiver no-show reported',
                $model->wasChanged('dispute_status') => 'Visit dispute '.$this->label((string) $model->dispute_status),
                $model->wasChanged('family_confirmed_at') && $model->family_confirmed_at !== null => 'Visit hours approved',
                $model->wasChanged(['scheduled_start_at', 'scheduled_end_at']) => 'Visit rescheduled',
                $model->wasChanged('status') => match ($status) {
                    'in_progress' => $model->getRawOriginal('status') === 'paused' ? 'Visit resumed' : 'Caregiver checked in',
                    'paused' => 'Visit paused', 'completed' => 'Visit completed · Hours ready for review',
                    'cancelled' => 'Visit cancelled or skipped', default => 'Visit '.$state,
                },
                $model->wasChanged('timesheet_submitted_at') => 'Visit hours submitted',
                $model->wasChanged('replacement_released_at') => 'Visit released for replacement',
                default => 'Visit updated',
            },
            'visit_change' => ($model->type === 'cancel' ? 'Cancellation request' : 'Reschedule request').' '.($created ? 'received' : $state),
            'time_correction' => 'Time correction '.($created ? ($model->supersedes_id ? 'resubmitted' : 'submitted') : $state),
            'incident' => $created ? 'Incident reported' : 'Incident '.$state,
            'plan' => $created ? 'Recurring care created' : ($model->wasChanged('status') ? 'Recurring care '.$state : ($model->wasChanged('payment_status') ? 'Recurring care payment · '.$this->label((string) $model->payment_status) : 'Recurring schedule updated')),
            'plan_change' => ($model->type === 'extra_visit' ? 'Extra visit request' : 'Recurring schedule change').' '.($created ? 'received' : $state),
            'extra_visit' => 'Reported extra care '.($created ? ($model->supersedes_id ? 'resubmitted' : 'submitted') : $state),
            'payment' => 'Card payment · '.$state,
            'care_hours' => 'Prepaid coverage · '.$state,
            'settlement' => 'Care hours settlement · '.($state ?: 'Hours recorded'),
            'care_hours_payment' => 'Prepaid '.$this->label((string) $model->kind).' · '.$state,
            'support' => $created ? 'Support ticket opened' : 'Support ticket '.$state,
            'support_message' => $model->kind === 'internal_note' ? 'Internal support note added' : 'Support reply sent',
        };
        $urgent = in_array($status, ['failed', 'transfer_failed', 'partially_settled', 'authorization_required', 'reauth_required', 'payment_action_required', 'payment_attention', 'funding_on_hold', 'reconciliation_pending', 'disputed', 'escalated'], true)
            || ($kind === 'booking' && ($model->no_show_flag || $model->dispute_status === 'open' || $status === 'cancelled'))
            || $kind === 'incident'
            || ($kind === 'plan' && $model->payment_status === 'action_required');
        $next = match ($kind) {
            'message' => $model->sender?->role === 'caregiver' ? 'Read the message and reply to the caregiver.' : 'None required; the message was sent.',
            'application' => in_array($status, ['applied', 'shortlisted'], true) ? 'Review the applicant, message the caregiver, or hire.' : ($status === 'withdrawn' ? 'Review remaining applicants and care coverage.' : 'None required; recruitment updated.'),
            'invitation' => $status === 'accepted' ? 'Review the caregiver response and agree care.' : ($status === 'declined' ? 'Review other caregivers and coverage.' : 'Monitor invitation responses.'),
            'payment', 'care_hours', 'settlement', 'care_hours_payment' => $urgent || (int) $model->getAttribute('shortfall_minutes') > 0 || (int) $model->getAttribute('card_minutes') > 0 ? 'Review payment or prepaid coverage before care proceeds.' : 'None required; payment or coverage updated.',
            'visit_change', 'plan_change', 'time_correction', 'extra_visit' => in_array($status, ['pending', 'pending_family', 'payment_action_required', 'escalated', 'approved_admin_required'], true) ? 'Open the visit or schedule and review the outstanding action.' : 'Review the updated outcome if follow-up is needed.',
            'incident', 'support', 'support_message' => 'Open the care record and review support follow-up.',
            'booking' => $urgent ? 'Review the visit and arrange any needed follow-up or replacement.' : ($status === 'completed' && ! $model->family_confirmed_at ? 'Review actual care time and approve the hours.' : 'None required; visit updated.'),
            'request' => $status === 'draft' ? 'Complete and post the request when ready.' : ($status === 'open' ? 'Review applicants and caregiver responses.' : 'None required; request updated.'),
            default => 'Review the schedule and affected visits.',
        };

        return compact('kind', 'title', 'next', 'urgent');
    }

    public function label(string $value): string
    {
        return match ($value) {
            'pending_family' => 'Awaiting hours review', 'applied' => 'Applied', 'approved_processing' => 'Approved; processing',
            'approved_admin_required' => 'Approved; admin review needed', 'authorization_required', 'reauth_required' => 'Card verification required',
            'payment_action_required', 'payment_attention' => 'Payment needs attention', 'selected' => 'Selected',
            'ready' => 'Ready', 'released' => 'Hours released', 'transferred' => 'Caregiver payout transferred',
            'transfer_failed' => 'Caregiver payout failed', 'partially_refunded' => 'Partially refunded',
            default => Str::ucfirst(str_replace('_', ' ', $value)),
        };
    }
}
