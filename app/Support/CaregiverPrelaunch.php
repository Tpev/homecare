<?php

namespace App\Support;

class CaregiverPrelaunch
{
    public static function enabled(): bool
    {
        return (bool) config('marketplace.caregiver_prelaunch_mode', false);
    }

    public static function message(): string
    {
        return 'HomeCare is currently in caregiver pre-launch mode. Complete your setup now and we will notify you as soon as matching opens.';
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

    public static function familyCanProceedWithCaregiver(?string $caregiverEmail): bool
    {
        if (! self::enabled()) {
            return true;
        }

        return self::isPilotCaregiverEmail($caregiverEmail);
    }
}
