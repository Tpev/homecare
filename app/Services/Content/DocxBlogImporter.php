<?php

namespace App\Services\Content;

use App\Models\BlogPost;
use App\Models\ContentAuthor;
use App\Models\ContentCategory;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

class DocxBlogImporter
{
    public function __construct(
        private readonly BlogPostWorkflow $workflow,
        private readonly MediaAssetManager $media,
    ) {}

    /**
     * @return array{post:BlogPost|null,title:string,source:string,warnings:list<string>,skipped:bool}
     */
    public function import(string $path, User $actor, bool $dryRun = false, bool $force = false): array
    {
        $absolute = realpath($path);
        if (! $absolute || ! File::isFile($absolute) || strtolower(pathinfo($absolute, PATHINFO_EXTENSION)) !== 'docx') {
            throw new \InvalidArgumentException('DOCX file not found: '.$path);
        }

        $source = basename($absolute);
        if (! $force && BlogPost::withTrashed()->where('source_import', $source)->exists()) {
            return ['post' => null, 'title' => pathinfo($source, PATHINFO_FILENAME), 'source' => $source, 'warnings' => [], 'skipped' => true];
        }

        $post = $force
            ? BlogPost::withTrashed()->where('source_import', $source)->first()
            : null;
        $parsed = $this->parse($absolute, $actor, ! $dryRun, $post?->id);
        if ($dryRun) {
            return ['post' => null, 'title' => $parsed['title'], 'source' => $source, 'warnings' => $parsed['warnings'], 'skipped' => false];
        }

        if ($post?->trashed()) {
            $post->restore();
        }
        $post ??= $this->workflow->createDraft($actor, $parsed['title']);

        $author = ContentAuthor::query()->firstOrCreate(
            ['slug' => 'lolo-care-editorial-team'],
            [
                'name' => 'LoLo Care Editorial Team',
                'headline' => 'Local care marketplace editorial team',
                'bio' => 'LoLo Care publishes practical guidance for families and independent caregivers. Every guide is reviewed before publication.',
                'is_active' => true,
            ]
        );
        $category = ContentCategory::query()->firstOrCreate(
            ['slug' => $parsed['category_slug']],
            [
                'name' => $parsed['category_name'],
                'description' => 'Reviewed LoLo Care guidance about '.Str::lower($parsed['category_name']).'.',
                'is_active' => true,
            ]
        );

        $post = $this->workflow->save(
            $post,
            [
                'title' => $parsed['title'],
                'slug' => $parsed['slug'],
                'excerpt' => $parsed['excerpt'],
                'content_json' => $parsed['document'],
                'author_id' => $author->id,
                'featured_media_asset_id' => $parsed['featured_media_asset_id'],
                'seo_title' => $parsed['seo_title'],
                'meta_description' => $parsed['meta_description'],
                'source_import' => $source,
                'content_type' => 'guide',
                'editorial_checklist' => [],
                'review_notes' => 'Imported from DOCX. Review every claim, source, link, image right, and product capability before publication. Import warnings: '.implode(' | ', $parsed['warnings']),
            ],
            $actor,
            [$category->id],
            [],
            [],
            'Imported from '.$source,
            $post->edit_version,
        );
        $post->forceFill(['source_import' => $source])->save();
        if (! $post->published_revision_id) {
            $post->forceFill([
                'status' => BlogPost::STATUS_DRAFT,
                'robots_index' => false,
                'robots_directives' => 'noindex,nofollow',
            ])->save();
        }

        return ['post' => $post->fresh(), 'title' => $parsed['title'], 'source' => $source, 'warnings' => $parsed['warnings'], 'skipped' => false];
    }

    /** @return list<string> */
    public function discover(string $path): array
    {
        $absolute = realpath($path);
        if (! $absolute) {
            return [];
        }
        if (File::isFile($absolute)) {
            return strtolower(pathinfo($absolute, PATHINFO_EXTENSION)) === 'docx' ? [$absolute] : [];
        }

        return collect(File::files($absolute))
            ->filter(fn ($file): bool => strtolower($file->getExtension()) === 'docx')
            ->sortBy(fn ($file): string => strtolower($file->getFilename()))
            ->map(fn ($file): string => $file->getPathname())
            ->values()
            ->all();
    }

    /** @return array<string,mixed> */
    private function parse(string $path, User $actor, bool $storeMedia, ?int $existingPostId = null): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Unable to open DOCX file: '.basename($path));
        }

        try {
            $documentStat = $zip->statName('word/document.xml');
            if (! is_array($documentStat) || (int) ($documentStat['size'] ?? 0) > 15 * 1024 * 1024) {
                throw new \RuntimeException('DOCX document XML is missing or exceeds the 15 MB safety limit.');
            }
            $xml = $zip->getFromName('word/document.xml');
            if (! is_string($xml) || $xml === '') {
                throw new \RuntimeException('DOCX document.xml is missing.');
            }

            $relationships = $this->relationships($zip);
            $dom = new \DOMDocument;
            if (! @$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
                throw new \RuntimeException('DOCX document XML is invalid.');
            }
            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
            $xpath->registerNamespace('wp', 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing');

            $warnings = [];
            if ($zip->locateName('word/footnotes.xml') !== false) {
                $warnings[] = 'Footnotes require manual review; DOCX footnote placement was not imported.';
            }
            if ($xpath->query('//w:ins|//w:del')?->length) {
                $warnings[] = 'Tracked changes were present; confirm accepted/rejected text manually.';
            }

            $nodes = [];
            $firstImageId = null;
            $title = '';
            $metaTitle = '';
            $metaDescription = '';
            $requestedSlug = '';
            $body = $xpath->query('//w:body')->item(0);
            if (! $body) {
                throw new \RuntimeException('DOCX body is missing.');
            }

            foreach ($body->childNodes as $child) {
                if (! $child instanceof \DOMElement) {
                    continue;
                }

                if ($child->localName === 'p') {
                    $paragraph = $this->paragraphNode($xpath, $child, $relationships);
                    $text = trim($this->nodeText($paragraph));

                    if ($text !== '' && preg_match('/^(meta\s*title|seo\s*title)\s*:\s*(.+)$/i', $text, $matches)) {
                        $metaTitle = trim($matches[2]);

                        continue;
                    }
                    if ($text !== '' && preg_match('/^meta\s*description\s*:\s*(.+)$/i', $text, $matches)) {
                        $metaDescription = trim($matches[1]);

                        continue;
                    }
                    if ($text !== '' && preg_match('/^title\s*:\s*(.+)$/i', $text, $matches)) {
                        $title = trim($matches[1]);

                        continue;
                    }
                    if ($text !== '' && preg_match('/^slug\s*:\s*(.+)$/i', $text, $matches)) {
                        $requestedSlug = Str::slug(trim($matches[1]));

                        continue;
                    }
                    if ($text !== '' && preg_match('/^(focus keyword|image alt)\s*:/i', $text)) {
                        continue;
                    }

                    if ($title === '' && ($paragraph['type'] ?? '') === 'heading') {
                        $title = $text;

                        continue;
                    }

                    if ($paragraph !== null && ($text !== '' || ($paragraph['content'] ?? []) !== [])) {
                        $nodes[] = $paragraph;
                    }

                    foreach ($this->paragraphImages($zip, $xpath, $child, $relationships, $actor, $storeMedia, $warnings) as $imageNode) {
                        $nodes[] = $imageNode;
                        $firstImageId ??= (int) data_get($imageNode, 'attrs.assetId');
                    }
                } elseif ($child->localName === 'tbl') {
                    $nodes[] = $this->tableNode($xpath, $child, $relationships);
                }
            }

            $filenameTitle = pathinfo(basename($path), PATHINFO_FILENAME);
            $title = $this->cleanTitle($title ?: $metaTitle ?: $filenameTitle);
            if ($nodes !== [] && Str::lower($this->nodeText($nodes[0])) === Str::lower($title)) {
                array_shift($nodes);
            }
            if ($nodes === []) {
                $nodes[] = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Imported document contained no readable article body.']]];
                $warnings[] = 'No readable body content was found.';
            }

            $plain = trim(implode(' ', array_map(fn (array $node): string => $this->nodeText($node), $nodes)));
            $excerpt = Str::limit($plain, 180, '');
            $metaDescription = Str::limit($metaDescription ?: $excerpt, 165, '');
            $category = $this->detectCategory($title.' '.$plain);

            $warnings[] = 'Imported as a noindex draft; publication dates were intentionally not inferred from the DOCX file timestamp.';
            $warnings[] = 'Verify all citations, competitor references, medical boundaries, image rights, and LoLo product claims.';

            return [
                'title' => $title,
                'slug' => $this->uniqueImportedSlug($requestedSlug ?: Str::slug($title), $existingPostId),
                'excerpt' => $excerpt,
                'seo_title' => Str::limit($metaTitle ?: $title.' | LoLo Care', 65, ''),
                'meta_description' => $metaDescription,
                'document' => ['type' => 'doc', 'content' => $nodes],
                'featured_media_asset_id' => $firstImageId,
                'category_name' => $category['name'],
                'category_slug' => $category['slug'],
                'warnings' => array_values(array_unique($warnings)),
            ];
        } finally {
            $zip->close();
        }
    }

    /** @return array<string,array{target:string,external:bool}> */
    private function relationships(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('word/_rels/document.xml.rels');
        if (! is_string($xml) || $xml === '') {
            return [];
        }

        $dom = new \DOMDocument;
        if (! @$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            return [];
        }

        $relationships = [];
        foreach ($dom->getElementsByTagName('Relationship') as $relationship) {
            $relationships[$relationship->getAttribute('Id')] = [
                'target' => $relationship->getAttribute('Target'),
                'external' => $relationship->getAttribute('TargetMode') === 'External',
            ];
        }

        return $relationships;
    }

    private function paragraphNode(\DOMXPath $xpath, \DOMElement $paragraph, array $relationships): ?array
    {
        $content = $this->inlineContent($xpath, $paragraph, $relationships);
        if ($content === []) {
            return null;
        }

        $style = (string) $xpath->evaluate('string(./w:pPr/w:pStyle/@w:val)', $paragraph);
        if (preg_match('/heading\s*([1-6])/i', $style, $matches)) {
            return ['type' => 'heading', 'attrs' => ['level' => min(4, max(2, (int) $matches[1]))], 'content' => $content];
        }

        if ($xpath->query('./w:pPr/w:numPr', $paragraph)?->length) {
            return ['type' => 'bulletList', 'content' => [[
                'type' => 'listItem',
                'content' => [['type' => 'paragraph', 'content' => $content]],
            ]]];
        }

        return ['type' => 'paragraph', 'content' => $content];
    }

    /** @return list<array<string,mixed>> */
    private function inlineContent(\DOMXPath $xpath, \DOMElement $container, array $relationships): array
    {
        $content = [];
        foreach ($container->childNodes as $child) {
            if (! $child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'hyperlink') {
                $id = $child->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
                $href = ($relationships[$id]['external'] ?? false) ? ($relationships[$id]['target'] ?? '') : '';
                foreach ($this->runTextNodes($xpath, $child) as $node) {
                    if ($href !== '') {
                        $node['marks'] = array_merge((array) ($node['marks'] ?? []), [[
                            'type' => 'link', 'attrs' => ['href' => $href, 'target' => '_blank'],
                        ]]);
                    }
                    $content[] = $node;
                }
            } elseif ($child->localName === 'r') {
                array_push($content, ...$this->runTextNodes($xpath, $child));
            }
        }

        if ($content === []) {
            $text = trim((string) $xpath->evaluate('string(.)', $container));
            if ($text !== '') {
                $content[] = ['type' => 'text', 'text' => $text];
            }
        }

        return $content;
    }

    /** @return list<array<string,mixed>> */
    private function runTextNodes(\DOMXPath $xpath, \DOMElement $run): array
    {
        $text = '';
        foreach ($xpath->query('.//w:t|.//w:tab|.//w:br', $run) ?: [] as $item) {
            $text .= match ($item->localName) {
                'tab' => ' ',
                'br' => "\n",
                default => $item->textContent,
            };
        }
        if ($text === '') {
            return [];
        }

        $marks = [];
        if ($xpath->query('./w:rPr/w:b', $run)?->length) {
            $marks[] = ['type' => 'bold'];
        }
        if ($xpath->query('./w:rPr/w:i', $run)?->length) {
            $marks[] = ['type' => 'italic'];
        }
        if ($xpath->query('./w:rPr/w:u', $run)?->length) {
            $marks[] = ['type' => 'underline'];
        }
        if ($xpath->query('./w:rPr/w:strike', $run)?->length) {
            $marks[] = ['type' => 'strike'];
        }

        $node = ['type' => 'text', 'text' => $text];
        if ($marks !== []) {
            $node['marks'] = $marks;
        }

        return [$node];
    }

    /** @return list<array<string,mixed>> */
    private function paragraphImages(ZipArchive $zip, \DOMXPath $xpath, \DOMElement $paragraph, array $relationships, User $actor, bool $storeMedia, array &$warnings): array
    {
        $nodes = [];
        foreach ($xpath->query('.//a:blip/@r:embed', $paragraph) ?: [] as $attribute) {
            $relationship = $relationships[$attribute->nodeValue] ?? null;
            if (! $relationship || $relationship['external']) {
                continue;
            }
            $target = 'word/'.ltrim(str_replace('..', '', $relationship['target']), '/');
            $entry = $zip->statName($target);
            if (! is_array($entry) || (int) ($entry['size'] ?? 0) > 20 * 1024 * 1024) {
                $warnings[] = 'An embedded image was missing or exceeded the 20 MB safety limit.';

                continue;
            }
            $binary = $zip->getFromName($target);
            if (! is_string($binary) || $binary === '') {
                $warnings[] = 'An embedded image could not be extracted.';

                continue;
            }

            if (! $storeMedia) {
                $warnings[] = 'Embedded image detected; it will be copied into managed media during import.';

                continue;
            }

            $description = trim((string) $xpath->evaluate('string(.//wp:docPr/@descr)', $paragraph));
            $asset = $this->media->storeBinary(
                $binary,
                basename($target),
                '',
                $actor,
                ['alt_text' => $description, 'import' => 'docx']
            );
            $nodes[] = [
                'type' => 'image',
                'attrs' => [
                    'assetId' => $asset->id,
                    'alt' => $description,
                    'title' => '',
                    'caption' => '',
                ],
            ];
            if ($description === '') {
                $warnings[] = 'An embedded image has no alt text.';
            }
        }

        return $nodes;
    }

    private function tableNode(\DOMXPath $xpath, \DOMElement $table, array $relationships): array
    {
        $rows = [];
        foreach ($xpath->query('./w:tr', $table) ?: [] as $rowIndex => $row) {
            $cells = [];
            foreach ($xpath->query('./w:tc', $row) ?: [] as $cell) {
                $blocks = [];
                foreach ($xpath->query('./w:p', $cell) ?: [] as $paragraph) {
                    $node = $this->paragraphNode($xpath, $paragraph, $relationships);
                    if ($node) {
                        $blocks[] = $node['type'] === 'paragraph' ? $node : ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $this->nodeText($node)]]];
                    }
                }
                $cells[] = [
                    'type' => $rowIndex === 0 ? 'tableHeader' : 'tableCell',
                    'attrs' => ['colspan' => 1, 'rowspan' => 1],
                    'content' => $blocks ?: [['type' => 'paragraph']],
                ];
            }
            $rows[] = ['type' => 'tableRow', 'content' => $cells];
        }

        return ['type' => 'table', 'content' => $rows];
    }

    private function nodeText(?array $node): string
    {
        if (! $node) {
            return '';
        }
        if (($node['type'] ?? null) === 'text') {
            return (string) ($node['text'] ?? '');
        }

        return trim(implode(' ', array_map(fn (array $child): string => $this->nodeText($child), (array) ($node['content'] ?? []))));
    }

    private function cleanTitle(string $title): string
    {
        $title = preg_replace('/\s+/u', ' ', trim($title)) ?: 'Home care guide';
        $title = str_replace(['_', 'â€“', 'â€”'], [' ', '–', '—'], $title);
        $title = preg_replace('/\(\d+\)$/', '', $title) ?: $title;

        return Str::of($title)->replace('prolbem', 'problem')->replace('care givers', 'caregivers')->toString();
    }

    /** @return array{name:string,slug:string} */
    private function detectCategory(string $text): array
    {
        $text = Str::lower($text);

        return match (true) {
            Str::contains($text, ['surgery', 'hospital', 'recovery']) => ['name' => 'Recovery at home', 'slug' => 'recovery-at-home'],
            Str::contains($text, ['caregiver job', 'career', 'hiring']) => ['name' => 'Caregiver careers', 'slug' => 'caregiver-careers'],
            Str::contains($text, ['cost', 'pricing', 'pay']) => ['name' => 'Care costs', 'slug' => 'care-costs'],
            Str::contains($text, ['raleigh', 'durham', 'wake']) => ['name' => 'Triangle care guides', 'slug' => 'triangle-care-guides'],
            default => ['name' => 'Family care guides', 'slug' => 'family-care-guides'],
        };
    }

    private function uniqueImportedSlug(string $requested, ?int $exceptPostId = null): string
    {
        $base = $requested ?: 'home-care-guide';
        $slug = $base;
        $suffix = 2;
        while (BlogPost::withTrashed()->where('slug', $slug)->when($exceptPostId, fn ($query) => $query->whereKeyNot($exceptPostId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
