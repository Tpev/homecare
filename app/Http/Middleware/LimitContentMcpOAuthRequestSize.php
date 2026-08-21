<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LimitContentMcpOAuthRequestSize
{
    private const MAX_BYTES = 16_384;

    public function handle(Request $request, Closure $next): Response
    {
        $declared = filter_var($request->server('CONTENT_LENGTH'), FILTER_VALIDATE_INT);
        if (($declared !== false && $declared > self::MAX_BYTES)
            || strlen($request->getContent()) > self::MAX_BYTES) {
            return new JsonResponse([
                'error' => 'invalid_request',
                'error_description' => 'The OAuth request exceeds the 16 KiB limit.',
            ], Response::HTTP_REQUEST_ENTITY_TOO_LARGE, [
                'Cache-Control' => 'no-store',
                'Pragma' => 'no-cache',
            ]);
        }

        return $next($request);
    }
}
