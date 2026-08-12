<?php

namespace Tests\Feature\Content;

use App\Livewire\Admin\Content\ContentSettings;
use App\Livewire\Admin\Content\PostEditor;
use App\Models\BlogPost;
use App\Models\BlogPostEvent;
use App\Models\ContentCategory;
use App\Services\Content\BlogPostWorkflow;
use App\Services\Content\DocxBlogImporter;
use App\Services\Content\PublicBlogPresenter;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\CreatesPublishedBlogPosts;
use Tests\TestCase;

class ContentHardeningTest extends TestCase
{
    use CreatesPublishedBlogPosts, RefreshDatabase;

    public function test_review_requires_submission_and_a_reviewer_independent_of_the_last_editor(): void
    {
        $fixture = $this->createPublishedBlogPost();
        $workflow = app(BlogPostWorkflow::class);
        $draft = $this->reviewedDraftFrom($fixture, 'Independent review test', 'independent-review-test', false);

        try {
            $workflow->approveReview($draft, $fixture['reviewer_user'], 'A complete review was performed before submission.');
            $this->fail('A draft should not be reviewable before submission.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('review', $exception->errors());
        }

        $draft = $workflow->submitForReview($draft, $fixture['publisher']);
        $this->expectException(ValidationException::class);
        $workflow->approveReview($draft, $fixture['publisher'], 'The submitter must never approve their own work.');
    }

    public function test_stale_editor_is_rejected_and_autosave_does_not_create_permanent_revision_spam(): void
    {
        $fixture = $this->createPublishedBlogPost();
        $workflow = app(BlogPostWorkflow::class);
        $post = $fixture['post']->fresh();
        $snapshot = $workflow->snapshot($post);
        $expectedVersion = $post->edit_version;

        $saved = $workflow->save(
            $post,
            array_merge($snapshot, ['title' => 'First concurrent edit']),
            $fixture['publisher'],
            $snapshot['category_ids'],
            $snapshot['tag_ids'],
            $snapshot['sources'],
            'First editor saved',
            $expectedVersion,
        );

        try {
            $workflow->save(
                $post,
                array_merge($snapshot, ['title' => 'Stale concurrent edit']),
                $fixture['publisher'],
                $snapshot['category_ids'],
                $snapshot['tag_ids'],
                $snapshot['sources'],
                'Stale editor saved',
                $expectedVersion,
            );
            $this->fail('A stale editor should not overwrite newer work.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('conflict', $exception->errors());
        }

        $revisionCount = $saved->revisions()->count();
        Livewire::actingAs($fixture['publisher'])
            ->test(PostEditor::class, ['blogPost' => $saved])
            ->set('form.excerpt', $saved->excerpt.' Autosaved clarification.')
            ->call('autosave')
            ->assertHasNoErrors();
        $this->assertSame($revisionCount, $saved->revisions()->count());
    }

    public function test_scheduled_publication_rechecks_readiness_and_continues_after_a_failure(): void
    {
        $fixture = $this->createPublishedBlogPost();
        $workflow = app(BlogPostWorkflow::class);
        $invalid = $this->reviewedDraftFrom($fixture, 'Invalid scheduled article', 'invalid-scheduled-article');
        $valid = $this->reviewedDraftFrom($fixture, 'Valid scheduled article', 'valid-scheduled-article');
        $invalid = $workflow->publish($invalid, $fixture['publisher'], now()->addMinute());
        $valid = $workflow->publish($valid, $fixture['publisher'], now()->addMinute());
        $invalid->update(['scheduled_for' => now()->subMinute(), 'featured_media_asset_id' => null]);
        $valid->update(['scheduled_for' => now()->subMinute()]);

        $this->assertSame(Command::FAILURE, Artisan::call('content:publish-scheduled'));
        $this->assertSame(BlogPost::STATUS_DRAFT, $invalid->fresh()->status);
        $this->assertSame(BlogPost::STATUS_PUBLISHED, $valid->fresh()->status);
    }

    public function test_force_import_preserves_a_live_revision_indexability_and_public_slug(): void
    {
        Storage::fake('public');
        $fixture = $this->createPublishedBlogPost();
        $path = base_path('blogs/Exploring Opportunities in Home Care Jobs.docx');
        $post = $fixture['post'];
        $post->update(['source_import' => basename($path)]);
        $liveRevisionId = $post->published_revision_id;
        $liveSlug = $post->publishedRevision->snapshot['slug'];

        $result = app(DocxBlogImporter::class)->import($path, $fixture['publisher'], false, true);

        $this->assertSame($post->id, $result['post']->id);
        $this->assertSame($liveRevisionId, $result['post']->published_revision_id);
        $this->assertTrue($result['post']->robots_index);
        $this->get('/blog/'.$liveSlug)->assertOk();
    }

    public function test_stable_citations_keep_the_same_source_when_sources_are_reordered(): void
    {
        $fixture = $this->createPublishedBlogPost();
        $workflow = app(BlogPostWorkflow::class);
        $post = $fixture['post']->fresh();
        $snapshot = $workflow->snapshot($post);
        $first = $snapshot['sources'][0];
        $second = array_merge($first, ['uuid' => (string) Str::uuid(), 'title' => 'Second source', 'url' => 'https://example.org/second']);
        $document = [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => 'Supported claim '],
                    ['type' => 'citation', 'attrs' => ['sourceKey' => $first['uuid'], 'label' => '1']],
                ],
            ]],
        ];

        $post = $workflow->save(
            $post,
            array_merge($snapshot, ['content_json' => $document]),
            $fixture['publisher'],
            $snapshot['category_ids'],
            $snapshot['tag_ids'],
            [$first, $second],
            'Added stable citation',
            $post->edit_version,
        );
        $this->assertStringContainsString('href="#source-'.$first['uuid'].'"', $post->body_html);
        $this->assertStringContainsString('>[1]</a>', $post->body_html);

        $post = $workflow->save(
            $post,
            array_merge($workflow->snapshot($post), ['content_json' => $document]),
            $fixture['publisher'],
            $snapshot['category_ids'],
            $snapshot['tag_ids'],
            [$second, $first],
            'Reordered sources',
            $post->edit_version,
        );
        $this->assertSame($first['uuid'], $post->sources->values()[1]->uuid);
        $this->assertStringContainsString('href="#source-'.$first['uuid'].'"', $post->body_html);
        $this->assertStringContainsString('>[2]</a>', $post->body_html);
    }

    public function test_taxonomy_merge_redirects_old_url_and_resolves_live_snapshot_to_destination(): void
    {
        $fixture = $this->createPublishedBlogPost();
        $target = ContentCategory::query()->create([
            'name' => 'Merged destination', 'slug' => 'merged-destination',
            'description' => 'A durable destination category for consolidated reviewed content.',
            'is_active' => true,
        ]);

        Livewire::actingAs($fixture['publisher'])
            ->test(ContentSettings::class)
            ->call('editCategory', $fixture['category']->id)
            ->set('categoryMergeTargetId', (string) $target->id)
            ->call('mergeCategory')
            ->assertHasNoErrors();

        $this->get('/blog/category/'.$fixture['category']->slug)
            ->assertRedirect('/blog/category/'.$target->slug);
        $this->get('/blog/category/'.$target->slug)
            ->assertOk()
            ->assertSee($fixture['post']->title);

        $finalTarget = ContentCategory::query()->create([
            'name' => 'Final destination', 'slug' => 'final-destination',
            'description' => 'The final canonical category after a second consolidation operation.',
            'is_active' => true,
        ]);
        Livewire::actingAs($fixture['publisher'])
            ->test(ContentSettings::class)
            ->call('editCategory', $target->id)
            ->set('categoryMergeTargetId', (string) $finalTarget->id)
            ->call('mergeCategory')
            ->assertHasNoErrors();

        $this->assertSame($finalTarget->id, $fixture['category']->fresh()->merged_into_id);
        $this->get('/blog/category/'.$fixture['category']->slug)
            ->assertRedirect('/blog/category/'.$finalTarget->slug);
        $this->get('/blog/category/'.$finalTarget->slug)
            ->assertOk()
            ->assertSee($fixture['post']->title);
    }

    public function test_live_modified_date_comes_from_the_immutable_public_revision(): void
    {
        $fixture = $this->createPublishedBlogPost();
        $post = $fixture['post']->fresh(['publishedRevision']);
        $publicRevisionDate = $post->last_published_at->copy()->addDay();
        $post->publishedRevision->forceFill(['created_at' => $publicRevisionDate])->saveQuietly();

        $presented = app(PublicBlogPresenter::class)->present($post->fresh(['publishedRevision']));

        $this->assertTrue($presented['modified_at']->equalTo($publicRevisionDate));
    }

    public function test_analytics_are_deduplicated_bot_filtered_and_pruned(): void
    {
        $fixture = $this->createPublishedBlogPost();
        $endpoint = route('blog.events', $fixture['post']->id);
        $payload = ['event' => 'page_view', 'utm_source' => 'chatgpt.com'];

        $this->withHeader('X-Content-Visitor', 'stable-visitor-token-12345');
        $this->postJson($endpoint, $payload)->assertAccepted();
        $this->withHeader('X-Content-Visitor', 'stable-visitor-token-12345');
        $this->postJson($endpoint, $payload)->assertAccepted();
        $views = BlogPostEvent::where('event', 'page_view')->get(['session_hash', 'dedupe_key', 'metadata']);
        $this->assertSame(1, $views->count(), $views->toJson());
        $this->withHeader('User-Agent', 'ExampleBot/1.0')->postJson($endpoint, ['event' => 'read_complete'])->assertAccepted();
        $this->assertDatabaseMissing('blog_post_events', ['event' => 'read_complete']);

        BlogPostEvent::query()->create([
            'blog_post_id' => $fixture['post']->id,
            'event' => 'signup_completed',
            'user_id' => $fixture['publisher']->id,
            'occurred_at' => now()->subDays(500),
        ]);
        Artisan::call('content:prune-events');
        $this->assertDatabaseMissing('blog_post_events', ['event' => 'signup_completed']);
    }

    /** @param array<string,mixed> $fixture */
    private function reviewedDraftFrom(array $fixture, string $title, string $slug, bool $approve = true): BlogPost
    {
        $workflow = app(BlogPostWorkflow::class);
        $snapshot = $workflow->snapshot($fixture['post']);
        $draft = $workflow->createDraft($fixture['publisher'], $title);
        $draft = $workflow->save(
            $draft,
            array_merge($snapshot, ['title' => $title, 'slug' => $slug]),
            $fixture['publisher'],
            $snapshot['category_ids'],
            $snapshot['tag_ids'],
            $snapshot['sources'],
            'Prepared reviewed draft',
            $draft->edit_version,
        );
        if (! $approve) {
            return $draft;
        }

        $draft = $workflow->submitForReview($draft, $fixture['publisher']);

        return $workflow->approveReview($draft, $fixture['reviewer_user'], 'Claims, links, scope, and accessibility were independently reviewed.');
    }
}
