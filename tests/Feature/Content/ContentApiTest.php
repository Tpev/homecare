<?php

namespace Tests\Feature\Content;

use App\Models\BlogPost;
use App\Models\ContentApiAuditEvent;
use App\Models\ContentApiIdempotencyKey;
use App\Models\ContentApiToken;
use App\Models\ContentAuthor;
use App\Models\ContentCategory;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\Content\BlogPostReadiness;
use App\Services\Content\BlogPostWorkflow;
use App\Services\Content\ContentApiIdempotency;
use App\Services\Content\ContentApiTokenManager;
use App\Services\Content\MediaAssetManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_authentication_scope_expiry_revocation_throttling_and_request_limits(): void
    {
        $actor = User::factory()->create(['content_role' => 'author']);

        $this->getJson('/api/content/v1/posts')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');

        $draftOnly = $this->token($actor, [ContentApiToken::ABILITY_DRAFT]);
        $this->withToken($draftOnly)->getJson('/api/content/v1/posts')
            ->assertForbidden()
            ->assertJsonPath('code', 'insufficient_scope');

        $expired = app(ContentApiTokenManager::class)->issue(
            $actor,
            'Short lived',
            [ContentApiToken::ABILITY_READ],
            now()->addSecond(),
        );
        $this->travel(2)->seconds();
        $this->withToken($expired['plain_text_token'])->getJson('/api/content/v1/posts')->assertUnauthorized();
        $this->travelBack();

        $revoked = app(ContentApiTokenManager::class)->issue($actor, 'Revoked', [ContentApiToken::ABILITY_READ], now()->addHour());
        app(ContentApiTokenManager::class)->revoke($revoked['token']);
        $this->withToken($revoked['plain_text_token'])->getJson('/api/content/v1/posts')->assertUnauthorized();

        config()->set('content_api.rate_limit_per_minute', 1);
        $read = $this->token($actor, [ContentApiToken::ABILITY_READ]);
        $this->withToken($read)->getJson('/api/content/v1/posts')->assertOk();
        $readToken = ContentApiToken::query()->where('token_hash', ContentApiTokenManager::hash($read))->firstOrFail();
        $lastUsedAt = $readToken->last_used_at;
        $this->travel(2)->seconds();
        $this->withToken($read)->getJson('/api/content/v1/posts')
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'rate_limited');
        $this->assertEquals($lastUsedAt, $readToken->fresh()->last_used_at);
        $this->assertSame(1, ContentApiAuditEvent::query()->where('content_api_token_id', $readToken->id)->count());
        $this->travelBack();

        config()->set('content_api.rate_limit_per_minute', 60);
        config()->set('content_api.max_json_bytes', 128);
        $large = json_encode(['title' => str_repeat('A', 220)], JSON_THROW_ON_ERROR);
        $this->withToken($draftOnly)->call('POST', '/api/content/v1/posts', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => strlen($large),
            'HTTP_IDEMPOTENCY_KEY' => (string) Str::uuid(),
            'HTTP_AUTHORIZATION' => 'Bearer '.$draftOnly,
        ], $large)->assertStatus(413)->assertJsonPath('code', 'payload_too_large');
    }

    public function test_draft_crud_options_validation_idempotency_conflicts_and_audit_attribution(): void
    {
        $publisher = User::factory()->create(['content_role' => 'publisher']);
        $author = $this->authorProfile();
        $category = ContentCategory::query()->create(['name' => 'Guides', 'slug' => 'guides', 'is_active' => true]);
        $token = $this->token($publisher, [
            ContentApiToken::ABILITY_READ,
            ContentApiToken::ABILITY_DRAFT,
            ContentApiToken::ABILITY_AUDIT,
        ]);

        $this->withToken($token)->getJson('/api/content/v1/options?include=authors,categories')
            ->assertOk()
            ->assertJsonPath('data.authors.0.id', $author->id)
            ->assertJsonPath('data.categories.0.id', $category->id);
        $this->withToken($token)->getJson('/api/content/v1/posts/999999')
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');
        $this->assertDatabaseHas('content_api_audit_events', [
            'actor_user_id' => $publisher->id,
            'blog_post_id' => null,
            'action' => 'show',
            'outcome' => 'failed',
            'response_status' => 404,
        ]);

        $this->withToken($token)->postJson('/api/content/v1/posts', ['title' => 'No key'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_idempotency_key');

        $key = (string) Str::uuid();
        $payload = [
            'title' => 'A safe API draft',
            'slug' => 'safe-api-draft',
            'excerpt' => 'A useful reader-focused excerpt that is intentionally long enough for the readiness validator to accept.',
            'author_id' => $author->id,
            'category_ids' => [$category->id],
            'content_json' => $this->document(),
            'seo_title' => 'A safe API draft',
            'meta_description' => 'A carefully prepared description for the safe API draft and its practical reader guidance.',
            'editorial_checklist' => array_fill_keys(array_keys(BlogPostReadiness::checklist()), true),
            'sources' => [[
                'uuid' => (string) Str::uuid(),
                'title' => 'Public guidance',
                'url' => 'https://example.org/guidance',
                'accessed_on' => now()->toDateString(),
            ]],
        ];
        $created = $this->mutate($token, 'postJson', '/api/content/v1/posts', $payload, $key)
            ->assertCreated()
            ->assertJsonPath('data.title', 'A safe API draft')
            ->assertJsonMissingPath('data.featured_media.path');
        $postId = (int) $created->json('data.id');
        $editVersion = (int) $created->json('data.edit_version');

        $this->mutate($token, 'postJson', '/api/content/v1/posts', $payload, $key)
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame(1, BlogPost::count());
        $this->assertSame(2, ContentApiAuditEvent::query()
            ->where('action', 'store')->where('blog_post_id', $postId)->where('outcome', 'succeeded')->count());

        $this->mutate($token, 'postJson', '/api/content/v1/posts', ['title' => 'Different'], $key)
            ->assertConflict()
            ->assertJsonPath('code', 'idempotency_conflict');

        ContentApiIdempotencyKey::query()->where('idempotency_key_hash', hash('sha256', $key))
            ->update(['expires_at' => now()->subMinute()]);
        $this->mutate($token, 'postJson', '/api/content/v1/posts', ['title' => 'Reused after expiry'], $key)
            ->assertCreated()
            ->assertJsonPath('data.title', 'Reused after expiry');
        $this->assertSame(2, BlogPost::count());

        $updateKey = (string) Str::uuid();
        $this->mutate($token, 'patchJson', "/api/content/v1/posts/{$postId}", [
            'edit_version' => $editVersion,
            'title' => 'Updated API draft',
        ], $updateKey)->assertOk()->assertJsonPath('data.title', 'Updated API draft');

        $this->mutate($token, 'patchJson', "/api/content/v1/posts/{$postId}", [
            'edit_version' => $editVersion,
            'title' => 'Stale write',
        ], (string) Str::uuid())->assertConflict()->assertJsonPath('code', 'edit_conflict');
        $this->assertDatabaseHas('content_api_audit_events', [
            'actor_user_id' => $publisher->id,
            'blog_post_id' => $postId,
            'action' => 'update',
            'outcome' => 'failed',
            'response_status' => 409,
        ]);

        $this->mutate($token, 'postJson', "/api/content/v1/posts/{$postId}/audit", [], (string) Str::uuid())
            ->assertOk()
            ->assertJsonPath('data.actor_user_id', $publisher->id);
        $this->assertDatabaseHas('content_api_audit_events', [
            'actor_user_id' => $publisher->id,
            'blog_post_id' => $postId,
            'action' => 'audit',
            'ability' => ContentApiToken::ABILITY_AUDIT,
            'outcome' => 'succeeded',
        ]);
        $this->assertGreaterThan(0, ContentApiAuditEvent::count());

        $expiredRecord = ContentApiIdempotencyKey::query()->firstOrFail();
        $expiredRecord->update(['expires_at' => now()->subMinute()]);
        $this->artisan('model:prune', ['--model' => [ContentApiIdempotencyKey::class]])->assertSuccessful();
        $this->assertDatabaseMissing('content_api_idempotency_keys', ['id' => $expiredRecord->id]);

        $tokenModel = app(ContentApiTokenManager::class)->authenticate($token);
        $atomicRequest = Request::create('/api/content/v1/posts', 'POST', ['title' => 'Atomic failure']);
        $atomicRequest->headers->set('Idempotency-Key', (string) Str::uuid());
        $atomicRequest->attributes->set('content_api_token', $tokenModel);
        $atomicRequest->setUserResolver(fn (): User => $publisher);
        $countBeforeFailure = BlogPost::count();
        try {
            app(ContentApiIdempotency::class)->execute($atomicRequest, function () use ($publisher): JsonResponse {
                app(BlogPostWorkflow::class)->createDraft($publisher, 'Rolled back draft');
                throw new \RuntimeException('Simulated response failure after mutation.');
            });
            $this->fail('The simulated response failure did not propagate.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated response failure after mutation.', $exception->getMessage());
        }
        $this->assertSame($countBeforeFailure, BlogPost::count());
        $this->assertDatabaseHas('content_api_idempotency_keys', ['status' => ContentApiIdempotencyKey::STATUS_PROCESSING]);
    }

    public function test_managed_media_rejects_unsafe_files_and_returns_no_direct_path(): void
    {
        Storage::fake('public');
        $actor = User::factory()->create(['content_role' => 'author']);
        $post = app(BlogPostWorkflow::class)->createDraft($actor, 'Media API draft');
        $token = $this->token($actor, [ContentApiToken::ABILITY_MEDIA]);

        $oversizedDimensions = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        $oversizedDimensions = substr_replace($oversizedDimensions, pack('N', 50000), 16, 4);
        $oversizedDimensions = substr_replace($oversizedDimensions, pack('N', 50000), 20, 4);
        try {
            app(MediaAssetManager::class)->storeBinary($oversizedDimensions, 'dimension-bomb.png', 'image/png', $actor);
            $this->fail('An image with unsafe decoded dimensions was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('media', $exception->errors());
            $this->assertStringContainsString('12,000', $exception->errors()['media'][0]);
        }

        $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())
            ->post("/api/content/v1/posts/{$post->id}/media", [
                'file' => UploadedFile::fake()->create('payload.svg', 10, 'image/svg+xml'),
                'alt_text' => 'Unsafe vector',
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable();

        $mediaKey = (string) Str::uuid();
        $response = $this->withToken($token)->withHeader('Idempotency-Key', $mediaKey)
            ->post("/api/content/v1/posts/{$post->id}/media", [
                'file' => UploadedFile::fake()->image('care.jpg', 1200, 800),
                'alt_text' => 'A caregiver supporting an older adult',
                'license' => 'Owned',
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.mime_type', 'image/jpeg');
        $this->withToken($token)->withHeader('Idempotency-Key', $mediaKey)
            ->post("/api/content/v1/posts/{$post->id}/media", [
                'file' => UploadedFile::fake()->image('different.jpg', 640, 480),
                'alt_text' => 'A caregiver supporting an older adult',
                'license' => 'Owned',
            ], ['Accept' => 'application/json'])
            ->assertConflict()
            ->assertJsonPath('code', 'idempotency_conflict');

        $json = json_encode($response->json(), JSON_THROW_ON_ERROR);
        $asset = MediaAsset::query()->sole();
        $this->assertStringNotContainsString($asset->path, $json);
        $this->assertStringNotContainsString('disk', $json);
        Storage::disk('public')->assertExists($asset->path);
    }

    public function test_preview_is_signed_short_lived_and_still_requires_authenticated_content_access(): void
    {
        $actor = User::factory()->create(['content_role' => 'author']);
        $post = app(BlogPostWorkflow::class)->createDraft($actor, 'Protected preview');
        $token = $this->token($actor, [ContentApiToken::ABILITY_READ]);

        $response = $this->withToken($token)->getJson("/api/content/v1/posts/{$post->id}/preview")
            ->assertOk()
            ->assertJsonPath('data.authentication_required', true);
        $url = $response->json('data.url');
        $path = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);

        $this->get($path)->assertRedirect('/login');
        $bufferLevel = ob_get_level();
        try {
            $this->actingAs($actor)->get($path)->assertOk()->assertSee('Signed editorial preview');
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
        }
        $tampered = preg_replace('/signature=[^&]+/', 'signature=invalid', $path);
        $this->actingAs($actor)->get($tampered)->assertForbidden();
    }

    public function test_submit_schedule_and_publish_enforce_edit_version_independent_review_and_readiness(): void
    {
        config()->set('content.publishing.require_independent_review', true);
        Storage::fake('public');
        $publisher = User::factory()->create(['content_role' => 'publisher']);
        $reviewerUser = User::factory()->create(['content_role' => 'editor']);
        $author = $this->authorProfile();
        $reviewer = $this->authorProfile($reviewerUser, 'Independent Reviewer');
        $category = ContentCategory::query()->create(['name' => 'Care', 'slug' => 'care', 'is_active' => true]);
        $media = MediaAsset::query()->create([
            'disk' => 'public', 'path' => 'content/test/feature.jpg', 'original_filename' => 'feature.jpg',
            'mime_type' => 'image/jpeg', 'size_bytes' => 1000, 'width' => 1200, 'height' => 800,
            'alt_text' => 'Family discussing care', 'license' => 'Owned', 'uploaded_by_user_id' => $publisher->id,
        ]);
        $token = $this->token($publisher, [
            ContentApiToken::ABILITY_DRAFT, ContentApiToken::ABILITY_SUBMIT,
            ContentApiToken::ABILITY_SCHEDULE, ContentApiToken::ABILITY_PUBLISH,
        ]);
        $created = $this->mutate($token, 'postJson', '/api/content/v1/posts', [
            'title' => 'Review gated draft', 'slug' => 'review-gated-draft',
            'excerpt' => 'This complete reader-focused excerpt explains how the review-gated article supports thoughtful care decisions.',
            'content_json' => $this->document(), 'author_id' => $author->id,
            'featured_media_asset_id' => $media->id, 'category_ids' => [$category->id],
            'seo_title' => 'Review gated draft',
            'meta_description' => 'This complete metadata description explains the reviewed practical care guidance for families.',
            'editorial_checklist' => array_fill_keys(array_keys(BlogPostReadiness::checklist()), true),
            'sources' => [[
                'uuid' => (string) Str::uuid(), 'title' => 'Guidance',
                'url' => 'https://example.org/source', 'accessed_on' => now()->toDateString(),
            ]],
        ], (string) Str::uuid())->assertCreated();
        $id = (int) $created->json('data.id');
        $version = (int) $created->json('data.edit_version');

        $this->mutate($token, 'postJson', "/api/content/v1/posts/{$id}/publish", ['edit_version' => $version], (string) Str::uuid())
            ->assertUnprocessable();
        $this->mutate($token, 'postJson', "/api/content/v1/posts/{$id}/submit", ['edit_version' => $version - 1], (string) Str::uuid())
            ->assertConflict();
        $submitted = $this->mutate($token, 'postJson', "/api/content/v1/posts/{$id}/submit", ['edit_version' => $version], (string) Str::uuid())
            ->assertOk()->assertJsonPath('data.status', BlogPost::STATUS_IN_REVIEW);

        $post = BlogPost::query()->findOrFail($id);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        try {
            app(BlogPostWorkflow::class)->approveReview($post, $publisher, 'Publisher attempted to review the work it submitted through the API.');
        } finally {
            $post = app(BlogPostWorkflow::class)->approveReview($post->fresh(), $reviewerUser, 'Independent claims, links, scope, sourcing, and accessibility review completed.');
            $this->assertSame($reviewer->id, $post->reviewer_id);
            $this->mutate($token, 'postJson', "/api/content/v1/posts/{$id}/schedule", [
                'edit_version' => (int) $submitted->json('data.edit_version'),
                'scheduled_for' => now()->addDay()->format('Y-m-d H:i:s'),
            ], (string) Str::uuid())->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
            $scheduleKey = (string) Str::uuid();
            $schedulePayload = [
                'edit_version' => (int) $submitted->json('data.edit_version'),
                'scheduled_for' => now()->addDay()->toIso8601String(),
            ];
            $scheduled = $this->mutate($token, 'postJson', "/api/content/v1/posts/{$id}/schedule", $schedulePayload, $scheduleKey)
                ->assertOk()->assertJsonPath('data.status', BlogPost::STATUS_SCHEDULED);
            $this->mutate($token, 'postJson', "/api/content/v1/posts/{$id}/schedule", $schedulePayload, $scheduleKey)
                ->assertOk()->assertHeader('Idempotency-Replayed', 'true');

            $publishKey = (string) Str::uuid();
            $publishPayload = [
                'edit_version' => (int) $scheduled->json('data.edit_version'),
            ];
            $this->mutate($token, 'postJson', "/api/content/v1/posts/{$id}/publish", $publishPayload, $publishKey)
                ->assertOk()->assertJsonPath('data.status', BlogPost::STATUS_PUBLISHED);
            $revisionsAfterPublish = BlogPost::query()->findOrFail($id)->revisions()->count();
            $this->mutate($token, 'postJson', "/api/content/v1/posts/{$id}/publish", $publishPayload, $publishKey)
                ->assertOk()->assertHeader('Idempotency-Replayed', 'true');
            $this->assertNotNull(BlogPost::query()->find($id)->published_revision_id);
            $this->assertSame($revisionsAfterPublish, BlogPost::query()->findOrFail($id)->revisions()->count());
        }
    }

    private function token(User $actor, array $abilities): string
    {
        return app(ContentApiTokenManager::class)->issue($actor, 'Test connector', $abilities, now()->addHour())['plain_text_token'];
    }

    private function mutate(string $token, string $method, string $uri, array $payload, string $key): TestResponse
    {
        return $this->withToken($token)->withHeader('Idempotency-Key', $key)->{$method}($uri, $payload);
    }

    private function authorProfile(?User $user = null, string $name = 'Public Author'): ContentAuthor
    {
        $user ??= User::factory()->create();

        return ContentAuthor::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'headline' => 'LoLo Care content specialist',
            'bio' => str_repeat($name.' provides detailed and practical care guidance for families. ', 3),
            'credentials' => 'Editorial care content specialist',
            'is_active' => true,
        ]);
    }

    private function document(): array
    {
        return ['type' => 'doc', 'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Practical guidance']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => trim(str_repeat('Families should compare needs, timing, communication, experience, references, safety boundaries, transportation, scheduling, and the exact daily support involved before choosing care. ', 14))]]],
        ]];
    }
}
