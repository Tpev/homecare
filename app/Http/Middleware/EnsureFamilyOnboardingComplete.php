<?php

namespace App\Http\Middleware;

use App\Services\Family\FamilyOnboardingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFamilyOnboardingComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && app(FamilyOnboardingService::class)->pending($request->user())) {
            return redirect()->route('family.onboarding');
        }

        if ($request->user()?->role === 'family' && $request->routeIs('dashboard')) {
            return redirect()->route('family.requests.index');
        }

        return $next($request);
    }
}
