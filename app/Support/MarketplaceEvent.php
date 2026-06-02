<?php

namespace App\Support;

class MarketplaceEvent
{
    public const INVITATION_SENT = 'invitation_sent';
    public const NEW_APPLICANT = 'new_applicant';
    public const APPLICATION_SUBMITTED = 'application_submitted';
    public const CARE_REQUEST_WITHDRAWN = 'care_request_withdrawn';
    public const INVITE_ACCEPTED = 'invite_accepted';
    public const INVITE_DECLINED = 'invite_declined';
    public const MESSAGE_RECEIVED = 'message_received';
    public const CAREGIVER_HIRED = 'caregiver_hired';
    public const HIRE_CONFIRMED = 'hire_confirmed';
    public const SHIFT_CANCELLED = 'shift_cancelled';
    public const SHIFT_STARTING_SOON = 'shift_starting_soon';
    public const SHIFT_STARTED = 'shift_started';
    public const SHIFT_COMPLETED = 'shift_completed';
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
    public const CAREGIVER_ONBOARDING_REMINDER_24H = 'caregiver_onboarding_reminder_24h';
    public const REGULAR_CARE_OFFERED = 'regular_care_offered';
    public const REGULAR_CARE_ACCEPTED = 'regular_care_accepted';
    public const REGULAR_CARE_COUNTERED = 'regular_care_countered';
    public const REGULAR_CARE_DECLINED = 'regular_care_declined';
    public const REGULAR_CARE_ENDED = 'regular_care_ended';
    public const REGULAR_CARE_PAYMENT_ATTENTION = 'regular_care_payment_attention';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::INVITATION_SENT,
            self::NEW_APPLICANT,
            self::APPLICATION_SUBMITTED,
            self::CARE_REQUEST_WITHDRAWN,
            self::INVITE_ACCEPTED,
            self::INVITE_DECLINED,
            self::MESSAGE_RECEIVED,
            self::CAREGIVER_HIRED,
            self::HIRE_CONFIRMED,
            self::SHIFT_CANCELLED,
            self::SHIFT_STARTING_SOON,
            self::SHIFT_STARTED,
            self::SHIFT_COMPLETED,
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
            self::CAREGIVER_ONBOARDING_REMINDER_24H,
            self::REGULAR_CARE_OFFERED,
            self::REGULAR_CARE_ACCEPTED,
            self::REGULAR_CARE_COUNTERED,
            self::REGULAR_CARE_DECLINED,
            self::REGULAR_CARE_ENDED,
            self::REGULAR_CARE_PAYMENT_ATTENTION,
        ];
    }
}
