<?php

namespace App\Services\Content;

use App\Models\BlogPost;
use App\Models\ContentApiIdempotencyKey;
use App\Models\ContentApiToken;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContentApiIdempotency
{
    public function execute(Request $request, callable $operation, ?BlogPost $post = null): JsonResponse
    {
        $key = trim((string) $request->header('Idempotency-Key'));
        if (! Str::isUuid($key)) {
            return $this->error('invalid_idempotency_key', 'Idempotency-Key must be a UUID.', 422);
        }

        /** @var ContentApiToken $token */
        $token = $request->attributes->get('content_api_token');
        $keyHash = hash('sha256', $key);
        $requestHash = $this->requestHash($request);
        $record = ContentApiIdempotencyKey::query()
            ->where('content_api_token_id', $token->id)
            ->where('idempotency_key_hash', $keyHash)
            ->first();

        if ($record?->expires_at?->isPast()) {
            $record->delete();
            $record = null;
        }

        if ($record) {
            return $this->existingResponse($record, $requestHash, $request);
        }

        try {
            $record = ContentApiIdempotencyKey::query()->create([
                'content_api_token_id' => $token->id,
                'actor_user_id' => $request->user()?->id,
                'blog_post_id' => $post?->id,
                'idempotency_key_hash' => $keyHash,
                'request_hash' => $requestHash,
                'http_method' => $request->method(),
                'route_name' => (string) $request->route()?->getName(),
                'status' => ContentApiIdempotencyKey::STATUS_PROCESSING,
                'expires_at' => now()->addHours((int) config('content_api.idempotency_ttl_hours')),
            ]);
        } catch (QueryException $exception) {
            $record = ContentApiIdempotencyKey::query()
                ->where('content_api_token_id', $token->id)
                ->where('idempotency_key_hash', $keyHash)
                ->first();

            if (! $record) {
                throw $exception;
            }

            return $this->existingResponse($record, $requestHash, $request);
        }

        return DB::transaction(function () use ($operation, $post, $record): JsonResponse {
            /** @var JsonResponse $response */
            $response = $operation();
            $payload = json_decode((string) $response->getContent(), true);
            $record->forceFill([
                'blog_post_id' => $post?->id ?? data_get($payload, 'data.id'),
                'status' => ContentApiIdempotencyKey::STATUS_COMPLETED,
                'response_status' => $response->getStatusCode(),
                'response_body' => is_array($payload) ? $payload : ['message' => 'Request completed.'],
            ])->save();

            return $response;
        });
    }

    private function requestHash(Request $request): string
    {
        $payload = $request->except(['file']);
        $file = $request->file('file');
        if ($file) {
            $payload['_media'] = [
                'sha256' => hash_file('sha256', $file->getRealPath()) ?: null,
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
            ];
        }
        $normalized = $this->sortRecursive($payload);

        return hash('sha256', $request->method().'|'.$request->path().'|'.json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function sortRecursive(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->sortRecursive($item), $value);
        }
        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->sortRecursive($item), $value);
    }

    private function error(string $code, string $detail, int $status): JsonResponse
    {
        return response()->json([
            'message' => $detail,
            'code' => $code,
            'errors' => ['idempotency_key' => [$detail]],
        ], $status);
    }

    private function existingResponse(ContentApiIdempotencyKey $record, string $requestHash, Request $request): JsonResponse
    {
        if (! hash_equals($record->request_hash, $requestHash)) {
            return $this->error('idempotency_conflict', 'That idempotency key was already used for a different request.', 409);
        }
        if ($record->status === ContentApiIdempotencyKey::STATUS_COMPLETED) {
            if ($record->blog_post_id) {
                $request->attributes->set('content_api_blog_post_id', $record->blog_post_id);
            }

            return response()->json($record->response_body, $record->response_status ?? 200)
                ->header('Idempotency-Replayed', 'true');
        }

        return $this->error('idempotency_in_progress', 'An identical request is already being processed. Retry shortly.', 409);
    }
}
