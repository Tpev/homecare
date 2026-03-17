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
}

