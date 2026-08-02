<?php

namespace App\Support;

use App\Models\CareRequest;
use App\Models\CareRequestInvitation;

class CaregiverPrelaunch
{
    public static function enabled(): bool
    {
        return (bool) config('marketplace.caregiver_prelaunch_mode', false);
    }

    public static function message(): string
    {
        return 'LoLo Care is currently in caregiver pre-launch mode. Complete your setup now and we will notify you as soon as matching opens.';
    }

    public static function familyHireMessage(): string
    {
        return 'Ask this caregiver to accept your invitation first. Once they accept, you can hire them for this visit.';
    }

    /**
     * @return list<string>
     */
    public static function pilotCaregiverEmails(): array
    {
        $emails = config('marketplace.family_prelaunch_auto_applicants.emails', []);

        if (! is_array($emails)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $email): string => strtolower(trim((string) $email)),
            $emails
        )));
    }

    public static function isPilotCaregiverEmail(?string $email): bool
    {
        $candidate = strtolower(trim((string) $email));

        if ($candidate === '') {
            return false;
        }

        return in_array($candidate, self::pilotCaregiverEmails(), true);
    }

    public static function familyCanProceedWithCaregiver(
        ?string $caregiverEmail,
        ?CareRequest $careRequest = null,
        ?int $caregiverUserId = null,
    ): bool {
        if (! self::enabled()) {
            return true;
        }

        if (self::isPilotCaregiverEmail($caregiverEmail)) {
            return true;
        }

        if ($careRequest && $caregiverUserId) {
            return $careRequest->invitations()
                ->where('caregiver_user_id', $caregiverUserId)
                ->where('status', CareRequestInvitation::STATUS_ACCEPTED)
                ->exists();
        }

        return false;
    }
}
