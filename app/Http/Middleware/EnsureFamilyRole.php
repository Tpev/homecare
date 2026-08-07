<?php

namespace App\Http\Middleware;

use App\Services\FamilyAccounts\FamilyAccountContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFamilyRole
{
    public function __construct(private readonly FamilyAccountContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'family') {
            abort(403);
        }

        if (! $this->context->membershipFor($request->user())) {
            abort(403, 'Your access to this family account has ended.');
        }

        return $next($request);
    }
}
