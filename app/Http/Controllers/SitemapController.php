<?php

namespace App\Http\Controllers;

use App\Services\Content\BlogContentService;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(BlogContentService $blogs): Response
    {
        $baseUrls = [
            route('landing'),
            route('landing.family'),
            route('landing.caregiver'),
            route('landing.agency'),
            route('blog.index'),
            route('register'),
            route('caregiver.register'),
            route('login'),
        ];

        $seoUrls = collect(array_keys(config('seo_pages.pages', [])))
            ->map(fn (string $slug) => route('seo.page', ['seoSlug' => $slug]))
            ->all();

        $blogUrls = collect($blogs->all())
            ->map(fn (array $post) => route('blog.show', ['blogSlug' => (string) $post['slug']]))
            ->all();

        $urls = collect(array_merge($baseUrls, $seoUrls, $blogUrls))
            ->unique()
            ->values();

        $today = now()->toDateString();

        $xml = view('marketing.sitemap', [
            'urls' => $urls,
            'today' => $today,
        ])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
