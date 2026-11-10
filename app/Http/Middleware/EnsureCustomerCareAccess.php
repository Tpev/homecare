<?php

namespace App\Http\Middleware;

use App\Support\StaffAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerCareAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user()?->fresh();
        abort_unless($user?->canManageCustomerCare() && StaffAccess::allowsUser($user), 403);

        return $next($request);
    }
}
