<?php

namespace App\Http\Controllers;

use App\Services\Content\ContentMcpOAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContentMcpOAuthClientController extends Controller
{
    public function store(Request $request, ContentMcpOAuth $oauth): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_name' => ['required', 'string', 'max:120'],
            'redirect_uris' => ['required', 'array', 'min:1', 'max:5'],
            'redirect_uris.*' => ['required', 'string', 'max:2048'],
            'token_endpoint_auth_method' => ['nullable', 'in:none'],
            'grant_types' => ['nullable', 'array', 'min:1', 'max:2'],
            'grant_types.*' => ['in:authorization_code,refresh_token'],
            'response_types' => ['nullable', 'array', 'size:1'],
            'response_types.*' => ['in:code'],
        ]);

        if ($validator->fails()) {
            return $this->error('invalid_client_metadata', 'The dynamic client metadata is invalid.', 400, $validator->errors()->toArray());
        }

        $clientName = trim((string) $request->input('client_name'));
        if ($clientName === '' || preg_match('/[\x00-\x1F\x7F]/u', $clientName)) {
            return $this->error('invalid_client_metadata', 'The dynamic client name contains invalid characters.');
        }

        $redirectUris = array_values(array_unique($request->input('redirect_uris')));
        foreach ($redirectUris as $redirectUri) {
            if (! $this->isSafeLoopbackRedirect($redirectUri)) {
                return $this->error(
                    'invalid_redirect_uri',
                    'Codex OAuth redirect URIs must use HTTP loopback, an explicit non-privileged port, and a /callback path.',
                );
            }
        }

        $client = $oauth->registerClient($clientName, $redirectUris);

        return response()->json([
            'client_id' => $client->client_id,
            'client_id_issued_at' => $client->created_at?->getTimestamp(),
            'client_id_expires_at' => $client->expires_at?->getTimestamp(),
            'client_name' => $client->name,
            'redirect_uris' => $client->redirect_uris,
            'token_endpoint_auth_method' => 'none',
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
        ], 201)->withHeaders(['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
    }

    private function isSafeLoopbackRedirect(string $uri): bool
    {
        if (filter_var($uri, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($uri);
        $host = trim(strtolower((string) ($parts['host'] ?? '')), '[]');
        $port = (int) ($parts['port'] ?? 0);
        $path = (string) ($parts['path'] ?? '');

        return ($parts['scheme'] ?? null) === 'http'
            && in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
            && $port >= 1024
            && $port <= 65535
            && ($path === '/callback' || str_starts_with($path, '/callback/'))
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['fragment']);
    }

    private function error(string $error, string $description, int $status = 400, array $details = []): JsonResponse
    {
        return response()->json(array_filter([
            'error' => $error,
            'error_description' => $description,
            'validation_errors' => $details ?: null,
        ]), $status)->withHeaders(['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
    }
}
