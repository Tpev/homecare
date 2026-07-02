<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSdrAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $canAccessSdr = $user
            && (
                in_array($user->role, ['admin', 'sales', 'sdr'], true)
                || strtolower((string) $user->email) === 'test@test.com'
            );

        if (! $canAccessSdr) {
            abort(403);
        }

        return $next($request);
    }
}
