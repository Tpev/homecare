<?php

namespace Tests\Feature\Marketing;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPublishedBlogPosts;
use Tests\TestCase;

class BlogPagesTest extends TestCase
{
    use CreatesPublishedBlogPosts, RefreshDatabase;

    public function test_only_published_revisions_appear_on_public_blog(): void
    {
        $published = $this->createPublishedBlogPost()['post'];
        BlogPost::query()->create([
            'title' => 'Risky legacy draft',
            'slug' => 'risky-legacy-draft',
            'status' => BlogPost::STATUS_DRAFT,
            'content_json' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
            'robots_index' => false,
            'robots_directives' => 'noindex,nofollow',
        ]);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee('Clear answers for arranging care at home')
            ->assertSee($published->title)
            ->assertDontSee('Risky legacy draft');
        $this->get('/blog/risky-legacy-draft')->assertNotFound();
    }

    public function test_article_renders_semantic_content_authorship_sources_and_accurate_schema(): void
    {
        $fixture = $this->createPublishedBlogPost();
        $post = $fixture['post'];

        $this->get(route('blog.show', ['blogSlug' => $post->slug]))
            ->assertOk()
            ->assertSee($post->title)
            ->assertSee('<h2 id="how-to-compare-local-care-options">', false)
            ->assertSee('Jordan Local')
            ->assertSee('Sources')
            ->assertSee('North Carolina Department of Health and Human Services')
            ->assertSee('Frequently asked questions')
            ->assertSee('property="og:type" content="article"', false)
            ->assertSee('"datePublished":"'.$post->first_published_at->toIso8601String().'"', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('rel="author"', false);
    }

    public function test_embedded_cta_text_color_overrides_the_generic_article_link_color(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString(
            '.public-article-content .content-cta__link--primary { background: #0f5b52; color: white; }',
            $css,
        );
    }

    public function test_public_only_human_author_is_emitted_as_a_schema_person(): void
    {
        $julie = \App\Models\ContentAuthor::query()->create([
            'name' => 'Julie',
            'schema_type' => \App\Models\ContentAuthor::SCHEMA_PERSON,
            'slug' => 'julie',
            'headline' => 'Local senior care writer',
            'bio' => str_repeat('Julie writes practical local guidance for families arranging non-medical help at home. ', 2),
            'is_active' => true,
        ]);
        $post = $this->createPublishedBlogPost(['author_id' => $julie->id])['post'];

        $this->get(route('blog.show', ['blogSlug' => $post->slug]))
            ->assertOk()
            ->assertSee('"author":{"@type":"Person","name":"Julie"', false);
    }

    public function test_related_published_guides_are_linked_inside_the_article_context(): void
    {
        config()->set('content.publishing.require_independent_review', false);
        $fixture = $this->createPublishedBlogPost();
        $workflow = app(\App\Services\Content\BlogPostWorkflow::class);
        $snapshot = $workflow->snapshot($fixture['post']);
        $related = $workflow->createDraft($fixture['publisher'], 'Raleigh transportation planning guide');
        $related = $workflow->save(
            $related,
            array_merge($snapshot, [
                'title' => 'Raleigh transportation planning guide',
                'slug' => 'raleigh-transportation-planning-guide',
            ]),
            $fixture['publisher'],
            $snapshot['category_ids'],
            $snapshot['tag_ids'],
            $snapshot['sources'],
        );
        $workflow->publish($related, $fixture['publisher']);

        $hub = $workflow->save(
            $fixture['post'],
            array_merge($snapshot, ['related_post_ids' => [$related->id]]),
            $fixture['publisher'],
            $snapshot['category_ids'],
            $snapshot['tag_ids'],
            $snapshot['sources'],
        );
        $workflow->publish($hub, $fixture['publisher']);

        $this->get(route('blog.show', ['blogSlug' => $hub->slug]))
            ->assertOk()
            ->assertSee('Related local guidance')
            ->assertSee('Raleigh transportation planning guide')
            ->assertSee('data-placement="article_guidance"', false);
    }

    public function test_sitemap_feed_llms_and_robots_expose_only_public_content(): void
    {
        $post = $this->createPublishedBlogPost()['post'];

        $sitemap = $this->get(route('sitemap.xml'))->assertOk();
        $sitemap->assertSee(route('blog.show', ['blogSlug' => $post->slug]), false)
            ->assertDontSee(route('login'), false)
            ->assertDontSee(route('register'), false)
            ->assertSee($post->last_published_at->toAtomString(), false);

        $this->get(route('blog.feed'))->assertOk()->assertSee($post->title)->assertHeader('Content-Type', 'application/atom+xml; charset=UTF-8');
        $this->get(route('llms.txt'))->assertOk()->assertSee($post->title)->assertSee('Only published articles that pass the CMS readiness checks');
        $this->get(route('robots.txt'))->assertOk()->assertSee('User-agent: OAI-SearchBot')->assertSee('User-agent: ChatGPT-User')->assertSee('Disallow: /admin/');
    }

    public function test_old_slug_redirects_to_new_canonical_slug(): void
    {
        $fixture = $this->createPublishedBlogPost();
        $post = $fixture['post'];
        $workflow = app(\App\Services\Content\BlogPostWorkflow::class);
        $snapshot = $workflow->snapshot($post);
        $working = $workflow->save($post, array_merge($snapshot, ['slug' => 'new-canonical-raleigh-guide']), $fixture['publisher'], $snapshot['category_ids'], $snapshot['tag_ids'], $snapshot['sources'], 'Permalink corrected');

        $this->get('/blog/'.$post->slug)->assertOk();
        $this->get('/blog/new-canonical-raleigh-guide')->assertNotFound();

        $working = $workflow->submitForReview($working, $fixture['publisher']);
        $working = $workflow->approveReview($working, $fixture['reviewer_user'], 'The permalink and canonical metadata were independently rechecked.');
        $workflow->publish($working, $fixture['publisher']);

        $this->get('/blog/'.$post->slug)->assertRedirect('/blog/new-canonical-raleigh-guide')->assertStatus(301);
        $this->get('/blog/new-canonical-raleigh-guide')->assertOk();
    }

    public function test_public_hubs_continue_to_use_the_immutable_live_taxonomy_while_a_draft_is_edited(): void
    {
        $fixture = $this->createPublishedBlogPost();
        $workflow = app(\App\Services\Content\BlogPostWorkflow::class);
        $snapshot = $workflow->snapshot($fixture['post']);

        $working = $workflow->save(
            $fixture['post'],
            array_merge($snapshot, ['title' => 'Working title not yet republished']),
            $fixture['publisher'],
            [],
            [],
            $snapshot['sources'],
        );

        $this->get(route('blog.category', $fixture['category']))
            ->assertOk()
            ->assertSee('How to Compare Home Care Options in Raleigh')
            ->assertDontSee('Working title not yet republished');
        $this->get(route('blog.author', $fixture['author']))
            ->assertOk()
            ->assertSee('How to Compare Home Care Options in Raleigh');
        $this->assertSame(\App\Models\BlogPost::STATUS_DRAFT, $working->status);
    }

    public function test_cms_metadata_and_json_ld_escape_hostile_editorial_text(): void
    {
        $hostile = '</title><script>alert("metadata")</script>';
        $post = $this->createPublishedBlogPost([
            'title' => $hostile,
            'seo_title' => $hostile,
            'social_title' => $hostile,
        ])['post'];

        $response = $this->get(route('blog.show', ['blogSlug' => $post->slug]))->assertOk();
        $response->assertDontSee($hostile, false)
            ->assertSee('&lt;/title&gt;&lt;script&gt;alert(&quot;metadata&quot;)&lt;/script&gt;', false)
            ->assertSee('\\u003C/script\\u003E', false);
    }
}
