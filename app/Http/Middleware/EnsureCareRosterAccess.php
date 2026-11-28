<?php

namespace App\Http\Middleware;

use App\Services\CustomerCare\CareRosterAccess;
use Closure;
use Illuminate\Http\Request;

class EnsureCareRosterAccess
{
    public function handle(Request $request, Closure $next)
    {
        CareRosterAccess::authorize($request->user());

        return $next($request);
    }
}
