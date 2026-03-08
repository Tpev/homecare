<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class SeoPagesController extends Controller
{
    public function show(string $seoSlug): View
    {
        /** @var array<string,array<string,mixed>> $pages */
        $pages = config('seo_pages.pages', []);
        abort_unless(isset($pages[$seoSlug]), 404);

        $page = $pages[$seoSlug];
        $page['slug'] = $seoSlug;
        $page['path'] = $page['path'] ?? '/'.$seoSlug;

        $allPages = collect($pages)->map(
            fn (array $entry, string $slug) => [
                'slug' => $slug,
                'path' => $entry['path'] ?? '/'.$slug,
                'title' => $entry['h1'] ?? ($entry['meta_title'] ?? $slug),
            ]
        )->values()->all();

        $relatedPages = collect($allPages)
            ->reject(fn (array $entry) => $entry['slug'] === $seoSlug)
            ->take(8)
            ->values()
            ->all();

        return view('marketing.seo-page', [
            'page' => $page,
            'allPages' => $allPages,
            'relatedPages' => $relatedPages,
        ]);
    }
}

