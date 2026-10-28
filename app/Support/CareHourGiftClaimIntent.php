<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/** Temporary navigation intent only. The gift must still be authorized when previewed and claimed. */
class CareHourGiftClaimIntent
{
    public const SESSION_KEY = 'care_hours.gift_claim_intent';

    /** @param array{paid_minutes:int,conditional_bonus_minutes:int} $safeGift */
    public static function put(#[\SensitiveParameter] string $code, array $safeGift): void
    {
        if (trim($code) === '' || strlen($code) > 256
            || ! is_int($safeGift['paid_minutes'] ?? null) || $safeGift['paid_minutes'] <= 0
            || ! is_int($safeGift['conditional_bonus_minutes'] ?? null) || $safeGift['conditional_bonus_minutes'] < 0) {
            throw new \InvalidArgumentException('A valid gift claim intent is required.');
        }

        session([self::SESSION_KEY => [
            'encrypted_code' => Crypt::encryptString($code),
            'expires_at' => now()->addHour()->timestamp,
            'summary' => [
                'paid_minutes' => $safeGift['paid_minutes'],
                'conditional_bonus_minutes' => $safeGift['conditional_bonus_minutes'],
            ],
        ]]);
    }

    public static function active(): bool
    {
        return self::payload() !== null;
    }

    public static function code(): ?string
    {
        return self::payload()['code'] ?? null;
    }

    public static function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /** @return array{paid_minutes:int,conditional_bonus_minutes:int}|null */
    public static function summary(): ?array
    {
        return self::payload()['summary'] ?? null;
    }

    private static function payload(): ?array
    {
        $payload = session(self::SESSION_KEY);
        if (! is_array($payload) || ! is_int($payload['expires_at'] ?? null)
            || $payload['expires_at'] <= now()->timestamp
            || ! is_string($payload['encrypted_code'] ?? null)
            || ! is_array($payload['summary'] ?? null)
            || ! is_int($payload['summary']['paid_minutes'] ?? null) || $payload['summary']['paid_minutes'] <= 0
            || ! is_int($payload['summary']['conditional_bonus_minutes'] ?? null) || $payload['summary']['conditional_bonus_minutes'] < 0) {
            self::clear();

            return null;
        }
        try {
            $code = Crypt::decryptString($payload['encrypted_code']);
        } catch (DecryptException) {
            self::clear();

            return null;
        }
        if (trim($code) === '' || strlen($code) > 256) {
            self::clear();

            return null;
        }

        return ['code' => $code, 'summary' => [
            'paid_minutes' => $payload['summary']['paid_minutes'],
            'conditional_bonus_minutes' => $payload['summary']['conditional_bonus_minutes'],
        ]];
    }
}
