<?php

namespace App\Http\Middleware;

use App\Models\BlogPost;
use App\Models\ContentApiAuditEvent;
use App\Models\ContentApiToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuditContentApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();
        $request->attributes->set('content_api_request_id', $requestId);

        try {
            $response = $next($request);
            $this->record($request, $requestId, $response->getStatusCode());
            if ($response instanceof JsonResponse) {
                $payload = (array) $response->getData(true);
                $payload['meta'] = array_merge((array) ($payload['meta'] ?? []), ['request_id' => $requestId]);
                $response->setData($payload);
            }
            $response->headers->set('X-Request-Id', $requestId);

            return $response;
        } catch (\Throwable $exception) {
            $this->record($request, $requestId, $this->statusFor($exception));
            throw $exception;
        }
    }

    private function record(Request $request, string $requestId, int $status): void
    {
        $token = $request->attributes->get('content_api_token');
        $routePost = $request->attributes->get('content_api_blog_post_id');
        $postId = $routePost instanceof BlogPost ? $routePost->id : (is_numeric($routePost) ? (int) $routePost : null);

        ContentApiAuditEvent::query()->create([
            'content_api_token_id' => $token instanceof ContentApiToken ? $token->id : null,
            'actor_user_id' => $request->user()?->id,
            'blog_post_id' => $postId,
            'action' => Str::afterLast((string) $request->route()?->getName(), '.'),
            'ability' => $request->attributes->get('content_api_ability'),
            'outcome' => $status < 400 ? 'succeeded' : 'failed',
            'response_status' => $status,
            'request_id' => $requestId,
            'idempotency_key_hash' => $request->hasHeader('Idempotency-Key')
                ? hash('sha256', (string) $request->header('Idempotency-Key'))
                : null,
            'metadata' => [
                'method' => $request->method(),
                'route' => $request->route()?->getName(),
            ],
            'occurred_at' => now(),
        ]);
    }

    private function statusFor(\Throwable $exception): int
    {
        if (method_exists($exception, 'getStatusCode')) {
            return (int) $exception->getStatusCode();
        }

        if ($exception instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return 403;
        }
        if ($exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return 404;
        }
        if ($exception instanceof \Illuminate\Validation\ValidationException) {
            return array_key_exists('conflict', $exception->errors()) ? 409 : 422;
        }

        return 500;
    }
}
