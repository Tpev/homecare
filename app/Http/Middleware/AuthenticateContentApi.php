<?php

namespace App\Http\Middleware;

use App\Services\Content\ContentApiTokenManager;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateContentApi
{
    public function __construct(private readonly ContentApiTokenManager $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $attemptKey = 'content-api-auth:'.hash('sha256', (string) $request->ip());
        if (RateLimiter::tooManyAttempts($attemptKey, (int) config('content_api.authentication_failures_per_minute'))) {
            return new JsonResponse([
                'message' => 'Too many authentication attempts.',
                'code' => 'rate_limited',
                'errors' => [],
            ], Response::HTTP_TOO_MANY_REQUESTS, [
                'Retry-After' => (string) RateLimiter::availableIn($attemptKey),
            ]);
        }

        $token = $this->tokens->authenticate((string) $request->bearerToken());

        if (! $token) {
            RateLimiter::hit($attemptKey, 60);

            return new JsonResponse([
                'message' => 'Authentication failed.',
                'code' => 'unauthenticated',
                'errors' => [
                    'token' => ['Provide an active, unexpired LoLo Care content API bearer token.'],
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        $request->attributes->set('content_api_token', $token);
        $request->setUserResolver(fn () => $token->actor);

        return $next($request);
    }
}
