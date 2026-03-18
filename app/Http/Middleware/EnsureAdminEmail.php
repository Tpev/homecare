<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminEmail
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $isAdmin = $user
            && ($user->role === 'admin' || strtolower((string) $user->email) === 'test@test.com');

        if (! $isAdmin) {
            abort(403);
        }

        return $next($request);
    }
}
