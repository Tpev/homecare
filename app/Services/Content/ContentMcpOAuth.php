<?php

namespace App\Services\Content;

use App\Exceptions\ContentMcpOAuthException;
use App\Models\ContentMcpOAuthAccessToken;
use App\Models\ContentMcpOAuthAuthorizationCode;
use App\Models\ContentMcpOAuthClient;
use App\Models\ContentMcpOAuthRefreshToken;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContentMcpOAuth
{
    private const CODE_PREFIX = 'lolo_mcp_code_';

    private const ACCESS_PREFIX = 'lolo_mcp_access_';

    private const REFRESH_PREFIX = 'lolo_mcp_refresh_';

    private const MAX_GENERATION_ATTEMPTS = 5;

    public function __construct(private readonly ContentMcpScopes $scopes) {}

    /** @param list<string> $redirectUris */
    public function registerClient(string $name, array $redirectUris): ContentMcpOAuthClient
    {
        for ($attempt = 0; $attempt < self::MAX_GENERATION_ATTEMPTS; $attempt++) {
            try {
                return ContentMcpOAuthClient::query()->create([
                    'client_id' => 'lolo_mcp_client_'.bin2hex(random_bytes(16)),
                    'name' => $name,
                    'redirect_uris' => $redirectUris,
                    'expires_at' => now()->addDays((int) config('content_mcp.dynamic_client_ttl_days')),
                ]);
            } catch (QueryException $exception) {
                if (($exception->errorInfo[0] ?? null) !== '23000') {
                    throw $exception;
                }
            }
        }

        throw new ContentMcpOAuthException('server_error', 'A unique OAuth client could not be registered.', 500);
    }

    public function client(string $clientId): ContentMcpOAuthClient
    {
        $client = ContentMcpOAuthClient::query()->where('client_id', $clientId)->first();
        if (! $client?->isUsable()) {
            throw new ContentMcpOAuthException('invalid_client', 'The OAuth client is unknown, expired, or revoked.', 401);
        }

        return $client;
    }

    /** @param list<string> $scopes */
    public function issueAuthorizationCode(
        ContentMcpOAuthClient $client,
        User $user,
        string $redirectUri,
        array $scopes,
        string $resource,
        string $codeChallenge,
    ): string {
        $plainTextCode = self::randomToken(self::CODE_PREFIX);
        ContentMcpOAuthAuthorizationCode::query()->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'code_hash' => self::hash($plainTextCode),
            'redirect_uri' => $redirectUri,
            'scopes' => $scopes,
            'resource' => $resource,
            'code_challenge' => $codeChallenge,
            'expires_at' => now()->addMinutes((int) config('content_mcp.authorization_code_ttl_minutes')),
        ]);

        $client->forceFill(['last_used_at' => now()])->saveQuietly();

        return $plainTextCode;
    }

    /** @return array{access_token:string, token_type:string, expires_in:int, scope:string, refresh_token:string} */
    public function exchangeAuthorizationCode(
        string $clientId,
        string $code,
        string $redirectUri,
        string $codeVerifier,
        string $resource,
    ): array {
        $this->validateResource($resource);
        $this->validateCodeVerifier($codeVerifier);

        return DB::transaction(function () use ($clientId, $code, $redirectUri, $codeVerifier, $resource): array {
            $client = $this->lockedClient($clientId);
            $authorizationCode = ContentMcpOAuthAuthorizationCode::query()
                ->with('user')
                ->where('code_hash', self::hash($code))
                ->lockForUpdate()
                ->first();

            if (! $authorizationCode
                || (int) $authorizationCode->client_id !== (int) $client->id
                || $authorizationCode->used_at !== null
                || $authorizationCode->expires_at?->isPast()
                || ! hash_equals($authorizationCode->redirect_uri, $redirectUri)
                || ! hash_equals($authorizationCode->resource, $resource)
                || ! hash_equals($authorizationCode->code_challenge, self::pkceChallenge($codeVerifier))) {
                throw new ContentMcpOAuthException('invalid_grant', 'The authorization code is invalid, expired, already used, or failed PKCE validation.');
            }

            $user = $authorizationCode->user;
            if (! $user instanceof User) {
                throw new ContentMcpOAuthException('invalid_grant', 'The authorizing user no longer exists.');
            }
            $scopes = $this->scopes->authorize($user, $authorizationCode->scopes ?? []);
            $authorizationCode->forceFill(['used_at' => now()])->save();

            $pair = $this->issueTokenPair($client, $user, $scopes, $resource, (string) Str::uuid());
            unset($pair['refresh_model_id']);

            return $pair;
        }, 3);
    }

    /** @return array{access_token:string, token_type:string, expires_in:int, scope:string, refresh_token:string} */
    public function refresh(
        string $clientId,
        string $refreshToken,
        string $resource,
        ?string $requestedScope = null,
    ): array {
        $this->validateResource($resource);

        $result = DB::transaction(function () use ($clientId, $refreshToken, $resource, $requestedScope): array {
            $client = $this->lockedClient($clientId);
            $stored = ContentMcpOAuthRefreshToken::query()
                ->with('user')
                ->where('token_hash', self::hash($refreshToken))
                ->lockForUpdate()
                ->first();

            if ($stored && (int) $stored->client_id === (int) $client->id && $stored->used_at !== null) {
                ContentMcpOAuthRefreshToken::query()
                    ->where('family_id', $stored->family_id)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now(), 'updated_at' => now()]);
                ContentMcpOAuthAccessToken::query()
                    ->where('family_id', $stored->family_id)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now(), 'updated_at' => now()]);

                return ['refresh_reuse_detected' => true];
            }

            if (! $stored
                || (int) $stored->client_id !== (int) $client->id
                || $stored->revoked_at !== null
                || $stored->expires_at?->isPast()
                || ! hash_equals($stored->resource, $resource)
                || ! ($stored->user instanceof User)) {
                throw new ContentMcpOAuthException('invalid_grant', 'The refresh token is invalid, expired, or revoked.');
            }

            $scopes = $this->scopes->effective($stored->user, $stored->scopes ?? []);
            if ($requestedScope !== null) {
                $requested = $this->scopes->parse($requestedScope);
                if (array_diff($requested, $scopes) !== []) {
                    throw new ContentMcpOAuthException('invalid_scope', 'A refresh cannot add scopes that were not previously granted.');
                }
                $scopes = array_values(array_intersect($scopes, $requested));
            }
            if ($scopes === []) {
                throw new ContentMcpOAuthException('invalid_grant', 'The user no longer has an eligible content scope.');
            }

            $stored->forceFill(['used_at' => now()])->save();
            $pair = $this->issueTokenPair($client, $stored->user, $scopes, $resource, $stored->family_id);
            $stored->forceFill(['replaced_by_id' => $pair['refresh_model_id']])->save();
            unset($pair['refresh_model_id']);

            return $pair;
        }, 3);

        if (($result['refresh_reuse_detected'] ?? false) === true) {
            throw new ContentMcpOAuthException('invalid_grant', 'Refresh token reuse was detected; the session has been revoked.');
        }

        return $result;
    }

    public function authenticateAccessToken(string $plainTextToken): ?ContentMcpOAuthAccessToken
    {
        if (! preg_match('/^lolo_mcp_access_[a-f0-9]{16}_[A-Za-z0-9_-]{43}$/D', trim($plainTextToken))) {
            return null;
        }

        $token = ContentMcpOAuthAccessToken::query()
            ->with(['client', 'user'])
            ->where('token_hash', self::hash(trim($plainTextToken)))
            ->first();

        if (! $token?->isUsable() || $this->scopes->effective($token->user, $token->scopes ?? []) === []) {
            return null;
        }

        $token->forceFill(['last_used_at' => now()])->saveQuietly();
        $token->client?->forceFill(['last_used_at' => now()])->saveQuietly();

        return $token;
    }

    public function revoke(string $clientId, string $plainTextToken): void
    {
        $client = $this->client($clientId);
        $hash = self::hash(trim($plainTextToken));

        DB::transaction(function () use ($client, $hash): void {
            $access = ContentMcpOAuthAccessToken::query()
                ->where('client_id', $client->id)
                ->where('token_hash', $hash)
                ->lockForUpdate()
                ->first();
            if ($access) {
                $access->forceFill(['revoked_at' => $access->revoked_at ?? now()])->save();

                return;
            }

            $refresh = ContentMcpOAuthRefreshToken::query()
                ->where('client_id', $client->id)
                ->where('token_hash', $hash)
                ->lockForUpdate()
                ->first();
            if ($refresh) {
                ContentMcpOAuthRefreshToken::query()
                    ->where('family_id', $refresh->family_id)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now(), 'updated_at' => now()]);
                ContentMcpOAuthAccessToken::query()
                    ->where('family_id', $refresh->family_id)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now(), 'updated_at' => now()]);
            }
        }, 3);
    }

    /** @return list<string> */
    public function effectiveScopes(ContentMcpOAuthAccessToken $token): array
    {
        return $token->user instanceof User
            ? $this->scopes->effective($token->user, $token->scopes ?? [])
            : [];
    }

    public static function hash(string $value): string
    {
        return hash('sha256', $value);
    }

    public static function pkceChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    private function lockedClient(string $clientId): ContentMcpOAuthClient
    {
        $client = ContentMcpOAuthClient::query()->where('client_id', $clientId)->lockForUpdate()->first();
        if (! $client?->isUsable()) {
            throw new ContentMcpOAuthException('invalid_client', 'The OAuth client is unknown, expired, or revoked.', 401);
        }

        return $client;
    }

    private function validateResource(string $resource): void
    {
        if (! hash_equals((string) config('content_mcp.resource'), $resource)) {
            throw new ContentMcpOAuthException('invalid_target', 'The requested OAuth resource is not the LoLo Content MCP resource.');
        }
    }

    private function validateCodeVerifier(string $codeVerifier): void
    {
        if (! preg_match('/^[A-Za-z0-9\-._~]{43,128}$/D', $codeVerifier)) {
            throw new ContentMcpOAuthException('invalid_grant', 'The PKCE code_verifier is invalid.');
        }
    }

    /**
     * @param  list<string>  $scopes
     * @return array{access_token:string, token_type:string, expires_in:int, scope:string, refresh_token:string, refresh_model_id?:int}
     */
    private function issueTokenPair(
        ContentMcpOAuthClient $client,
        User $user,
        array $scopes,
        string $resource,
        string $familyId,
    ): array {
        $plainAccess = self::randomToken(self::ACCESS_PREFIX);
        $access = ContentMcpOAuthAccessToken::query()->create([
            'public_id' => (string) Str::uuid(),
            'family_id' => $familyId,
            'client_id' => $client->id,
            'user_id' => $user->id,
            'token_prefix' => Str::beforeLast($plainAccess, '_'),
            'token_hash' => self::hash($plainAccess),
            'scopes' => $scopes,
            'resource' => $resource,
            'expires_at' => now()->addMinutes((int) config('content_mcp.access_token_ttl_minutes')),
        ]);

        $plainRefresh = self::randomToken(self::REFRESH_PREFIX);
        $refresh = ContentMcpOAuthRefreshToken::query()->create([
            'public_id' => (string) Str::uuid(),
            'family_id' => $familyId,
            'client_id' => $client->id,
            'user_id' => $user->id,
            'access_token_id' => $access->id,
            'token_hash' => self::hash($plainRefresh),
            'scopes' => $scopes,
            'resource' => $resource,
            'expires_at' => now()->addDays((int) config('content_mcp.refresh_token_ttl_days')),
        ]);

        return [
            'access_token' => $plainAccess,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('content_mcp.access_token_ttl_minutes') * 60,
            'scope' => implode(' ', $scopes),
            'refresh_token' => $plainRefresh,
            'refresh_model_id' => (int) $refresh->id,
        ];
    }

    private static function randomToken(string $prefix): string
    {
        return $prefix.bin2hex(random_bytes(8)).'_'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
