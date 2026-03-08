<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCaregiverRole
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'caregiver') {
            abort(403);
        }

        return $next($request);
    }
}
