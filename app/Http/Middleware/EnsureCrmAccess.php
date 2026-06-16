<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCrmAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $canAccessCrm = $user
            && (
                in_array($user->role, ['admin', 'sales'], true)
                || strtolower((string) $user->email) === 'test@test.com'
            );

        if (! $canAccessCrm) {
            abort(403);
        }

        return $next($request);
    }
}
