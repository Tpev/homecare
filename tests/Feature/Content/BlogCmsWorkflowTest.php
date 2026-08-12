<?php

namespace Tests\Feature\Content;

use App\Jobs\SubmitIndexNowUrls;
use App\Livewire\Admin\Content\PostEditor;
use App\Models\BlogPost;
use App\Models\BlogPostEvent;
use App\Models\User;
use App\Services\Content\BlogPostWorkflow;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Concerns\CreatesPublishedBlogPosts;
use Tests\TestCase;

class BlogCmsWorkflowTest extends TestCase
{
    use CreatesPublishedBlogPosts, RefreshDatabase;

    public function test_content_roles_are_separated_and_email_is_not_an_admin_bypass(): void
    {
        $emailOnly = User::factory()->create(['email' => 'test@test.com', 'role' => 'family']);
        $author = User::factory()->create(['role' => 'family', 'content_role' => 'author']);
        $editor = User::factory()->create(['role' => 'family', 'content_role' => 'editor']);
        $publisher = User::factory()->create(['role' => 'family', 'content_role' => 'publisher']);
        $post = BlogPost::query()->create([
            'title' => 'Draft', 'slug' => 'draft', 'status' => BlogPost::STATUS_DRAFT,
            'content_json' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
            'created_by_user_id' => $author->id,
        ]);

        $this->assertFalse($emailOnly->isAdministrator());
        $this->assertTrue($author->can('update', $post));
        $this->assertFalse($author->can('review', $post));
        $this->assertTrue($editor->can('review', $post));
        $this->assertFalse($editor->can('publish', $post));
        $this->assertTrue($publisher->can('publish', $post));
        $this->actingAs($emailOnly)->get(route('admin.content.posts.index'))->assertForbidden();
    }

    public function test_editing_after_publication_keeps_the_immutable_live_revision_until_republished(): void
    {
        $fixture = $this->createPublishedBlogPost();
        $post = $fixture['post'];
        $originalTitle = $post->publishedRevision->snapshot['title'];
        $snapshot = app(BlogPostWorkflow::class)->snapshot($post);

        $working = app(BlogPostWorkflow::class)->save(
            $post,
            array_merge($snapshot, ['title' => 'A materially revised working title']),
            $fixture['publisher'],
            $snapshot['category_ids'],
            $snapshot['tag_ids'],
            $snapshot['sources'],
            'Started a substantial update',
        );

        $this->assertSame(BlogPost::STATUS_DRAFT, $working->status);
        $this->assertNotNull($working->published_revision_id);
        $this->assertNull($working->reviewed_at);
        $this->assertNull($working->reviewer_id);
        $this->get(route('blog.show', ['blogSlug' => $working->slug]))
            ->assertOk()
            ->assertSee($originalTitle)
            ->assertDontSee('A materially revised working title');
    }

    public function test_any_change_after_review_requires_a_fresh_independent_review(): void
    {
        $fixture = $this->createPublishedBlogPost();
        $workflow = app(BlogPostWorkflow::class);
        $snapshot = $workflow->snapshot($fixture['post']);
        $draft = $workflow->createDraft($fixture['publisher'], 'Fresh reviewed draft');
        $draft = $workflow->save(
            $draft,
            array_merge($snapshot, ['title' => 'Fresh reviewed draft', 'slug' => 'fresh-reviewed-draft']),
            $fixture['publisher'],
            $snapshot['category_ids'],
            $snapshot['tag_ids'],
            $snapshot['sources'],
        );
        $draft = $workflow->submitForReview($draft, $fixture['publisher']);
        $draft = $workflow->approveReview($draft, $fixture['reviewer_user'], 'Approved before the author changed it.');

        $changed = $workflow->save(
            $draft,
            array_merge($workflow->snapshot($draft), ['excerpt' => $draft->excerpt.' Materially changed after approval.']),
            $fixture['publisher'],
            $snapshot['category_ids'],
            $snapshot['tag_ids'],
            $snapshot['sources'],
        );

        $this->assertSame(BlogPost::STATUS_DRAFT, $changed->status);
        $this->assertNull($changed->reviewed_at);
        $this->assertNull($changed->reviewer_id);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $workflow->publish($changed, $fixture['publisher']);
    }

    public function test_admin_editor_exposes_workflow_media_sources_preview_and_revisions(): void
    {
        $fixture = $this->createPublishedBlogPost();

        $this->actingAs($fixture['publisher']);
        $this->get(route('admin.content.posts.index'))->assertOk()->assertSee('Editorial command center');
        $this->get(route('admin.content.posts.edit', $fixture['post']->id))
            ->assertOk()
            ->assertSee('Sources and citations')
            ->assertSee('Publishing gate')
            ->assertSee('Revision history')
            ->assertSee('Managed media');

        Livewire::test(PostEditor::class, ['blogPost' => $fixture['post']])
            ->set('form.title', 'Updated editorial title')
            ->call('saveDraft')
            ->assertHasNoErrors();
    }

    public function test_publish_dispatches_indexnow_and_content_click_is_attributed_to_registration(): void
    {
        Queue::fake();
        $fixture = $this->createPublishedBlogPost();
        Queue::assertPushed(SubmitIndexNowUrls::class);

        $this->postJson(route('blog.events', $fixture['post']->id), [
            'event' => 'cta_click', 'placement' => 'article_end', 'href' => route('register'),
        ])->assertAccepted();
        $newUser = User::factory()->create(['role' => 'family']);
        event(new Registered($newUser));

        $this->assertDatabaseHas('blog_post_events', [
            'blog_post_id' => $fixture['post']->id,
            'event' => 'signup_completed',
            'user_id' => $newUser->id,
        ]);
        $this->assertSame(1, BlogPostEvent::where('event', 'cta_click')->count());
    }
}
