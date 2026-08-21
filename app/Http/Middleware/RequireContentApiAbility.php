<?php

namespace App\Http\Middleware;

use App\Models\ContentApiToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireContentApiAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $token = $request->attributes->get('content_api_token');

        $abilities = $request->attributes->get('content_api_abilities', []);
        if (! $token instanceof ContentApiToken || ! in_array($ability, is_array($abilities) ? $abilities : [], true)) {
            return new JsonResponse([
                'message' => 'This token is not authorized for the requested operation.',
                'code' => 'insufficient_scope',
                'errors' => [
                    'ability' => ['The endpoint requires the '.$ability.' ability.'],
                ],
                'meta' => ['required_ability' => $ability],
            ], Response::HTTP_FORBIDDEN);
        }

        $request->attributes->set('content_api_ability', $ability);

        return $next($request);
    }
}
