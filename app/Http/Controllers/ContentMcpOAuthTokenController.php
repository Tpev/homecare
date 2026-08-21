<?php

namespace App\Http\Controllers;

use App\Exceptions\ContentMcpOAuthException;
use App\Models\ContentApiToken;
use App\Services\Content\ContentApiTokenManager;
use App\Services\Content\ContentMcpOAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContentMcpOAuthTokenController extends Controller
{
    public function issue(Request $request, ContentMcpOAuth $oauth): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'grant_type' => ['required', 'in:authorization_code,refresh_token'],
            'client_id' => ['required', 'string', 'max:100'],
            'resource' => ['required', 'string', 'max:255'],
            'code' => ['required_if:grant_type,authorization_code', 'nullable', 'string', 'max:200'],
            'redirect_uri' => ['required_if:grant_type,authorization_code', 'nullable', 'string', 'max:2048'],
            'code_verifier' => ['required_if:grant_type,authorization_code', 'nullable', 'string', 'max:128'],
            'refresh_token' => ['required_if:grant_type,refresh_token', 'nullable', 'string', 'max:200'],
            'scope' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($validator->fails() || $request->filled('client_secret')) {
            return $this->error(new ContentMcpOAuthException('invalid_request', 'The token request is malformed.'));
        }

        try {
            $payload = $request->input('grant_type') === 'authorization_code'
                ? $oauth->exchangeAuthorizationCode(
                    (string) $request->input('client_id'),
                    (string) $request->input('code'),
                    (string) $request->input('redirect_uri'),
                    (string) $request->input('code_verifier'),
                    (string) $request->input('resource'),
                )
                : $oauth->refresh(
                    (string) $request->input('client_id'),
                    (string) $request->input('refresh_token'),
                    (string) $request->input('resource'),
                    $request->input('scope'),
                );
        } catch (ContentMcpOAuthException $exception) {
            return $this->error($exception);
        }

        return $this->noStore($payload);
    }

    public function revoke(Request $request, ContentMcpOAuth $oauth): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_id' => ['required', 'string', 'max:100'],
            'token' => ['required', 'string', 'max:200'],
        ]);
        if ($validator->fails()) {
            return $this->error(new ContentMcpOAuthException('invalid_request', 'The revocation request is malformed.'));
        }

        try {
            $oauth->revoke((string) $request->input('client_id'), (string) $request->input('token'));
        } catch (ContentMcpOAuthException $exception) {
            if ($exception->oauthError === 'invalid_client') {
                return $this->error($exception);
            }
        }

        return $this->noStore([]);
    }

    public function introspect(
        Request $request,
        ContentMcpOAuth $oauth,
        ContentApiTokenManager $contentTokens,
    ): JsonResponse {
        $serviceToken = $this->serviceCredential($request, $contentTokens);
        if (! $serviceToken) {
            return response()->json(['error' => 'invalid_client'], 401)->withHeaders([
                'WWW-Authenticate' => 'Basic realm="LoLo Content MCP resource server"',
                'Cache-Control' => 'no-store',
            ]);
        }

        $plainTextToken = trim((string) $request->input('token'));
        $access = $oauth->authenticateAccessToken($plainTextToken);
        if (! $access) {
            return $this->noStore(['active' => false]);
        }

        return $this->noStore([
            'active' => true,
            'scope' => implode(' ', $oauth->effectiveScopes($access)),
            'client_id' => $access->client?->client_id,
            'sub' => (string) $access->user_id,
            'actor_user_id' => (int) $access->user_id,
            'token_id' => $access->public_id,
            'token_type' => 'Bearer',
            'aud' => $access->resource,
            'exp' => $access->expires_at?->getTimestamp(),
            'iat' => $access->created_at?->getTimestamp(),
        ]);
    }

    private function serviceCredential(Request $request, ContentApiTokenManager $tokens): ?ContentApiToken
    {
        $authorization = (string) $request->header('Authorization');
        if (! preg_match('/^Basic\s+([A-Za-z0-9+\/=]+)$/i', $authorization, $matches)) {
            return null;
        }

        $decoded = base64_decode($matches[1], true);
        if ($decoded === false || ! str_contains($decoded, ':')) {
            return null;
        }
        [$username, $plainTextToken] = explode(':', $decoded, 2);
        if (! hash_equals('lolo-content-mcp-resource', $username)) {
            return null;
        }

        $token = $tokens->authenticate($plainTextToken);

        return $token?->allows_actor_delegation === true ? $token : null;
    }

    private function error(ContentMcpOAuthException $exception): JsonResponse
    {
        return $this->noStore([
            'error' => $exception->oauthError,
            'error_description' => $exception->getMessage(),
        ], $exception->httpStatus);
    }

    private function noStore(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status)->withHeaders([
            'Cache-Control' => 'no-store',
            'Pragma' => 'no-cache',
        ]);
    }
}
