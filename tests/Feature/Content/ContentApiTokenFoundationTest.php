<?php

namespace Tests\Feature\Content;

use App\Models\ContentApiToken;
use App\Models\User;
use App\Services\Content\ContentApiTokenManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContentApiTokenFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tokens_are_random_hashed_expiring_attributed_and_revocable(): void
    {
        $actor = User::factory()->create(['content_role' => 'publisher']);
        $issuer = User::factory()->create(['role' => 'admin']);
        $manager = app(ContentApiTokenManager::class);

        $issued = $manager->issue(
            $actor,
            'Codex publishing',
            [ContentApiToken::ABILITY_PUBLISH, ContentApiToken::ABILITY_READ],
            now()->addHour(),
            $issuer,
        );
        $plainTextToken = $issued['plain_text_token'];
        $token = $issued['token'];

        $this->assertMatchesRegularExpression(
            '/^lolo_content_[a-f0-9]{16}_[A-Za-z0-9_-]{43}$/D',
            $plainTextToken,
        );
        $this->assertSame(hash('sha256', $plainTextToken), $token->token_hash);
        $this->assertStringNotContainsString($plainTextToken, json_encode($token->getAttributes(), JSON_THROW_ON_ERROR));
        $this->assertSame(
            [ContentApiToken::ABILITY_READ, ContentApiToken::ABILITY_PUBLISH],
            $token->abilities,
        );
        $this->assertTrue($token->expires_at->isFuture());
        $this->assertTrue($token->actor->is($actor));
        $this->assertTrue($token->issuer->is($issuer));

        $authenticated = $manager->authenticate($plainTextToken);
        $this->assertTrue($authenticated?->is($token));
        $this->assertNotNull($authenticated?->fresh()->last_used_at);

        $this->assertTrue($manager->revoke($token, $issuer));
        $this->assertFalse($manager->revoke($token, $issuer));
        $this->assertNull($manager->authenticate($plainTextToken));
        $this->assertTrue($token->fresh()->revokedBy->is($issuer));
    }

    public function test_issuance_enforces_content_membership_known_scopes_expiry_and_publisher_privilege(): void
    {
        $manager = app(ContentApiTokenManager::class);
        $outsider = User::factory()->create(['role' => 'family', 'content_role' => null]);

        try {
            $manager->issue($outsider, 'Invalid actor', [ContentApiToken::ABILITY_READ], now()->addHour());
            $this->fail('An outsider received a token.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('actor', $exception->errors());
        }

        $author = User::factory()->create(['content_role' => 'author']);
        foreach (
            [
                [[ContentApiToken::ABILITY_READ, 'content:unknown'], now()->addHour()],
                [[ContentApiToken::ABILITY_PUBLISH], now()->addHour()],
                [[ContentApiToken::ABILITY_READ], now()->subMinute()],
            ] as [$abilities, $expiresAt]
        ) {
            try {
                $manager->issue($author, 'Invalid grant', $abilities, $expiresAt);
                $this->fail('An invalid token grant succeeded.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }

        $issued = $manager->issue($author, 'Demoted actor', [ContentApiToken::ABILITY_READ], now()->addHour());
        $author->update(['content_role' => null]);
        $this->assertNull($manager->authenticate($issued['plain_text_token']));
    }

    public function test_artisan_commands_never_reveal_persisted_secrets_or_hashes_when_listing(): void
    {
        $actor = User::factory()->create([
            'email' => 'content-service@example.test',
            'content_role' => 'publisher',
        ]);

        $this->assertSame(0, Artisan::call('content:token:issue', [
            'actor' => $actor->email,
            'name' => 'Codex local connector',
            '--ability' => [ContentApiToken::ABILITY_READ, ContentApiToken::ABILITY_DRAFT],
            '--ttl' => 60,
        ]));
        $issueOutput = Artisan::output();
        preg_match('/lolo_content_[a-f0-9]{16}_[A-Za-z0-9_-]{43}/D', $issueOutput, $matches);
        $plainTextToken = $matches[0] ?? '';
        $this->assertNotSame('', $plainTextToken);

        $token = ContentApiToken::query()->sole();
        $this->assertSame(hash('sha256', $plainTextToken), $token->token_hash);
        $this->assertSame(0, Artisan::call('content:token:list'));
        $listOutput = Artisan::output();
        $this->assertStringContainsString($token->token_prefix, $listOutput);
        $this->assertStringNotContainsString($plainTextToken, $listOutput);
        $this->assertStringNotContainsString($token->token_hash, $listOutput);

        $this->assertSame(0, Artisan::call('content:token:revoke', ['token' => $token->token_prefix]));
        $this->assertNotNull($token->fresh()->revoked_at);
    }
}
