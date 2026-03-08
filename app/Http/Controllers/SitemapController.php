<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $baseUrls = [
            route('landing'),
            route('landing.family'),
            route('landing.caregiver'),
            route('landing.agency'),
            route('register'),
            route('caregiver.register'),
            route('login'),
        ];

        $seoUrls = collect(array_keys(config('seo_pages.pages', [])))
            ->map(fn (string $slug) => route('seo.page', ['seoSlug' => $slug]))
            ->all();

        $urls = collect(array_merge($baseUrls, $seoUrls))
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

