<?php

namespace Tests\Feature\Marketing;

use App\Services\Content\BlogContentService;
use Tests\TestCase;

class SeoPagesTest extends TestCase
{
    public function test_all_configured_seo_pages_render_successfully(): void
    {
        $pages = config('seo_pages.pages', []);
        $this->assertNotEmpty($pages);

        foreach (array_keys($pages) as $slug) {
            $this->get(route('seo.page', ['seoSlug' => $slug]))->assertOk();
        }
    }

    public function test_seo_page_includes_core_registration_ctas_and_related_links(): void
    {
        $response = $this->get(route('seo.page', ['seoSlug' => 'raleigh-home-care']));

        $response->assertSee(route('register'), false);
        $response->assertSee(route('caregiver.register'), false);
        $response->assertSee('/raleigh-companion-care', false);
        $response->assertSee('Raleigh home care FAQ');
        $response->assertSee('aria-label="Breadcrumb"', false);
        $response->assertSee('"@type":"BreadcrumbList"', false);
        $response->assertSeeText('Raleigh Care Guides');
    }

    public function test_blog_pages_have_visible_breadcrumbs_backed_by_matching_schema(): void
    {
        $index = $this->get(route('blog.index'));
        $index->assertOk()
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSeeText('Blog');

        $post = app(BlogContentService::class)->all()[0];
        $article = $this->get(route('blog.show', ['blogSlug' => $post['slug']]));
        $article->assertOk()
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSeeText($post['title'])
            ->assertSee('"name":"LoLo Care"', false);
    }

    public function test_sitemap_robots_and_llms_files_are_exposed_for_crawlers(): void
    {
        $this->get(route('sitemap.xml'))
            ->assertOk()
            ->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false)
            ->assertSee(route('landing'), false)
            ->assertSee('/raleigh-home-care', false);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('User-agent: *')
            ->assertSee('Disallow: /admin/')
            ->assertSee('/sitemap.xml');

        $this->get('/llms.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('# LoLo Care')
            ->assertSee('https://carelolo.com/families')
            ->assertSee('https://carelolo.com/legal/privacy-policy');
    }
}
