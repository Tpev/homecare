<?php

namespace App\Services\Content;

use App\Models\BlogPost;
use App\Models\MediaAsset;
use Illuminate\Support\Str;

class BlogPostReadiness
{
    private const CHECKLIST = [
        'originality_confirmed' => 'Original value and first-hand usefulness confirmed',
        'facts_verified' => 'Material facts and claims verified',
        'sources_verified' => 'Sources opened and verified',
        'medical_claims_reviewed' => 'Health and medical boundary reviewed',
        'privacy_claims_reviewed' => 'Privacy and product-capability claims reviewed',
        'brand_reviewed' => 'Brand, competitor, and positioning language reviewed',
        'accessibility_checked' => 'Headings, links, images, and tables checked for accessibility',
    ];

    /** @return array{issues:list<string>,warnings:list<string>,checks:array<string,string>,ready:bool} */
    public function inspect(BlogPost $post): array
    {
        $post->loadMissing(['author', 'reviewer', 'featuredMedia', 'categories', 'sources']);
        $issues = [];
        $warnings = [];

        if (trim($post->title) === '') {
            $issues[] = 'Add a descriptive article title.';
        }
        if (trim($post->slug) === '') {
            $issues[] = 'Set a stable permalink.';
        }
        if (trim((string) $post->excerpt) === '' || mb_strlen((string) $post->excerpt) < 80) {
            $issues[] = 'Write an 80+ character reader-focused excerpt.';
        }
        if ($post->word_count < 150) {
            $issues[] = 'The article needs at least 150 meaningful words.';
        }
        if (! $post->author) {
            $issues[] = 'Assign a public author.';
        } elseif (! $post->author->is_active || mb_strlen(trim((string) $post->author->bio)) < 60) {
            $issues[] = 'The public author needs an active profile with a substantive bio.';
        }
        if (! $post->reviewer || ! $post->reviewed_at) {
            $issues[] = 'Complete an independent editorial review.';
        } elseif ((int) $post->reviewer_id === (int) $post->author_id) {
            $issues[] = 'The author and independent reviewer must be different people.';
        } elseif (! $post->reviewer->is_active
            || mb_strlen(trim((string) $post->reviewer->bio)) < 60
            || trim((string) $post->reviewer->credentials) === ''
        ) {
            $issues[] = 'The reviewer needs an active profile, substantive bio, and visible review credentials.';
        }
        if (! $post->featuredMedia) {
            $issues[] = 'Choose a managed featured image.';
        } elseif (trim((string) $post->featuredMedia->alt_text) === '') {
            $issues[] = 'Add alt text to the featured image.';
        } elseif (trim((string) $post->featuredMedia->license) === '') {
            $issues[] = 'Record the featured image license or ownership basis.';
        }
        if ($post->categories->isEmpty()) {
            $issues[] = 'Assign at least one content category.';
        }
        if (trim((string) $post->seo_title) === '') {
            $issues[] = 'Write an SEO title.';
        } elseif (mb_strlen((string) $post->seo_title) > 65) {
            $warnings[] = 'The SEO title is longer than 65 characters.';
        }
        if (trim((string) $post->meta_description) === '') {
            $issues[] = 'Write a meta description.';
        } elseif (mb_strlen((string) $post->meta_description) > 165) {
            $warnings[] = 'The meta description is longer than 165 characters.';
        }
        if (in_array($post->content_type, ['guide', 'research', 'case-study'], true) && $post->sources->isEmpty()) {
            $issues[] = 'Guides, research, and case studies must cite at least one verifiable source.';
        }
        if (in_array($post->content_type, ['research', 'case-study'], true)
            && mb_strlen(trim((string) $post->research_methodology)) < 100
        ) {
            $issues[] = 'Original research and case studies need a transparent 100+ character methodology.';
        }

        foreach ($this->documentIssues(
            (array) $post->content_json,
            $post->sources->pluck('uuid')->filter()->values()->all(),
        ) as $issue) {
            $issues[] = $issue;
        }

        foreach ($post->sources as $source) {
            if (! in_array(strtolower((string) parse_url($source->url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                $issues[] = 'Every source needs a valid HTTP or HTTPS URL.';
            }
            if (! $source->accessed_on || $source->accessed_on->isFuture()) {
                $issues[] = 'Every source needs a truthful access date that is not in the future.';
            }
            if ($source->published_on?->isFuture()) {
                $issues[] = 'Source publication dates cannot be in the future.';
            }
        }

        $canonical = trim((string) $post->canonical_url);
        if ($canonical !== '') {
            $canonicalHost = strtolower((string) parse_url($canonical, PHP_URL_HOST));
            $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
            if ($canonicalHost === '' || $canonicalHost !== $appHost || parse_url($canonical, PHP_URL_FRAGMENT) !== null) {
                $issues[] = "Canonical overrides must use this site's host and cannot contain a fragment.";
            }
        }

        $checklist = (array) $post->editorial_checklist;
        foreach (self::CHECKLIST as $key => $label) {
            if (($checklist[$key] ?? false) !== true) {
                $issues[] = $label.'.';
            }
        }

        if (Str::contains(Str::lower((string) $post->plain_text), ['hipaa', 'medical', 'background check', 'licensed', 'guarantee']) && $post->sources->count() < 2) {
            $warnings[] = 'High-trust claims should include multiple authoritative sources and an appropriately qualified reviewer.';
        }

        return [
            'issues' => array_values(array_unique($issues)),
            'warnings' => array_values(array_unique($warnings)),
            'checks' => self::CHECKLIST,
            'ready' => $issues === [],
        ];
    }

    public static function checklist(): array
    {
        return self::CHECKLIST;
    }

    /** @return list<string> */
    private function documentIssues(array $node, array $sourceKeys): array
    {
        $issues = [];
        $assetIds = $this->documentAssetIds($node);
        $assets = MediaAsset::query()->whereIn('id', $assetIds)->get()->keyBy('id');
        $walk = function (array $current) use (&$walk, &$issues, $sourceKeys, $assets): void {
            if (($current['type'] ?? null) === 'image') {
                $asset = $assets->get((int) data_get($current, 'attrs.assetId'));
                if (! $asset) {
                    $issues[] = 'Replace an article image that is missing from managed media.';
                } elseif (trim((string) (data_get($current, 'attrs.alt') ?: $asset->alt_text)) === '') {
                    $issues[] = 'Add alt text to every image embedded in the article.';
                }
            }
            if (($current['type'] ?? null) === 'citation') {
                $sourceKey = (string) data_get($current, 'attrs.sourceKey', '');
                $sourceId = (int) data_get($current, 'attrs.sourceId');
                $stableReferenceIsValid = $sourceKey !== '' && in_array($sourceKey, $sourceKeys, true);
                $legacyReferenceIsValid = $sourceKey === '' && $sourceId >= 1 && $sourceId <= count($sourceKeys);
                if (! $stableReferenceIsValid && ! $legacyReferenceIsValid) {
                    $issues[] = 'Fix a citation that does not point to an existing source number.';
                }
            }
            foreach ((array) ($current['content'] ?? []) as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };
        $walk($node);

        return array_values(array_unique($issues));
    }

    /** @return list<int> */
    private function documentAssetIds(array $node): array
    {
        $ids = [];
        $walk = function (array $current) use (&$walk, &$ids): void {
            if (($current['type'] ?? null) === 'image') {
                $ids[] = (int) data_get($current, 'attrs.assetId');
            }
            foreach ((array) ($current['content'] ?? []) as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };
        $walk($node);

        return array_values(array_unique(array_filter($ids)));
    }
}
