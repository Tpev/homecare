<?php

namespace Tests\Feature\Marketing;

use App\Jobs\SubmitIndexNowUrls;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\CreatesPublishedBlogPosts;
use Tests\TestCase;

class SeoPagesTest extends TestCase
{
    use CreatesPublishedBlogPosts, RefreshDatabase;

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
            ->assertSeeText('Resources');

        $post = $this->createPublishedBlogPost()['post'];
        $article = $this->get(route('blog.show', ['blogSlug' => $post['slug']]));
        $article->assertOk()
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSeeText($post->title)
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
            ->assertSee(route('landing.family'), false)
            ->assertSee(route('legal.show', ['slug' => 'privacy-policy']), false);
    }

    public function test_indexnow_has_a_stable_public_host_key_and_submits_same_host_urls(): void
    {
        config()->set('app.url', 'https://carelolo.test');
        config()->set('services.indexnow.key', null);
        config()->set('services.indexnow.derive_host_key', true);
        URL::forceRootUrl('https://carelolo.test');
        URL::forceScheme('https');
        $expectedKey = hash('sha256', 'indexnow:carelolo.test');

        $this->get(route('indexnow.key'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertContent($expectedKey);

        Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);
        app()->call([new SubmitIndexNowUrls([
            'https://carelolo.test/blog/local-guide',
            'https://carelolo.test/sitemap.xml',
            'https://unrelated.example/page',
        ]), 'handle']);

        $request = Http::recorded()->sole()[0];
        $this->assertSame('https://api.indexnow.org/indexnow', $request->url());
        $this->assertSame([
            'host' => 'carelolo.test',
            'key' => $expectedKey,
            'keyLocation' => 'https://carelolo.test/indexnow-key.txt',
            'urlList' => [
                'https://carelolo.test/blog/local-guide',
                'https://carelolo.test/sitemap.xml',
            ],
        ], $request->data());
    }
}
