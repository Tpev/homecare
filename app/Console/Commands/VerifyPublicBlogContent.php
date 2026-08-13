<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class VerifyPublicBlogContent extends Command
{
    protected $signature = 'content:verify-public
        {--fail-on-issues : Return a failing exit code when a public article cannot be verified}';

    protected $description = 'Verify public articles, discovery files, canonical metadata, schema, and internal links';

    public function handle(): int
    {
        try {
            $sitemap = $this->get(route('sitemap.xml'));
            $llms = $this->get(route('llms.txt'));
        } catch (Throwable $exception) {
            $this->error('Discovery files could not be fetched: '.$exception->getMessage());

            return $this->option('fail-on-issues') ? self::FAILURE : self::SUCCESS;
        }

        $posts = BlogPost::published()->with('publishedRevision')->orderBy('id')->get();
        $linkChecks = [];
        $rows = [];
        $issueCount = 0;

        foreach ($posts as $post) {
            $snapshot = (array) $post->publishedRevision?->snapshot;
            $slug = (string) ($snapshot['slug'] ?? $post->slug);
            $url = route('blog.show', ['blogSlug' => $slug]);
            $issues = [];

            try {
                $page = $this->get($url);
                $html = $page->body();
                if (! $page->successful()) {
                    $issues[] = 'HTTP '.$page->status();
                }
                if (! $this->hasCanonical($html, $url)) {
                    $issues[] = 'canonical mismatch';
                }
                if (! preg_match('/"@type"\s*:\s*"(?:BlogPosting|Article|NewsArticle)"/', $html)) {
                    $issues[] = 'article schema missing';
                }
            } catch (Throwable $exception) {
                $issues[] = 'page fetch failed: '.$exception->getMessage();
            }

            if (! str_contains($sitemap->body(), $url)) {
                $issues[] = 'missing from sitemap';
            }
            if (! str_contains($llms->body(), $url)) {
                $issues[] = 'missing from llms.txt';
            }

            foreach ($this->internalLinks((string) ($snapshot['body_html'] ?? '')) as $href) {
                $target = url($href);
                if (! array_key_exists($target, $linkChecks)) {
                    try {
                        $linkChecks[$target] = $this->get($target)->successful();
                    } catch (Throwable) {
                        $linkChecks[$target] = false;
                    }
                }
                if (! $linkChecks[$target]) {
                    $issues[] = 'broken internal link: '.$href;
                }
            }

            $issues = array_values(array_unique($issues));
            $issueCount += count($issues);
            $rows[] = [$post->id, $slug, $issues === [] ? 'OK' : implode('; ', $issues)];
        }

        $this->table(['ID', 'Public slug', 'Verification'], $rows);
        $this->info("Verified {$posts->count()} public article(s), ".count($linkChecks)." unique internal link(s), and {$issueCount} issue(s).");

        return $issueCount > 0 && $this->option('fail-on-issues') ? self::FAILURE : self::SUCCESS;
    }

    private function get(string $url): Response
    {
        return Http::accept('text/html,application/xml,text/plain')
            ->timeout(10)
            ->retry(2, 250)
            ->get($url);
    }

    private function hasCanonical(string $html, string $expected): bool
    {
        return preg_match('/<link\b(?=[^>]*\brel=["\']canonical["\'])(?=[^>]*\bhref=["\']'.preg_quote($expected, '/').'["\'])[^>]*>/i', $html) === 1
            || preg_match('/<link\b(?=[^>]*\bhref=["\']'.preg_quote($expected, '/').'["\'])(?=[^>]*\brel=["\']canonical["\'])[^>]*>/i', $html) === 1;
    }

    /** @return list<string> */
    private function internalLinks(string $html): array
    {
        preg_match_all('/<a\b[^>]*\bhref=["\']([^"\']+)["\']/i', $html, $matches);

        return collect($matches[1] ?? [])
            ->filter(fn ($href): bool => is_string($href) && str_starts_with($href, '/') && ! str_starts_with($href, '//'))
            ->map(fn (string $href): string => strtok($href, '#') ?: $href)
            ->unique()
            ->values()
            ->all();
    }
}
