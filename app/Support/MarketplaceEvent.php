<?php

namespace App\Support;

class MarketplaceEvent
{
    public const INVITATION_SENT = 'invitation_sent';

    public const INVITATION_RECEIVED = 'invitation_received';

    public const NEW_APPLICANT = 'new_applicant';

    public const APPLICATION_SUBMITTED = 'application_submitted';

    public const CARE_REQUEST_WITHDRAWN = 'care_request_withdrawn';

    public const INVITE_ACCEPTED = 'invite_accepted';

    public const INVITE_DECLINED = 'invite_declined';

    public const MESSAGE_RECEIVED = 'message_received';

    public const BOOKING_CHANGE_REQUESTED = 'booking_change_requested';

    public const BOOKING_CHANGE_ACCEPTED = 'booking_change_accepted';

    public const BOOKING_CHANGE_DECLINED = 'booking_change_declined';

    public const CAREGIVER_HIRED = 'caregiver_hired';

    public const HIRE_CONFIRMED = 'hire_confirmed';

    public const SHIFT_CANCELLED = 'shift_cancelled';

    public const SHIFT_STARTING_SOON = 'shift_starting_soon';

    public const SHIFT_REMINDER_24H = 'shift_reminder_24h';

    public const SHIFT_STARTED = 'shift_started';

    public const SHIFT_COMPLETED = 'shift_completed';

    public const TIMESHEET_AUTO_APPROVED = 'timesheet_auto_approved';

    public const TIME_CORRECTION_REQUESTED = 'time_correction_requested';

    public const TIME_CORRECTION_CHANGES_REQUESTED = 'time_correction_changes_requested';

    public const TIME_CORRECTION_RESUBMITTED = 'time_correction_resubmitted';

    public const TIME_CORRECTION_APPROVED = 'time_correction_approved';

    public const TIME_CORRECTION_PAYMENT_ACTION_REQUIRED = 'time_correction_payment_action_required';

    public const TIME_CORRECTION_APPLIED = 'time_correction_applied';

    public const TIME_CORRECTION_ESCALATED = 'time_correction_escalated';

    public const TIME_CORRECTION_WITHDRAWN = 'time_correction_withdrawn';

    public const REVIEW_RECEIVED = 'review_received';

    public const MATCHING_REQUEST_REMINDER = 'matching_request_reminder';

    public const PAYMENT_AUTHORIZED = 'payment_authorized';

    public const PAYMENT_AUTHORIZATION_FAILED = 'payment_authorization_failed';

    public const PAYMENT_ACTION_REQUIRED = 'payment_action_required';

    public const PAYMENT_CAPTURED = 'payment_captured';

    public const PAYMENT_REFUNDED = 'payment_refunded';

    public const PAYOUT_TRANSFERRED = 'payout_transferred';

    public const PAYOUT_TRANSFER_FAILED = 'payout_transfer_failed';

    public const CAREGIVER_WELCOME = 'caregiver_welcome';

    public const FAMILY_WELCOME = 'family_welcome';

    public const CAREGIVER_ONBOARDING_REMINDER_24H = 'caregiver_onboarding_reminder_24h';

    public const SUPPORT_TICKET_CREATED = 'support_ticket_created';

    public const SUPPORT_TICKET_REPLY = 'support_ticket_reply';

    public const REGULAR_CARE_OFFERED = 'regular_care_offered';

    public const REGULAR_CARE_ACCEPTED = 'regular_care_accepted';

    public const REGULAR_CARE_COUNTERED = 'regular_care_countered';

    public const REGULAR_CARE_DECLINED = 'regular_care_declined';

    public const REGULAR_CARE_ENDED = 'regular_care_ended';

    public const REGULAR_CARE_PAYMENT_ATTENTION = 'regular_care_payment_attention';

    public const REGULAR_CARE_SCHEDULE_CHANGE_REQUESTED = 'regular_care_schedule_change_requested';

    public const REGULAR_CARE_EXTRA_VISIT_REQUESTED = 'regular_care_extra_visit_requested';

    public const REGULAR_CARE_SCHEDULE_CHANGE_ACCEPTED = 'regular_care_schedule_change_accepted';

    public const REGULAR_CARE_SCHEDULE_CHANGE_DECLINED = 'regular_care_schedule_change_declined';

    public const REGULAR_CARE_VISIT_SKIPPED = 'regular_care_visit_skipped';

    public const REGULAR_CARE_PAUSED = 'regular_care_paused';

    public const REGULAR_CARE_RESUMED = 'regular_care_resumed';

    public const COMPLETED_EXTRA_VISIT_SUBMITTED = 'completed_extra_visit_submitted';

    public const COMPLETED_EXTRA_VISIT_CHANGES_REQUESTED = 'completed_extra_visit_changes_requested';

    public const COMPLETED_EXTRA_VISIT_RESUBMITTED = 'completed_extra_visit_resubmitted';

    public const COMPLETED_EXTRA_VISIT_APPROVED = 'completed_extra_visit_approved';

    public const COMPLETED_EXTRA_VISIT_DISPUTED = 'completed_extra_visit_disputed';

    public const COMPLETED_EXTRA_VISIT_WITHDRAWN = 'completed_extra_visit_withdrawn';

    public const COMPLETED_EXTRA_VISIT_APPLIED = 'completed_extra_visit_applied';

    public const COMPLETED_EXTRA_VISIT_PAYMENT_ACTION_REQUIRED = 'completed_extra_visit_payment_action_required';

    public const COMPLETED_EXTRA_VISIT_ESCALATED = 'completed_extra_visit_escalated';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::INVITATION_SENT,
            self::INVITATION_RECEIVED,
            self::NEW_APPLICANT,
            self::APPLICATION_SUBMITTED,
            self::CARE_REQUEST_WITHDRAWN,
            self::INVITE_ACCEPTED,
            self::INVITE_DECLINED,
            self::MESSAGE_RECEIVED,
            self::BOOKING_CHANGE_REQUESTED,
            self::BOOKING_CHANGE_ACCEPTED,
            self::BOOKING_CHANGE_DECLINED,
            self::CAREGIVER_HIRED,
            self::HIRE_CONFIRMED,
            self::SHIFT_CANCELLED,
            self::SHIFT_STARTING_SOON,
            self::SHIFT_REMINDER_24H,
            self::SHIFT_STARTED,
            self::SHIFT_COMPLETED,
            self::TIMESHEET_AUTO_APPROVED,
            self::TIME_CORRECTION_REQUESTED,
            self::TIME_CORRECTION_CHANGES_REQUESTED,
            self::TIME_CORRECTION_RESUBMITTED,
            self::TIME_CORRECTION_APPROVED,
            self::TIME_CORRECTION_PAYMENT_ACTION_REQUIRED,
            self::TIME_CORRECTION_APPLIED,
            self::TIME_CORRECTION_ESCALATED,
            self::TIME_CORRECTION_WITHDRAWN,
            self::REVIEW_RECEIVED,
            self::MATCHING_REQUEST_REMINDER,
            self::PAYMENT_AUTHORIZED,
            self::PAYMENT_AUTHORIZATION_FAILED,
            self::PAYMENT_ACTION_REQUIRED,
            self::PAYMENT_CAPTURED,
            self::PAYMENT_REFUNDED,
            self::PAYOUT_TRANSFERRED,
            self::PAYOUT_TRANSFER_FAILED,
            self::CAREGIVER_WELCOME,
            self::FAMILY_WELCOME,
            self::CAREGIVER_ONBOARDING_REMINDER_24H,
            self::SUPPORT_TICKET_CREATED,
            self::SUPPORT_TICKET_REPLY,
            self::REGULAR_CARE_OFFERED,
            self::REGULAR_CARE_ACCEPTED,
            self::REGULAR_CARE_COUNTERED,
            self::REGULAR_CARE_DECLINED,
            self::REGULAR_CARE_ENDED,
            self::REGULAR_CARE_PAYMENT_ATTENTION,
            self::REGULAR_CARE_SCHEDULE_CHANGE_REQUESTED,
            self::REGULAR_CARE_EXTRA_VISIT_REQUESTED,
            self::REGULAR_CARE_SCHEDULE_CHANGE_ACCEPTED,
            self::REGULAR_CARE_SCHEDULE_CHANGE_DECLINED,
            self::REGULAR_CARE_VISIT_SKIPPED,
            self::REGULAR_CARE_PAUSED,
            self::REGULAR_CARE_RESUMED,
            self::COMPLETED_EXTRA_VISIT_SUBMITTED,
            self::COMPLETED_EXTRA_VISIT_CHANGES_REQUESTED,
            self::COMPLETED_EXTRA_VISIT_RESUBMITTED,
            self::COMPLETED_EXTRA_VISIT_APPROVED,
            self::COMPLETED_EXTRA_VISIT_DISPUTED,
            self::COMPLETED_EXTRA_VISIT_WITHDRAWN,
            self::COMPLETED_EXTRA_VISIT_APPLIED,
            self::COMPLETED_EXTRA_VISIT_PAYMENT_ACTION_REQUIRED,
            self::COMPLETED_EXTRA_VISIT_ESCALATED,
        ];
    }
}
