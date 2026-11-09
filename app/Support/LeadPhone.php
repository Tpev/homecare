<?php

namespace App\Support;

class LeadPhone
{
    public static function key(?string $phone): ?string
    {
        $phone = trim((string) $phone);
        // Extensions and incomplete numbers are not safe contact identities.
        if ($phone === '' || ! preg_match('/^\+?[0-9\s().-]+$/', $phone)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);
        if (! str_starts_with($phone, '+')) {
            if (strlen($digits) === 10) {
                $digits = '1'.$digits;
            } elseif (strlen($digits) !== 11 || ! str_starts_with($digits, '1')) {
                return null;
            }
        }

        if (str_starts_with($digits, '1') && strlen($digits) !== 11) {
            return null;
        }

        return preg_match('/^[1-9][0-9]{7,14}$/', $digits) ? '+'.$digits : null;
    }
}
