<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVoiceAgentToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = trim((string) config('voice_agent.internal_api_token'));
        $provided = $this->providedToken($request);

        if ($configured === '' || ! hash_equals($configured, $provided)) {
            return new JsonResponse([
                'message' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }

    private function providedToken(Request $request): string
    {
        $bearer = trim((string) $request->bearerToken());
        if ($bearer !== '') {
            return $bearer;
        }

        return trim((string) $request->header('X-Voice-Agent-Key', ''));
    }
}
