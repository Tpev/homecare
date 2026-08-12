<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

class LimitContentApiRequestSize
{
    public function handle(Request $request, Closure $next): Response
    {
        $limit = $request->is('api/content/v1/posts/*/media')
            ? (int) config('content_api.max_media_bytes') + 65536
            : (int) config('content_api.max_json_bytes');
        $length = (int) $request->server('CONTENT_LENGTH', 0);
        if ($length <= 0) {
            $length = strlen($request->getContent());
            foreach (Arr::flatten($request->allFiles()) as $file) {
                $length += max(0, (int) $file->getSize());
            }
        }

        if ($length > $limit) {
            return new JsonResponse([
                'message' => 'The request payload is too large.',
                'code' => 'payload_too_large',
                'errors' => [
                    'payload' => ['The request exceeds the configured content API size limit.'],
                ],
                'meta' => ['max_bytes' => $limit],
            ], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        return $next($request);
    }
}
