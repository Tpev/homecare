<?php

namespace Tests\Feature\Marketing;

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
    }

    public function test_sitemap_and_robots_are_exposed_for_crawlers(): void
    {
        $this->get(route('sitemap.xml'))
            ->assertOk()
            ->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false)
            ->assertSee(route('landing'), false)
            ->assertSee('/raleigh-home-care', false);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('User-agent: *')
            ->assertSee('/sitemap.xml');
    }
}
