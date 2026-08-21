<?php

namespace App\Services\Content;

use App\Exceptions\ContentMcpDelegationException;
use App\Models\ContentApiToken;
use App\Models\ContentMcpOAuthAccessToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ContentMcpDelegationVerifier
{
    public function __construct(private readonly ContentMcpScopes $scopes) {}

    /** @return array{access_token:ContentMcpOAuthAccessToken,abilities:list<string>} */
    public function verify(Request $request, ContentApiToken $serviceToken, string $plainTextServiceToken): array
    {
        if (! $serviceToken->allows_actor_delegation) {
            throw new ContentMcpDelegationException('delegation_not_allowed', 'This Content API token cannot delegate actors.', 403);
        }

        $encodedPayload = trim((string) $request->header('X-LoLo-MCP-Delegation'));
        $signature = trim((string) $request->header('X-LoLo-MCP-Signature'));
        if ($encodedPayload === '' || strlen($encodedPayload) > 2048 || ! preg_match('/^[A-Za-z0-9_-]{43}$/D', $signature)) {
            throw new ContentMcpDelegationException('delegation_required', 'A valid hosted MCP actor delegation is required.');
        }

        $expected = self::base64Url(hash_hmac('sha256', $encodedPayload, hash('sha256', $plainTextServiceToken, true), true));
        if (! hash_equals($expected, $signature)) {
            throw new ContentMcpDelegationException('delegation_invalid', 'The hosted MCP delegation signature is invalid.');
        }

        $decoded = self::base64UrlDecode($encodedPayload);
        $payload = $decoded === null ? null : json_decode($decoded, true);
        if (! is_array($payload)
            || ($payload['v'] ?? null) !== 1
            || ! is_string($payload['oauth_token_id'] ?? null)
            || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $payload['oauth_token_id'])
            || ! is_string($payload['method'] ?? null)
            || ! is_string($payload['path'] ?? null)
            || ! is_int($payload['iat'] ?? null)
            || ! is_int($payload['exp'] ?? null)
            || ! is_string($payload['nonce'] ?? null)
            || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $payload['nonce'])) {
            throw new ContentMcpDelegationException('delegation_invalid', 'The hosted MCP delegation payload is invalid.');
        }

        $now = now()->getTimestamp();
        if ($payload['iat'] < $now - 30
            || $payload['iat'] > $now + 10
            || $payload['exp'] <= $now
            || $payload['exp'] > $payload['iat'] + 60
            || ! hash_equals(strtoupper($request->method()), strtoupper($payload['method']))
            || ! hash_equals($request->getPathInfo(), $payload['path'])) {
            throw new ContentMcpDelegationException('delegation_expired', 'The hosted MCP delegation expired or does not match this request.');
        }

        $access = ContentMcpOAuthAccessToken::query()
            ->with(['client', 'user'])
            ->where('public_id', $payload['oauth_token_id'])
            ->first();
        if (! $access?->isUsable()) {
            throw new ContentMcpDelegationException('oauth_session_inactive', 'The Codex OAuth session is expired, revoked, or no longer eligible.');
        }

        $abilities = array_values(array_intersect(
            $this->scopes->effective($access->user, $access->scopes ?? []),
            $serviceToken->abilities ?? [],
        ));
        if ($abilities === []) {
            throw new ContentMcpDelegationException('insufficient_scope', 'The Codex OAuth session has no usable Content API scope.', 403);
        }

        $nonceKey = 'content-mcp-delegation:'.$serviceToken->id.':'.$payload['nonce'];
        if (! Cache::add($nonceKey, true, now()->addMinutes(2))) {
            throw new ContentMcpDelegationException('delegation_replayed', 'The hosted MCP delegation was already used.');
        }

        return ['access_token' => $access, 'abilities' => $abilities];
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        if (! preg_match('/^[A-Za-z0-9_-]+$/D', $value)) {
            return null;
        }
        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);

        return $decoded === false ? null : $decoded;
    }
}
