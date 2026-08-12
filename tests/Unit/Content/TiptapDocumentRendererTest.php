<?php

namespace Tests\Unit\Content;

use App\Models\MediaAsset;
use App\Services\Content\TiptapDocumentRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TiptapDocumentRendererTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_semantic_allowlisted_html_and_escapes_text(): void
    {
        $asset = MediaAsset::query()->create([
            'disk' => 'public', 'path' => 'content/test.jpg', 'original_filename' => 'test.jpg',
            'mime_type' => 'image/jpeg', 'size_bytes' => 100, 'width' => 1200, 'height' => 675,
            'alt_text' => 'Care conversation',
        ]);
        $result = app(TiptapDocumentRenderer::class)->render([
            'type' => 'doc',
            'content' => [
                ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Safe & useful']]],
                ['type' => 'paragraph', 'content' => [
                    ['type' => 'text', 'text' => '<script>alert(1)</script> ', 'marks' => [['type' => 'bold']]],
                    ['type' => 'text', 'text' => 'source', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.com']]]],
                ]],
                ['type' => 'image', 'attrs' => ['assetId' => $asset->id, 'src' => 'https://attacker.test/x', 'alt' => 'Managed alt', 'title' => '', 'caption' => 'Managed caption']],
            ],
        ]);

        $this->assertStringContainsString('<h2 id="safe-useful">Safe &amp; useful</h2>', $result['html']);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $result['html']);
        $this->assertStringNotContainsString('attacker.test', $result['html']);
        $this->assertStringContainsString('/storage/content/test.jpg', $result['html']);
        $this->assertSame('Safe & useful', $result['table_of_contents'][0]['text']);
    }

    public function test_it_rejects_unknown_nodes_attributes_and_unsafe_links(): void
    {
        $this->expectException(ValidationException::class);

        app(TiptapDocumentRenderer::class)->render([
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'attrs' => ['onclick' => 'steal()'],
                'content' => [[
                    'type' => 'text', 'text' => 'bad',
                    'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]],
                ]],
            ]],
        ]);
    }

    public function test_it_rejects_protocol_relative_links_and_uses_managed_media_alt_text(): void
    {
        $asset = MediaAsset::query()->create([
            'disk' => 'public', 'path' => 'content/test/managed.jpg', 'original_filename' => 'managed.jpg',
            'mime_type' => 'image/jpeg', 'size_bytes' => 100, 'width' => 1200, 'height' => 800,
            'alt_text' => 'Managed descriptive alternative',
        ]);
        $renderer = app(TiptapDocumentRenderer::class);

        $result = $renderer->render([
            'type' => 'doc', 'content' => [['type' => 'image', 'attrs' => ['assetId' => $asset->id, 'alt' => '']]],
        ]);
        $this->assertStringContainsString('alt="Managed descriptive alternative"', $result['html']);

        $this->expectException(ValidationException::class);
        $renderer->render([
            'type' => 'doc', 'content' => [[
                'type' => 'paragraph', 'content' => [[
                    'type' => 'text', 'text' => 'unsafe', 'marks' => [['type' => 'link', 'attrs' => ['href' => '//attacker.test']]],
                ]],
            ], ['type' => 'image', 'attrs' => ['assetId' => $asset->id, 'alt' => '']]],
        ]);
    }
}
