<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CareAssistancePrivacyHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Public form errors can include old input; reviews always contain private details.
        $response->headers->set('Cache-Control', 'private, no-store');
        if ($request->routeIs('admin.care-assistance.*')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
