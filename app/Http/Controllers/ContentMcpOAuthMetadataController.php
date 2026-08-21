<?php

namespace App\Http\Controllers;

use App\Services\Content\ContentMcpScopes;
use Illuminate\Http\JsonResponse;

class ContentMcpOAuthMetadataController extends Controller
{
    public function authorizationServer(): JsonResponse
    {
        $issuer = (string) config('content_mcp.issuer');

        return $this->noStore([
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer.'/oauth/authorize',
            'token_endpoint' => $issuer.'/oauth/token',
            'registration_endpoint' => $issuer.'/oauth/register',
            'revocation_endpoint' => $issuer.'/oauth/revoke',
            'introspection_endpoint' => $issuer.'/oauth/introspect',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'revocation_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => array_keys((array) config('content_mcp.scopes')),
        ]);
    }

    public function protectedResource(ContentMcpScopes $scopes): JsonResponse
    {
        return $this->noStore([
            'resource' => (string) config('content_mcp.resource'),
            'authorization_servers' => [(string) config('content_mcp.issuer')],
            'bearer_methods_supported' => ['header'],
            'scopes_supported' => $scopes->supported(),
        ]);
    }

    private function noStore(array $payload): JsonResponse
    {
        return response()->json($payload)->withHeaders([
            'Cache-Control' => 'no-store',
            'Pragma' => 'no-cache',
        ]);
    }
}
