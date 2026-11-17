<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePartnerRole
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->role === 'partner' && $request->user()?->access_revoked_at === null, 403);

        return $next($request);
    }
}
