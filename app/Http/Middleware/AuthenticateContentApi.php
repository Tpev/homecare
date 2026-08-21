<?php

namespace App\Http\Middleware;

use App\Exceptions\ContentMcpDelegationException;
use App\Services\Content\ContentApiTokenManager;
use App\Services\Content\ContentMcpDelegationVerifier;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateContentApi
{
    public function __construct(
        private readonly ContentApiTokenManager $tokens,
        private readonly ContentMcpDelegationVerifier $delegations,
    ) {}

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

        $plainTextToken = (string) $request->bearerToken();
        $token = $this->tokens->authenticate($plainTextToken);

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
        if ($token->allows_actor_delegation) {
            try {
                $delegation = $this->delegations->verify($request, $token, $plainTextToken);
            } catch (ContentMcpDelegationException $exception) {
                return new JsonResponse([
                    'message' => 'Hosted MCP actor delegation failed.',
                    'code' => $exception->errorCode,
                    'errors' => ['delegation' => [$exception->getMessage()]],
                ], $exception->httpStatus);
            }

            $request->attributes->set('content_api_abilities', $delegation['abilities']);
            $request->attributes->set('content_mcp_oauth_access_token', $delegation['access_token']);
            $request->setUserResolver(fn () => $delegation['access_token']->user);
        } else {
            $request->attributes->set('content_api_abilities', $token->abilities ?? []);
            $request->setUserResolver(fn () => $token->actor);
        }

        return $next($request);
    }
}
