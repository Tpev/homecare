<?php

namespace App\Support;

class FamilyQuickRequestDraft
{
    public const SESSION_KEY = 'family.quick_request_draft';

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function put(array $payload): void
    {
        session([self::SESSION_KEY => $payload]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(): ?array
    {
        $payload = session(self::SESSION_KEY);

        return is_array($payload) ? $payload : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function pull(): ?array
    {
        $payload = session()->pull(self::SESSION_KEY);

        return is_array($payload) ? $payload : null;
    }

    public static function has(): bool
    {
        return is_array(session(self::SESSION_KEY));
    }
}
