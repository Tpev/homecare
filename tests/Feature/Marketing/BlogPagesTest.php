<?php

namespace Tests\Feature\Marketing;

use App\Services\Content\BlogContentService;
use Tests\TestCase;

class BlogPagesTest extends TestCase
{
    public function test_blog_index_renders_and_links_posts_when_available(): void
    {
        $posts = app(BlogContentService::class)->all();

        $response = $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee('Raleigh Home Care Blog');

        if ($posts !== []) {
            $response->assertSee($posts[0]['title']);
            $response->assertSee(route('blog.show', ['blogSlug' => $posts[0]['slug']]), false);
        }
    }

    public function test_blog_post_page_renders_docx_content_when_available(): void
    {
        $posts = app(BlogContentService::class)->all();
        if ($posts === []) {
            $this->assertTrue(true);

            return;
        }

        $post = $posts[0];

        $response = $this->get(route('blog.show', ['blogSlug' => $post['slug']]))
            ->assertOk()
            ->assertSee($post['title'])
            ->assertSee(route('register'), false)
            ->assertSee('/trusted-caregiver-screening', false)
            ->assertSee('/how-homecare-works-raleigh', false);

        if (($post['paragraphs'] ?? []) !== []) {
            $response->assertSee($post['paragraphs'][0]);
        }
    }

    public function test_sitemap_includes_blog_urls(): void
    {
        $posts = app(BlogContentService::class)->all();

        $response = $this->get(route('sitemap.xml'))
            ->assertOk()
            ->assertSee(route('blog.index'), false);

        if ($posts !== []) {
            $response->assertSee(route('blog.show', ['blogSlug' => $posts[0]['slug']]), false);
        }
    }
}
