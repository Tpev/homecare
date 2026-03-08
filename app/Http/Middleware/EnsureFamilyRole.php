<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFamilyRole
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'family') {
            abort(403);
        }

        return $next($request);
    }
}
