<?php

namespace App\Services\Content;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TiptapDocumentRenderer
{
    private const ALLOWED_NODES = [
        'doc', 'paragraph', 'text', 'heading', 'bulletList', 'orderedList', 'listItem',
        'blockquote', 'horizontalRule', 'hardBreak', 'image', 'table', 'tableRow',
        'tableHeader', 'tableCell', 'callout', 'cta', 'citation', 'faq', 'faqItem',
    ];

    private const ALLOWED_MARKS = ['bold', 'italic', 'underline', 'strike', 'link'];

    private const NODE_ATTRIBUTES = [
        'doc' => [],
        'paragraph' => [],
        'text' => [],
        'heading' => ['level', 'id'],
        'bulletList' => [],
        'orderedList' => ['start'],
        'listItem' => [],
        'blockquote' => [],
        'horizontalRule' => [],
        'hardBreak' => [],
        'image' => ['assetId', 'src', 'alt', 'title', 'caption'],
        'table' => [],
        'tableRow' => [],
        'tableHeader' => ['colspan', 'rowspan'],
        'tableCell' => ['colspan', 'rowspan'],
        'callout' => ['kind', 'title'],
        'cta' => ['label', 'url', 'variant'],
        'citation' => ['sourceKey', 'sourceId', 'label'],
        'faq' => [],
        'faqItem' => ['question'],
    ];

    /**
     * @return array{html:string,plain_text:string,table_of_contents:list<array{id:string,text:string,level:int}>,word_count:int,read_minutes:int,faqs:list<array{question:string,answer:string}>}
     */
    public function render(array $document, array $sources = []): array
    {
        $this->validate($document);

        $context = [
            'headings' => [],
            'heading_ids' => [],
            'plain' => [],
            'faqs' => [],
            'source_labels' => collect($sources)->values()->mapWithKeys(function (array $source, int $index): array {
                $uuid = (string) ($source['uuid'] ?? '');

                return Str::isUuid($uuid) ? [$uuid => $index + 1] : [];
            })->all(),
            'media_assets' => MediaAsset::query()
                ->with('variants')
                ->whereIn('id', $this->documentAssetIds($document))
                ->get()
                ->keyBy('id'),
        ];

        $html = $this->renderNode($document, $context, 0);
        $plain = trim(preg_replace('/\s+/u', ' ', implode(' ', $context['plain'])) ?: '');
        $wordCount = $plain === '' ? 0 : Str::wordCount($plain);

        return [
            'html' => $html,
            'plain_text' => $plain,
            'table_of_contents' => $context['headings'],
            'word_count' => $wordCount,
            'read_minutes' => max(1, (int) ceil($wordCount / 220)),
            'faqs' => $context['faqs'],
        ];
    }

    public function validate(array $document): void
    {
        $errors = [];
        $count = 0;
        $this->validateNode($document, 'content', 0, $count, $errors);

        if (($document['type'] ?? null) !== 'doc') {
            $errors['content'][] = 'The root Tiptap node must be a document.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<string, list<string>> $errors */
    private function validateNode(mixed $node, string $path, int $depth, int &$count, array &$errors): void
    {
        $count++;
        if ($count > 5000) {
            $errors['content'][] = 'The document contains too many nodes.';

            return;
        }

        if ($depth > 24 || ! is_array($node)) {
            $errors[$path][] = 'The document structure is invalid or too deeply nested.';

            return;
        }

        $type = $node['type'] ?? null;
        if (! is_string($type) || ! in_array($type, self::ALLOWED_NODES, true)) {
            $errors[$path][] = 'Unsupported content node: '.(is_scalar($type) ? (string) $type : 'unknown').'.';

            return;
        }

        $attrs = $node['attrs'] ?? [];
        if (! is_array($attrs)) {
            $errors[$path][] = 'Node attributes must be an object.';
        } else {
            $unknown = array_diff(array_keys($attrs), self::NODE_ATTRIBUTES[$type]);
            if ($unknown !== []) {
                $errors[$path][] = 'Unsupported attributes on '.$type.': '.implode(', ', $unknown).'.';
            }
        }

        if ($type === 'text') {
            if (! isset($node['text']) || ! is_string($node['text']) || mb_strlen($node['text']) > 100000) {
                $errors[$path][] = 'Text nodes must contain valid text.';
            }
        }

        if ($type === 'heading' && ! in_array((int) ($attrs['level'] ?? 0), [2, 3, 4], true)) {
            $errors[$path][] = 'Article headings must use level 2, 3, or 4.';
        }

        if ($type === 'image' && (! isset($attrs['assetId']) || ! is_numeric($attrs['assetId']))) {
            $errors[$path][] = 'Images must reference a managed media asset.';
        }

        if ($type === 'cta' && $this->safeUrl((string) ($attrs['url'] ?? '')) === null) {
            $errors[$path][] = 'CTA links must use a safe public URL.';
        }

        if ($type === 'citation' && isset($attrs['sourceKey']) && $attrs['sourceKey'] !== '' && ! Str::isUuid((string) $attrs['sourceKey'])) {
            $errors[$path][] = 'Citations must reference a stable source identifier.';
        }

        foreach ((array) ($node['marks'] ?? []) as $markIndex => $mark) {
            if (! is_array($mark) || ! in_array(($mark['type'] ?? null), self::ALLOWED_MARKS, true)) {
                $errors[$path.'.marks.'.$markIndex][] = 'Unsupported text formatting.';

                continue;
            }

            if (($mark['type'] ?? null) === 'link') {
                $markAttrs = (array) ($mark['attrs'] ?? []);
                $unknown = array_diff(array_keys($markAttrs), ['href', 'target', 'rel']);
                if ($unknown !== [] || $this->safeUrl((string) ($markAttrs['href'] ?? '')) === null) {
                    $errors[$path.'.marks.'.$markIndex][] = 'Links must use a safe public URL.';
                }
            }
        }

        foreach ((array) ($node['content'] ?? []) as $index => $child) {
            $this->validateNode($child, $path.'.'.$index, $depth + 1, $count, $errors);
        }
    }

    /** @param array{headings:array,heading_ids:array,plain:array,faqs:array,source_labels:array,media_assets:\Illuminate\Support\Collection} $context */
    private function renderNode(array $node, array &$context, int $depth): string
    {
        $type = $node['type'];
        $attrs = (array) ($node['attrs'] ?? []);
        $children = function () use ($node, &$context, $depth): string {
            $html = '';
            foreach ((array) ($node['content'] ?? []) as $child) {
                $html .= $this->renderNode($child, $context, $depth + 1);
            }

            return $html;
        };

        if ($type === 'text') {
            $text = (string) ($node['text'] ?? '');
            $context['plain'][] = $text;

            return $this->renderMarkedText($text, (array) ($node['marks'] ?? []));
        }

        if ($type === 'doc') {
            return $children();
        }

        if ($type === 'hardBreak') {
            return '<br>';
        }

        if ($type === 'horizontalRule') {
            return '<hr>';
        }

        if ($type === 'heading') {
            $level = (int) $attrs['level'];
            $text = $this->nodeText($node);
            $base = Str::slug((string) ($attrs['id'] ?? $text)) ?: 'section';
            $id = $base;
            $suffix = 2;
            while (isset($context['heading_ids'][$id])) {
                $id = $base.'-'.$suffix++;
            }
            $context['heading_ids'][$id] = true;
            $context['headings'][] = ['id' => $id, 'text' => $text, 'level' => $level];

            return '<h'.$level.' id="'.$this->escape($id).'">'.$children().'</h'.$level.'>';
        }

        if ($type === 'paragraph') {
            return '<p>'.$children().'</p>';
        }

        if ($type === 'bulletList') {
            return '<ul>'.$children().'</ul>';
        }

        if ($type === 'orderedList') {
            $start = max(1, (int) ($attrs['start'] ?? 1));
            $attribute = $start === 1 ? '' : ' start="'.$start.'"';

            return '<ol'.$attribute.'>'.$children().'</ol>';
        }

        if ($type === 'listItem') {
            return '<li>'.$children().'</li>';
        }

        if ($type === 'blockquote') {
            return '<blockquote>'.$children().'</blockquote>';
        }

        if ($type === 'image') {
            return $this->renderImage($attrs, $context);
        }

        if ($type === 'table') {
            return '<div class="content-table-wrap"><table>'.$children().'</table></div>';
        }

        if ($type === 'tableRow') {
            return '<tr>'.$children().'</tr>';
        }

        if (in_array($type, ['tableHeader', 'tableCell'], true)) {
            $tag = $type === 'tableHeader' ? 'th' : 'td';
            $colspan = min(12, max(1, (int) ($attrs['colspan'] ?? 1)));
            $rowspan = min(50, max(1, (int) ($attrs['rowspan'] ?? 1)));
            $span = ($colspan > 1 ? ' colspan="'.$colspan.'"' : '').($rowspan > 1 ? ' rowspan="'.$rowspan.'"' : '');

            return '<'.$tag.$span.'>'.$children().'</'.$tag.'>';
        }

        if ($type === 'callout') {
            $kind = in_array(($attrs['kind'] ?? ''), ['note', 'tip', 'warning', 'important'], true)
                ? $attrs['kind']
                : 'note';
            $title = trim((string) ($attrs['title'] ?? ''));

            return '<aside class="content-callout content-callout--'.$kind.'">'
                .($title !== '' ? '<p class="content-callout__title">'.$this->escape($title).'</p>' : '')
                .$children().'</aside>';
        }

        if ($type === 'cta') {
            $url = $this->safeUrl((string) ($attrs['url'] ?? '')) ?? '/';
            $label = trim((string) ($attrs['label'] ?? 'Learn more')) ?: 'Learn more';
            $variant = in_array(($attrs['variant'] ?? ''), ['primary', 'secondary'], true) ? $attrs['variant'] : 'primary';

            return '<div class="content-cta"><a class="content-cta__link content-cta__link--'.$variant.'" href="'
                .$this->escape($url).'">'.$this->escape($label).'</a></div>';
        }

        if ($type === 'citation') {
            $sourceKey = (string) ($attrs['sourceKey'] ?? '');
            if (Str::isUuid($sourceKey)) {
                $label = (string) ($context['source_labels'][$sourceKey] ?? ($attrs['label'] ?? '?'));

                return '<sup class="content-citation"><a href="#source-'.$this->escape($sourceKey).'" aria-label="Source '
                    .$this->escape($label).'">['.$this->escape($label).']</a></sup>';
            }

            $sourceId = max(1, (int) ($attrs['sourceId'] ?? 1));
            $label = trim((string) ($attrs['label'] ?? $sourceId));

            return '<sup class="content-citation"><a href="#source-'.$sourceId.'" aria-label="Source '.$sourceId.'">['
                .$this->escape($label).']</a></sup>';
        }

        if ($type === 'faq') {
            return '<section class="content-faq" aria-labelledby="content-faq-heading"><h2 id="content-faq-heading">Frequently asked questions</h2>'.$children().'</section>';
        }

        if ($type === 'faqItem') {
            $question = trim((string) ($attrs['question'] ?? 'Question')) ?: 'Question';
            $answerHtml = $children();
            $answer = trim(preg_replace('/\s+/u', ' ', strip_tags($answerHtml)) ?: '');
            $context['faqs'][] = ['question' => $question, 'answer' => $answer];
            $context['plain'][] = $question;

            return '<details class="content-faq__item"><summary>'.$this->escape($question).'</summary><div>'.$answerHtml.'</div></details>';
        }

        return '';
    }

    private function renderImage(array $attrs, array $context): string
    {
        $asset = $context['media_assets']->get((int) ($attrs['assetId'] ?? 0));
        if (! $asset || ! str_starts_with($asset->mime_type, 'image/')) {
            return '';
        }

        $alt = trim((string) ($attrs['alt'] ?? '')) ?: trim((string) ($asset->alt_text ?? ''));
        $title = trim((string) ($attrs['title'] ?? ''));
        $caption = trim((string) ($attrs['caption'] ?? $asset->caption ?? ''));
        $variants = $asset->variants->sortBy('width');
        $srcset = $variants->map(fn ($variant): string => Storage::disk($variant->disk)->url($variant->path).' '.$variant->width.'w')->implode(', ');

        return '<figure class="content-figure"><img src="'.$this->escape($asset->variantUrl('large')).'"'
            .($srcset !== '' ? ' srcset="'.$this->escape($srcset).'" sizes="(min-width: 1024px) 760px, 100vw"' : '')
            .' alt="'.$this->escape($alt).'"'
            .($title !== '' ? ' title="'.$this->escape($title).'"' : '')
            .($asset->width ? ' width="'.$asset->width.'"' : '')
            .($asset->height ? ' height="'.$asset->height.'"' : '')
            .' loading="lazy" decoding="async">'
            .($caption !== '' ? '<figcaption>'.$this->escape($caption).'</figcaption>' : '')
            .'</figure>';
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

    private function renderMarkedText(string $text, array $marks): string
    {
        $html = $this->escape($text);
        foreach ($marks as $mark) {
            $type = $mark['type'];
            $attrs = (array) ($mark['attrs'] ?? []);
            $html = match ($type) {
                'bold' => '<strong>'.$html.'</strong>',
                'italic' => '<em>'.$html.'</em>',
                'underline' => '<u>'.$html.'</u>',
                'strike' => '<s>'.$html.'</s>',
                'link' => $this->renderLink($html, $attrs),
                default => $html,
            };
        }

        return $html;
    }

    private function renderLink(string $html, array $attrs): string
    {
        $url = $this->safeUrl((string) ($attrs['href'] ?? ''));
        if ($url === null) {
            return $html;
        }

        $external = preg_match('/^https?:\/\//i', $url) === 1;
        $target = ($attrs['target'] ?? null) === '_blank' ? ' target="_blank"' : '';
        $rel = $external || $target !== '' ? ' rel="noopener noreferrer"' : '';

        return '<a href="'.$this->escape($url).'"'.$target.$rel.'>'.$html.'</a>';
    }

    private function safeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return null;
        }
        if (str_starts_with($url, '#')) {
            return $url;
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_starts_with($url, '/\\')) {
            return $url === '' ? null : $url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true) ? $url : null;
    }

    private function nodeText(array $node): string
    {
        if (($node['type'] ?? null) === 'text') {
            return (string) ($node['text'] ?? '');
        }

        return trim(implode('', array_map(fn (array $child): string => $this->nodeText($child), (array) ($node['content'] ?? []))));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
