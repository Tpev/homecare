<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\ContentAuthor;
use App\Models\ContentCategory;
use App\Models\ContentTag;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $entries = [
            ['url' => route('landing'), 'lastmod' => null, 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['url' => route('landing.family'), 'lastmod' => null, 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['url' => route('landing.caregiver'), 'lastmod' => null, 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['url' => route('blog.index'), 'lastmod' => BlogPost::published()->max('last_published_at'), 'priority' => '0.9', 'changefreq' => 'daily'],
        ];

        foreach (array_keys(config('seo_pages.pages', [])) as $slug) {
            $entries[] = ['url' => route('seo.page', ['seoSlug' => $slug]), 'lastmod' => null, 'priority' => '0.8', 'changefreq' => 'monthly'];
        }

        $entries[] = ['url' => route('legal.index'), 'lastmod' => null, 'priority' => '0.4', 'changefreq' => 'yearly'];
        foreach (array_keys(config('legal_pages.pages', [])) as $slug) {
            $entries[] = ['url' => route('legal.show', ['slug' => $slug]), 'lastmod' => null, 'priority' => '0.4', 'changefreq' => 'yearly'];
        }

        $categories = ContentCategory::query()->get();
        $tags = ContentTag::query()->get();
        $categoryDestinations = $categories->mapWithKeys(fn (ContentCategory $category): array => [
            $category->id => (int) ($category->merged_into_id ?: $category->id),
        ]);
        $tagDestinations = $tags->mapWithKeys(fn (ContentTag $tag): array => [
            $tag->id => (int) ($tag->merged_into_id ?: $tag->id),
        ]);
        $categoryDates = [];
        $tagDates = [];
        $authorDates = [];

        BlogPost::published()->with('publishedRevision')->orderBy('id')->each(function (BlogPost $post) use (&$entries, &$categoryDates, &$tagDates, &$authorDates, $categoryDestinations, $tagDestinations): void {
            $publicSlug = (string) data_get($post->publishedRevision?->snapshot, 'slug', $post->slug);
            $lastModified = $this->latestDate($post->publishedRevision?->created_at, $post->last_published_at);
            $entries[] = [
                'url' => route('blog.show', ['blogSlug' => $publicSlug]),
                'lastmod' => $lastModified,
                'priority' => '0.8',
                'changefreq' => 'monthly',
            ];

            $snapshot = (array) $post->publishedRevision?->snapshot;
            foreach ((array) ($snapshot['category_ids'] ?? []) as $id) {
                $this->recordLatest($categoryDates, (int) ($categoryDestinations[(int) $id] ?? $id), $lastModified);
            }
            foreach ((array) ($snapshot['tag_ids'] ?? []) as $id) {
                $this->recordLatest($tagDates, (int) ($tagDestinations[(int) $id] ?? $id), $lastModified);
            }
            foreach (array_filter([$snapshot['author_id'] ?? null, $snapshot['reviewer_id'] ?? null]) as $id) {
                $this->recordLatest($authorDates, (int) $id, $lastModified);
            }
        });

        $categories->filter(fn (ContentCategory $category): bool => $category->is_active && ! $category->merged_into_id && isset($categoryDates[$category->id]))->each(function (ContentCategory $category) use (&$entries, $categoryDates): void {
            $entries[] = [
                'url' => route('blog.category', $category),
                'lastmod' => $this->latestDate($category->updated_at, $categoryDates[$category->id]),
                'priority' => '0.7', 'changefreq' => 'weekly',
            ];
        });

        $tags->filter(fn (ContentTag $tag): bool => ! $tag->merged_into_id && isset($tagDates[$tag->id]))->each(function (ContentTag $tag) use (&$entries, $tagDates): void {
            $entries[] = [
                'url' => route('blog.tag', $tag),
                'lastmod' => $this->latestDate($tag->updated_at, $tagDates[$tag->id]),
                'priority' => '0.6', 'changefreq' => 'weekly',
            ];
        });

        ContentAuthor::query()->whereIn('id', array_keys($authorDates))->get()->each(function (ContentAuthor $author) use (&$entries, $authorDates): void {
            $entries[] = [
                'url' => route('blog.author', $author),
                'lastmod' => $this->latestDate($author->updated_at, $authorDates[$author->id]),
                'priority' => '0.6', 'changefreq' => 'monthly',
            ];
        });

        $xml = view('marketing.sitemap', [
            'entries' => collect($entries)->unique('url')->values(),
        ])->render();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=900',
        ]);
    }

    private function latestDate(mixed ...$dates): ?Carbon
    {
        return collect($dates)
            ->filter()
            ->map(fn ($date): Carbon => Carbon::parse($date))
            ->sortDesc()
            ->first();
    }

    /** @param array<int,Carbon> $dates */
    private function recordLatest(array &$dates, int $id, mixed $date): void
    {
        if ($id < 1 || ! $date) {
            return;
        }
        $candidate = Carbon::parse($date);
        if (! isset($dates[$id]) || $candidate->gt($dates[$id])) {
            $dates[$id] = $candidate;
        }
    }
}
