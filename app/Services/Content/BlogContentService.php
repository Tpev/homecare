<?php

namespace App\Services\Content;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

class BlogContentService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return Cache::remember($this->cacheKey(), now()->addHours(6), function (): array {
            return $this->buildPosts();
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        foreach ($this->all() as $post) {
            if (($post['slug'] ?? null) === $slug) {
                return $post;
            }
        }

        return null;
    }

    /**
     * @return array{content:string,mime:string}|null
     */
    public function coverBinaryForSlug(string $slug): ?array
    {
        $post = $this->findBySlug($slug);
        if (! $post) {
            return null;
        }

        $sourceFile = (string) ($post['source_file'] ?? '');
        $mediaPath = (string) ($post['cover_media_path'] ?? '');
        if ($sourceFile === '' || $mediaPath === '') {
            return null;
        }

        $docxPath = base_path('blogs'.DIRECTORY_SEPARATOR.$sourceFile);
        if (! File::exists($docxPath)) {
            return null;
        }

        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return null;
        }

        $binary = $zip->getFromName($mediaPath);
        $zip->close();

        if (! is_string($binary) || $binary === '') {
            return null;
        }

        $extension = strtolower(pathinfo($mediaPath, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };

        return [
            'content' => $binary,
            'mime' => $mime,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildPosts(): array
    {
        $blogPath = base_path('blogs');
        if (! File::isDirectory($blogPath)) {
            return [];
        }

        $files = collect(File::files($blogPath))
            ->filter(fn ($file) => strtolower($file->getExtension()) === 'docx')
            ->sortBy(fn ($file) => strtolower($file->getFilename()))
            ->values();

        $posts = [];
        $slugCounts = [];

        foreach ($files as $file) {
            $parsed = $this->parseDocx($file->getPathname());
            $frontMatter = $this->extractFrontMatter($parsed['paragraphs']);

            $fileTitle = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $rawTitle = $frontMatter['title'] ?: ($parsed['title'] ?: $fileTitle);
            $title = $this->normalizeTitle($rawTitle);

            $baseSlug = Str::slug($title);
            if ($baseSlug === '') {
                $baseSlug = Str::slug($fileTitle);
            }

            $slugCounts[$baseSlug] = ($slugCounts[$baseSlug] ?? 0) + 1;
            $slug = $slugCounts[$baseSlug] > 1
                ? $baseSlug.'-'.$slugCounts[$baseSlug]
                : $baseSlug;

            $paragraphs = $this->normalizeParagraphs($frontMatter['paragraphs']);
            if ($paragraphs !== [] && Str::lower($paragraphs[0]) === Str::lower($title)) {
                array_shift($paragraphs);
            }
            if ($paragraphs === []) {
                $paragraphs = [
                    'This guide explains practical non-medical home care considerations for Raleigh families and caregivers.',
                    'Use this page as a starting point, then post a request or sign up as a caregiver to take action quickly.',
                ];
            }

            $excerpt = trim(Str::limit(implode(' ', array_slice($paragraphs, 0, 3)), 160, ''));
            if (! $this->isSeoRelevant($title, $excerpt, $paragraphs)) {
                continue;
            }

            $wordCount = str_word_count(implode(' ', $paragraphs));
            $readMinutes = max(1, (int) ceil($wordCount / 220));
            $topics = $this->detectTopics($title, $excerpt, $paragraphs);

            $posts[] = [
                'slug' => $slug,
                'path' => '/blog/'.$slug,
                'source_file' => $file->getFilename(),
                'title' => $title,
                'meta_title' => $this->metaTitle($frontMatter['meta_title'] ?: $title),
                'meta_description' => $this->metaDescription($frontMatter['meta_description'] ?: $excerpt),
                'excerpt' => $excerpt,
                'paragraphs' => $paragraphs,
                'topics' => $topics,
                'cover_image' => $this->resolveCoverImage($slug, $parsed['first_media_path'], $topics),
                'cover_media_path' => $parsed['first_media_path'],
                'word_count' => $wordCount,
                'read_minutes' => $readMinutes,
                'published_at' => now()->toDateString(),
            ];
        }

        $posts = $this->attachInternalLinks($posts);

        return collect($posts)
            ->sortBy('title')
            ->values()
            ->all();
    }

    /**
     * @return array{title:string,paragraphs:list<string>,first_media_path:string|null}
     */
    private function parseDocx(string $path): array
    {
        $zip = new ZipArchive();
        $opened = $zip->open($path);
        if ($opened !== true) {
            return [
                'title' => '',
                'paragraphs' => [],
                'first_media_path' => null,
            ];
        }

        $documentXml = $zip->getFromName('word/document.xml');
        $paragraphs = [];
        $title = '';

        if (is_string($documentXml) && $documentXml !== '') {
            $dom = new \DOMDocument();
            $loaded = @$dom->loadXML($documentXml);
            if ($loaded) {
                $xpath = new \DOMXPath($dom);
                $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

                $nodes = $xpath->query('//w:p');
                if ($nodes !== false) {
                    foreach ($nodes as $node) {
                        $textNodes = $xpath->query('.//w:t', $node);
                        if ($textNodes === false) {
                            continue;
                        }

                        $line = '';
                        foreach ($textNodes as $textNode) {
                            $line .= $textNode->textContent;
                        }

                        $line = preg_replace('/\s+/u', ' ', trim($line));
                        if (! is_string($line) || $line === '') {
                            continue;
                        }

                        if ($title === '') {
                            $title = $line;
                        }

                        $paragraphs[] = $line;
                    }
                }
            }
        }

        $firstMediaPath = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! is_string($name)) {
                continue;
            }

            if (preg_match('/^word\/media\/.+\.(jpg|jpeg|png|webp|gif)$/i', $name) === 1) {
                $firstMediaPath = $name;
                break;
            }
        }

        $zip->close();

        return [
            'title' => $title,
            'paragraphs' => $paragraphs,
            'first_media_path' => $firstMediaPath,
        ];
    }

    /**
     * @param  list<string>  $paragraphs
     * @return array{title:string,meta_title:string,meta_description:string,paragraphs:list<string>}
     */
    private function extractFrontMatter(array $paragraphs): array
    {
        $title = '';
        $metaTitle = '';
        $metaDescription = '';
        $clean = [];

        foreach ($paragraphs as $line) {
            $normalized = trim((string) $line);
            if ($normalized === '') {
                continue;
            }

            $lower = Str::lower($normalized);

            if (preg_match('/^meta\s*title\s*:\s*(.+)$/i', $normalized, $matches) === 1) {
                $metaTitle = trim($matches[1]);
                if ($title === '') {
                    $title = $metaTitle;
                }
                continue;
            }

            if (preg_match('/^meta\s*description\s*:\s*(.+)$/i', $normalized, $matches) === 1) {
                $metaDescription = trim($matches[1]);
                continue;
            }

            if (preg_match('/^title\s*:\s*(.+)$/i', $normalized, $matches) === 1) {
                $title = trim($matches[1]);
                continue;
            }

            if (Str::startsWith($lower, ['focus keyword:', 'slug:', 'image alt:', 'seo title:'])) {
                continue;
            }

            $clean[] = $normalized;
        }

        return [
            'title' => $title,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'paragraphs' => $clean,
        ];
    }

    /**
     * @param  list<string>  $paragraphs
     * @return list<string>
     */
    private function normalizeParagraphs(array $paragraphs): array
    {
        return collect($paragraphs)
            ->map(function (string $line): string {
                $line = trim($line);
                $line = preg_replace('/^(#+|\*+|\-+)\s*/', '', $line) ?: $line;
                $line = preg_replace('/\s+/u', ' ', $line) ?: $line;
                return trim($line);
            })
            ->filter(fn (string $line) => $line !== '' && mb_strlen($line) > 20)
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeTitle(string $rawTitle): string
    {
        $clean = preg_replace('/\s+/u', ' ', trim($rawTitle));
        if (! is_string($clean) || $clean === '') {
            return 'Home Care Guide';
        }

        $clean = str_replace(['_', '–', '—'], [' ', '-', '-'], $clean);
        $clean = preg_replace('/\(\d+\)$/', '', trim($clean)) ?: $clean;

        return Str::of($clean)
            ->replace('prolbem', 'problem')
            ->replace('care givers', 'caregivers')
            ->title()
            ->toString();
    }

    private function metaTitle(string $title): string
    {
        return Str::limit($title.' | Raleigh Home Care Guide | HomeCare', 65, '');
    }

    private function metaDescription(string $excerpt): string
    {
        $description = $excerpt !== ''
            ? $excerpt
            : 'Read this Raleigh home care guide from HomeCare to help families and caregivers make faster, clearer decisions.';

        if (! Str::contains(Str::lower($description), 'raleigh')) {
            $description = 'Raleigh, NC guide: '.$description;
        }

        return Str::limit($description, 155, '');
    }

    /**
     * @param  list<string>  $topics
     */
    private function resolveCoverImage(string $slug, ?string $firstMediaPath, array $topics): string
    {
        if ($firstMediaPath) {
            return route('blog.cover', ['blogSlug' => $slug]);
        }

        return $this->fallbackImageForTopics($topics);
    }

    /**
     * @param  list<string>  $topics
     */
    private function fallbackImageForTopics(array $topics): string
    {
        $topic = $topics[0] ?? 'home care';
        $map = [
            'surgery recovery' => 'https://images.unsplash.com/photo-1579154204601-01588f351e67?auto=format&fit=crop&w=1600&q=80',
            'companion care' => 'https://images.unsplash.com/photo-1516302752625-fcc3c50ae61f?auto=format&fit=crop&w=1600&q=80',
            'home care jobs' => 'https://images.unsplash.com/photo-1526256262350-7da7584cf5eb?auto=format&fit=crop&w=1600&q=80',
            'durham' => 'https://images.unsplash.com/photo-1462604556075-979b380b7b54?auto=format&fit=crop&w=1600&q=80',
            'raleigh' => 'https://images.unsplash.com/photo-1469474968028-56623f02e42e?auto=format&fit=crop&w=1600&q=80',
        ];

        return $map[$topic] ?? 'https://images.unsplash.com/photo-1505751172876-fa1923c5c528?auto=format&fit=crop&w=1600&q=80';
    }

    /**
     * @param  list<string>  $paragraphs
     * @return list<string>
     */
    private function detectTopics(string $title, string $excerpt, array $paragraphs): array
    {
        $text = Str::lower($title.' '.$excerpt.' '.implode(' ', array_slice($paragraphs, 0, 8)));

        $candidates = [
            'raleigh' => ['raleigh', 'wake'],
            'durham' => ['durham', 'unc'],
            'surgery recovery' => ['surgery', 'post-hospital', 'recovery'],
            'companion care' => ['companion', 'companionship'],
            'home care jobs' => ['jobs', 'caregiver', 'career'],
            'senior housing' => ['senior housing', 'assisted living'],
            'transportation' => ['transport', 'rides', 'appointment'],
            'meal prep' => ['meal', 'nutrition', 'food'],
            'business home care' => ['business', 'agency', 'operations'],
        ];

        $topics = [];
        foreach ($candidates as $topic => $keywords) {
            foreach ($keywords as $keyword) {
                if (Str::contains($text, $keyword)) {
                    $topics[] = $topic;
                    break;
                }
            }
        }

        if ($topics === []) {
            $topics[] = 'raleigh';
        }

        return array_values(array_unique($topics));
    }

    /**
     * @param  list<string>  $paragraphs
     */
    private function isSeoRelevant(string $title, string $excerpt, array $paragraphs): bool
    {
        $text = Str::lower($title.' '.$excerpt.' '.implode(' ', array_slice($paragraphs, 0, 6)));

        $includeHints = [
            'home care',
            'caregiver',
            'senior',
            'companion',
            'post-surgery',
            'post hospital',
            'personal care',
            'raleigh',
            'durham',
            'in-home',
        ];

        $excludeHints = [
            'ascs to negotiate lower pricing',
            'vendor pricing',
            'clinical workflow solutions market growth analysis',
        ];

        foreach ($excludeHints as $term) {
            if (Str::contains($text, $term)) {
                return false;
            }
        }

        foreach ($includeHints as $term) {
            if (Str::contains($text, $term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string,mixed>>  $posts
     * @return list<array<string,mixed>>
     */
    private function attachInternalLinks(array $posts): array
    {
        return collect($posts)->map(function (array $post) use ($posts): array {
            $relatedPosts = collect($posts)
                ->reject(fn (array $candidate) => $candidate['slug'] === $post['slug'])
                ->sortByDesc(function (array $candidate) use ($post): int {
                    $intersection = array_intersect(
                        (array) ($post['topics'] ?? []),
                        (array) ($candidate['topics'] ?? [])
                    );

                    return count($intersection);
                })
                ->take(6)
                ->map(fn (array $candidate) => [
                    'title' => $candidate['title'],
                    'path' => $candidate['path'],
                ])
                ->values()
                ->all();

            $seoLinks = [
                ['title' => 'Raleigh Home Care Guide', 'path' => '/raleigh-home-care'],
                ['title' => 'How HomeCare Works in Raleigh', 'path' => '/how-homecare-works-raleigh'],
                ['title' => 'Trusted Caregiver Screening', 'path' => '/trusted-caregiver-screening'],
                ['title' => 'Caregiver Jobs in Raleigh', 'path' => '/caregiver-jobs-raleigh-nc'],
            ];

            $post['related_posts'] = $relatedPosts;
            $post['internal_links'] = $seoLinks;

            return $post;
        })->values()->all();
    }

    private function cacheKey(): string
    {
        $blogPath = base_path('blogs');
        if (! File::isDirectory($blogPath)) {
            return 'blog-content:v2:empty';
        }

        $fingerprint = collect(File::files($blogPath))
            ->filter(fn ($file) => strtolower($file->getExtension()) === 'docx')
            ->sortBy(fn ($file) => strtolower($file->getFilename()))
            ->map(fn ($file) => $file->getFilename().'|'.$file->getSize().'|'.$file->getMTime())
            ->implode(';');

        return 'blog-content:v2:'.md5($fingerprint);
    }
}
