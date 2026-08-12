<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureContentAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isContentTeamMember() === true, 403);

        return $next($request);
    }
}
