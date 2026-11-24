<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCareAssistanceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->canAccessCareAssistance() && $user->fresh()?->canAccessCareAssistance(), 403);

        return $next($request);
    }
}
