<?php

namespace Tests\Feature\Content;

use App\Models\ContentApiAuditEvent;
use App\Models\ContentApiToken;
use App\Models\ContentMcpOAuthAccessToken;
use App\Models\ContentMcpOAuthClient;
use App\Models\ContentMcpOAuthRefreshToken;
use App\Models\User;
use App\Services\Content\ContentApiTokenManager;
use App\Services\Content\ContentMcpOAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HostedContentMcpOAuthTest extends TestCase
{
    use RefreshDatabase;

    private const RESOURCE = 'https://carelolo.com/mcp/content';

    private const REDIRECT = 'http://127.0.0.1:49152/callback/codex-test';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'https://carelolo.com');
        config()->set('content_mcp.issuer', 'https://carelolo.com');
        config()->set('content_mcp.resource', self::RESOURCE);
    }

    public function test_metadata_and_dynamic_registration_follow_the_remote_mcp_oauth_contract(): void
    {
        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('issuer', 'https://carelolo.com')
            ->assertJsonPath('registration_endpoint', 'https://carelolo.com/oauth/register')
            ->assertJsonPath('code_challenge_methods_supported.0', 'S256');

        $this->getJson('/.well-known/oauth-protected-resource/mcp/content')
            ->assertOk()
            ->assertJsonPath('resource', self::RESOURCE)
            ->assertJsonPath('authorization_servers.0', 'https://carelolo.com');

        $response = $this->postJson('/oauth/register', [
            'client_name' => 'Codex on Charles Mac',
            'redirect_uris' => [self::REDIRECT],
            'token_endpoint_auth_method' => 'none',
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
        ])->assertCreated()->assertJsonPath('client_name', 'Codex on Charles Mac');

        $clientId = $response->json('client_id');
        $this->assertMatchesRegularExpression('/^lolo_mcp_client_[a-f0-9]{32}$/', $clientId);
        $this->assertDatabaseHas('content_mcp_oauth_clients', ['client_id' => $clientId]);

        $this->postJson('/oauth/register', [
            'client_name' => 'Unsafe redirect',
            'redirect_uris' => ['https://attacker.example/callback'],
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_redirect_uri');

        $this->postJson('/oauth/register', [
            'client_name' => 'Loopback redirect with user info',
            'redirect_uris' => ['http://attacker@127.0.0.1:49152/callback/codex-test'],
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_redirect_uri');

        $this->postJson('/oauth/register', [
            'client_name' => str_repeat('x', 17_000),
            'redirect_uris' => [self::REDIRECT],
        ])->assertStatus(413)->assertJsonPath('error', 'invalid_request');

        config()->set('content_mcp.registration_rate_limit_per_hour', 1);
        $this->postJson('/oauth/register', [
            'client_name' => str_repeat('x', 17_000),
            'redirect_uris' => [self::REDIRECT],
        ])->assertTooManyRequests();
    }

    public function test_publisher_can_consent_and_exchange_a_single_use_pkce_code_for_hashed_tokens(): void
    {
        $publisher = User::factory()->create(['content_role' => 'publisher']);
        $client = $this->client();
        $verifier = str_repeat('a', 43);
        $parameters = $this->authorizationParameters($client, $verifier, implode(' ', ContentApiToken::ABILITIES));

        $this->actingAs($publisher)->get('/oauth/authorize?'.http_build_query($parameters))
            ->assertOk()
            ->assertSee('Authorize LoLo Care content access')
            ->assertSee('content:publish');

        $redirect = $this->actingAs($publisher)->post('/oauth/authorize', [
            ...$parameters,
            'decision' => 'allow',
        ])->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url((string) $redirect, PHP_URL_QUERY), $query);
        $code = (string) ($query['code'] ?? '');
        $this->assertNotSame('', $code);
        $this->assertSame('state-value', $query['state'] ?? null);
        $this->assertDatabaseMissing('content_mcp_oauth_authorization_codes', ['code_hash' => $code]);
        $this->assertDatabaseHas('content_mcp_oauth_authorization_codes', ['code_hash' => hash('sha256', $code)]);

        $tokens = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->client_id,
            'code' => $code,
            'redirect_uri' => self::REDIRECT,
            'code_verifier' => $verifier,
            'resource' => self::RESOURCE,
        ])->assertOk()->assertJsonPath('token_type', 'Bearer')->json();

        $this->assertMatchesRegularExpression('/^lolo_mcp_access_/', $tokens['access_token']);
        $this->assertMatchesRegularExpression('/^lolo_mcp_refresh_/', $tokens['refresh_token']);
        $this->assertDatabaseMissing('content_mcp_oauth_access_tokens', ['token_hash' => $tokens['access_token']]);
        $this->assertDatabaseHas('content_mcp_oauth_access_tokens', ['token_hash' => hash('sha256', $tokens['access_token'])]);
        $this->assertDatabaseMissing('content_mcp_oauth_refresh_tokens', ['token_hash' => $tokens['refresh_token']]);
        $this->assertDatabaseHas('content_mcp_oauth_refresh_tokens', ['token_hash' => hash('sha256', $tokens['refresh_token'])]);

        $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->client_id,
            'code' => $code,
            'redirect_uri' => self::REDIRECT,
            'code_verifier' => $verifier,
            'resource' => self::RESOURCE,
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_pkce_failure_scope_separation_expiry_and_revocation_are_enforced(): void
    {
        $author = User::factory()->create(['content_role' => 'author']);
        $client = $this->client();
        $verifier = str_repeat('b', 43);

        $publishParameters = $this->authorizationParameters($client, $verifier, 'content:read content:publish');
        $redirect = $this->actingAs($author)->get('/oauth/authorize?'.http_build_query($publishParameters))
            ->assertRedirect()->headers->get('Location');
        $this->assertStringContainsString('error=access_denied', (string) $redirect);

        $readParameters = $this->authorizationParameters($client, $verifier, 'content:read');
        $redirect = $this->actingAs($author)->post('/oauth/authorize', [...$readParameters, 'decision' => 'allow'])
            ->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url((string) $redirect, PHP_URL_QUERY), $query);

        $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->client_id,
            'code' => $query['code'],
            'redirect_uri' => self::REDIRECT,
            'code_verifier' => str_repeat('c', 43),
            'resource' => self::RESOURCE,
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');

        ContentMcpOAuthClient::query()->whereKey($client->id)->update(['revoked_at' => now()]);
        $this->actingAs($author)->get('/oauth/authorize?'.http_build_query($readParameters))->assertStatus(400);
    }

    public function test_refresh_tokens_rotate_and_reuse_revokes_the_session_family(): void
    {
        $publisher = User::factory()->create(['content_role' => 'publisher']);
        [$client, $tokens] = $this->authorizedTokens($publisher, 'content:read content:draft');

        $rotated = $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $client->client_id,
            'refresh_token' => $tokens['refresh_token'],
            'resource' => self::RESOURCE,
        ])->assertOk()->json();
        $this->assertNotSame($tokens['refresh_token'], $rotated['refresh_token']);

        $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $client->client_id,
            'refresh_token' => $tokens['refresh_token'],
            'resource' => self::RESOURCE,
        ])->assertStatus(400)->assertJsonPath('error_description', 'Refresh token reuse was detected; the session has been revoked.');

        $this->assertSame(0, ContentMcpOAuthRefreshToken::query()->whereNull('revoked_at')->count());
        $this->assertSame(0, ContentMcpOAuthAccessToken::query()->whereNull('revoked_at')->count());
    }

    public function test_introspection_and_delegated_content_api_calls_preserve_the_oauth_actor_and_scopes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $charles = User::factory()->create(['content_role' => 'author']);
        [, $oauthTokens] = $this->authorizedTokens($charles, 'content:read');
        $service = app(ContentApiTokenManager::class)->issue(
            $admin,
            'Hosted MCP resource service',
            ContentApiToken::ABILITIES,
            now()->addDay(),
            $admin,
            true,
        );

        $basic = 'Basic '.base64_encode('lolo-content-mcp-resource:'.$service['plain_text_token']);
        $introspection = $this->withHeader('Authorization', $basic)->post('/oauth/introspect', [
            'token' => $oauthTokens['access_token'],
        ])->assertOk()->assertJsonPath('active', true)->assertJsonPath('actor_user_id', $charles->id)->json();

        $this->withToken($service['plain_text_token'])->getJson('/api/content/v1/options')
            ->assertUnauthorized()->assertJsonPath('code', 'delegation_required');

        $headers = $this->delegationHeaders(
            $service['plain_text_token'],
            (string) $introspection['token_id'],
            'GET',
            '/api/content/v1/options',
        );
        $this->withToken($service['plain_text_token'])->withHeaders($headers)
            ->getJson('/api/content/v1/options')
            ->assertOk();

        $this->assertDatabaseHas('content_api_audit_events', [
            'content_api_token_id' => $service['token']->id,
            'actor_user_id' => $charles->id,
            'ability' => 'content:read',
            'outcome' => 'succeeded',
        ]);
        $event = ContentApiAuditEvent::query()->latest('id')->firstOrFail();
        $this->assertSame($introspection['token_id'], $event->metadata['oauth_access_token_id']);

        $writeHeaders = $this->delegationHeaders(
            $service['plain_text_token'],
            (string) $introspection['token_id'],
            'POST',
            '/api/content/v1/posts',
        );
        $this->withToken($service['plain_text_token'])->withHeaders($writeHeaders + ['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/content/v1/posts', ['title' => 'Not allowed by OAuth scope'])
            ->assertForbidden()->assertJsonPath('code', 'insufficient_scope');
    }

    public function test_delegation_is_request_bound_single_use_and_stops_when_oauth_access_expires(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $actor = User::factory()->create(['content_role' => 'author']);
        [, $oauthTokens] = $this->authorizedTokens($actor, 'content:read');
        $access = ContentMcpOAuthAccessToken::query()->where('token_hash', hash('sha256', $oauthTokens['access_token']))->firstOrFail();
        $service = app(ContentApiTokenManager::class)->issue(
            $admin,
            'Hosted MCP service',
            ContentApiToken::ABILITIES,
            now()->addDay(),
            $admin,
            true,
        );
        $headers = $this->delegationHeaders($service['plain_text_token'], $access->public_id, 'GET', '/api/content/v1/options');

        $this->withToken($service['plain_text_token'])->withHeaders($headers)->getJson('/api/content/v1/options')->assertOk();
        $this->withToken($service['plain_text_token'])->withHeaders($headers)->getJson('/api/content/v1/options')
            ->assertUnauthorized()->assertJsonPath('code', 'delegation_replayed');

        $freshHeaders = $this->delegationHeaders($service['plain_text_token'], $access->public_id, 'GET', '/api/content/v1/posts');
        $this->withToken($service['plain_text_token'])->withHeaders($freshHeaders)->getJson('/api/content/v1/options')
            ->assertUnauthorized()->assertJsonPath('code', 'delegation_expired');

        $access->update(['expires_at' => now()->subMinute()]);
        $expiredHeaders = $this->delegationHeaders($service['plain_text_token'], $access->public_id, 'GET', '/api/content/v1/options');
        $this->withToken($service['plain_text_token'])->withHeaders($expiredHeaders)->getJson('/api/content/v1/options')
            ->assertUnauthorized()->assertJsonPath('code', 'oauth_session_inactive');
    }

    public function test_only_an_explicit_administrator_service_identity_can_receive_delegation_power(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $publisher = User::factory()->create(['content_role' => 'publisher']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ContentApiTokenManager::class)->issue(
            $publisher,
            'Unsafe hosted MCP service',
            ContentApiToken::ABILITIES,
            now()->addDay(),
            $admin,
            true,
        );
    }

    private function client(): ContentMcpOAuthClient
    {
        return app(ContentMcpOAuth::class)->registerClient('Codex desktop test', [self::REDIRECT]);
    }

    /** @return array{0:ContentMcpOAuthClient,1:array} */
    private function authorizedTokens(User $user, string $scope): array
    {
        $client = $this->client();
        $verifier = str_repeat('v', 43);
        $parameters = $this->authorizationParameters($client, $verifier, $scope);
        $redirect = $this->actingAs($user)->post('/oauth/authorize', [...$parameters, 'decision' => 'allow'])
            ->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url((string) $redirect, PHP_URL_QUERY), $query);
        $tokens = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->client_id,
            'code' => $query['code'],
            'redirect_uri' => self::REDIRECT,
            'code_verifier' => $verifier,
            'resource' => self::RESOURCE,
        ])->assertOk()->json();

        return [$client, $tokens];
    }

    private function authorizationParameters(ContentMcpOAuthClient $client, string $verifier, string $scope): array
    {
        return [
            'client_id' => $client->client_id,
            'redirect_uri' => self::REDIRECT,
            'response_type' => 'code',
            'scope' => $scope,
            'state' => 'state-value',
            'code_challenge' => ContentMcpOAuth::pkceChallenge($verifier),
            'code_challenge_method' => 'S256',
            'resource' => self::RESOURCE,
        ];
    }

    private function delegationHeaders(
        string $serviceToken,
        string $oauthTokenId,
        string $method,
        string $path,
    ): array {
        $now = now()->getTimestamp();
        $payload = rtrim(strtr(base64_encode(json_encode([
            'v' => 1,
            'oauth_token_id' => $oauthTokenId,
            'method' => $method,
            'path' => $path,
            'iat' => $now,
            'exp' => $now + 30,
            'nonce' => (string) Str::uuid(),
        ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $signature = rtrim(strtr(base64_encode(hash_hmac(
            'sha256',
            $payload,
            hash('sha256', $serviceToken, true),
            true,
        )), '+/', '-_'), '=');

        return [
            'X-LoLo-MCP-Delegation' => $payload,
            'X-LoLo-MCP-Signature' => $signature,
        ];
    }
}
