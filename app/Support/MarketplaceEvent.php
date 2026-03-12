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
        ];
    }
}
