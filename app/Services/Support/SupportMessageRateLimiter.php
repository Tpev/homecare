<?php

namespace App\Services\Support;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class SupportMessageRateLimiter
{
    public const MAX_ATTEMPTS = 30;

    public const DECAY_SECONDS = 60;

    public function ensureAllowed(User $user): void
    {
        $key = $this->keyFor($user);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $retryAfter = max(1, RateLimiter::availableIn($key));

            throw ValidationException::withMessages([
                'messageBody' => "You're sending messages too quickly. Try again in {$retryAfter} seconds.",
            ]);
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);
    }

    public function keyFor(User $user): string
    {
        return 'support-messages:user:'.$user->id;
    }
}
