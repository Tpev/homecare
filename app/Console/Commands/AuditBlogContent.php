<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Models\ContentAuthor;
use App\Models\MediaAsset;
use App\Services\Content\BlogPostReadiness;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class AuditBlogContent extends Command
{
    protected $signature = 'content:audit
        {--fail-on-issues : Return a failing exit code when public or scheduled content has governance issues}
        {--check-links : Verify that published source URLs still return a successful HTTP response}';

    protected $description = 'Audit public blog revisions for review freshness, authorship, sources, media, metadata, and editorial sign-off';

    public function handle(BlogPostReadiness $readiness): int
    {
        $posts = BlogPost::query()
            ->whereNotNull('published_revision_id')
            ->where('status', '!=', BlogPost::STATUS_ARCHIVED)
            ->with('publishedRevision')
            ->orderBy('title')
            ->get();

        $rows = [];
        $issueCount = 0;

        foreach ($posts as $post) {
            $snapshot = (array) ($post->publishedRevision?->snapshot ?? []);
            $issues = $this->issues($post, $snapshot);
            $issueCount += count($issues);
            $rows[] = [
                $post->id,
                $post->title,
                $post->status,
                $post->content_review_due_at?->toDateString() ?? 'not set',
                $issues === [] ? 'OK' : implode('; ', $issues),
            ];
        }

        $scheduled = BlogPost::query()
            ->where('status', BlogPost::STATUS_SCHEDULED)
            ->with(['author', 'reviewer', 'featuredMedia', 'categories', 'sources'])
            ->orderBy('scheduled_for')
            ->get();
        foreach ($scheduled as $post) {
            $issues = $readiness->inspect($post)['issues'];
            $issueCount += count($issues);
            $rows[] = [
                $post->id,
                $post->title,
                'scheduled',
                $post->scheduled_for?->toDateTimeString() ?? 'not set',
                $issues === [] ? 'OK' : implode('; ', $issues),
            ];
        }

        $this->table(['ID', 'Article', 'Workflow', 'Review due', 'Audit'], $rows);

        $quarantined = BlogPost::query()
            ->whereNotNull('source_import')
            ->whereNull('published_revision_id')
            ->count();
        $this->info("Audited {$posts->count()} live and {$scheduled->count()} scheduled article(s); {$issueCount} issue(s); {$quarantined} legacy import(s) remain quarantined.");

        return $issueCount > 0 && $this->option('fail-on-issues') ? self::FAILURE : self::SUCCESS;
    }

    /** @param array<string,mixed> $snapshot @return list<string> */
    private function issues(BlogPost $post, array $snapshot): array
    {
        $issues = [];
        $check = static function (bool $condition, string $message) use (&$issues): void {
            if ($condition) {
                $issues[] = $message;
            }
        };

        $check(! $post->robots_index, 'not indexable');
        $check(! $post->first_published_at || ! $post->last_published_at, 'missing truthful publication dates');
        $check(empty($snapshot['reviewed_at']), 'missing review date');
        $check(! $post->content_review_due_at, 'missing next review date');
        $check($post->content_review_due_at?->isPast() === true, 'review overdue');
        $check(empty($snapshot['title']) || empty($snapshot['excerpt']), 'missing title or excerpt');
        $check(empty($snapshot['author_id']), 'missing public author');
        $check(empty($snapshot['reviewer_id']), 'missing independent reviewer');
        $author = ! empty($snapshot['author_id']) ? ContentAuthor::query()->find($snapshot['author_id']) : null;
        $reviewer = ! empty($snapshot['reviewer_id']) ? ContentAuthor::query()->find($snapshot['reviewer_id']) : null;
        $check(! $author || ! $author->is_active || mb_strlen(trim((string) $author->bio)) < 60, 'author profile is inactive or incomplete');
        $check(! $reviewer || ! $reviewer->is_active || mb_strlen(trim((string) $reviewer->bio)) < 60 || trim((string) $reviewer->credentials) === '', 'reviewer profile is inactive or incomplete');
        $featuredMedia = ! empty($snapshot['featured_media_asset_id'])
            ? MediaAsset::query()->find($snapshot['featured_media_asset_id'])
            : null;
        $check(! $featuredMedia, 'missing featured media');
        $check($featuredMedia && trim((string) $featuredMedia->alt_text) === '', 'featured media missing alt text');
        $check(empty($snapshot['seo_title']) || empty($snapshot['meta_description']), 'missing search metadata');
        $check(empty($snapshot['category_ids']), 'missing category');
        $check(($snapshot['content_type'] ?? 'guide') === 'guide' && empty($snapshot['sources']), 'guide has no sources');
        $check(count(array_filter((array) ($snapshot['editorial_checklist'] ?? []))) < 7, 'editorial checklist incomplete');
        foreach ((array) ($snapshot['sources'] ?? []) as $source) {
            $url = (string) ($source['url'] ?? '');
            $check(! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true), 'source has an invalid URL');
            if ($this->option('check-links') && $url !== '') {
                try {
                    $check(! Http::timeout(5)->head($url)->successful(), 'source URL is not reachable: '.$url);
                } catch (\Throwable) {
                    $issues[] = 'source URL could not be checked: '.$url;
                }
            }
        }
        $mediaIds = $this->documentMediaIds((array) ($snapshot['content_json'] ?? []));
        $availableMedia = MediaAsset::query()->whereIn('id', $mediaIds)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $check(array_diff($mediaIds, $availableMedia) !== [], 'embedded article media is missing');

        return $issues;
    }

    /** @return list<int> */
    private function documentMediaIds(array $node): array
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
