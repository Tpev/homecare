<?php

namespace App\Support;

class MarketplaceEvent
{
    public const NEW_APPLICANT = 'new_applicant';
    public const INVITE_ACCEPTED = 'invite_accepted';
    public const INVITE_DECLINED = 'invite_declined';
    public const MESSAGE_RECEIVED = 'message_received';
    public const CAREGIVER_HIRED = 'caregiver_hired';
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

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::NEW_APPLICANT,
            self::INVITE_ACCEPTED,
            self::INVITE_DECLINED,
            self::MESSAGE_RECEIVED,
            self::CAREGIVER_HIRED,
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
        ];
    }
}
