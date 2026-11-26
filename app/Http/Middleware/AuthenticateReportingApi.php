<?php

namespace App\Http\Middleware;

use Closure;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateReportingApi
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('reporting.enabled')) {
            return response()->json(['code' => 'reporting_disabled'], 404);
        }
        if ($request->method() !== 'GET') {
            return response()->json(['code' => 'method_not_allowed'], 405, ['Allow' => 'GET']);
        }
        $hash = (string) config('reporting.token_sha256');
        $token = $request->bearerToken() ?? '';
        try {
            $expiry = config('reporting.token_expires_at');
            $valid = $expiry === null || (is_string($expiry) && preg_match('/T.*(?:Z|[+-]\d{2}:\d{2})$/', $expiry)
                && new DateTimeImmutable($expiry) > new DateTimeImmutable('now'));
        } catch (\Throwable) {
            $valid = false;
        }
        if (! $valid || ! preg_match('/\A[a-f0-9]{64}\z/', $hash) || strlen($token) < 32
            || ! hash_equals($hash, hash('sha256', $token))) {
            return response()->json(['code' => 'unauthorized'], 401);
        }

        // This dedicated credential authorizes only these two read-only reporting routes.
        // No browser session, admin-user provisioning or database token registration is needed.
        return $next($request);
    }
}
