<?php

namespace App\Http\Middleware;

use App\Services\ContinuousCoverage\ContinuousCoverageAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureContinuousCoverageAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app(ContinuousCoverageAccess::class)->allows($request->user()), 404);

        return $next($request);
    }
}
