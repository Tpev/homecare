<?php

namespace Tests\Feature\Content;

use App\Models\BlogPost;
use App\Models\BlogPostRevision;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\Content\DocxBlogImporter;
use App\Services\Content\MediaAssetManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MediaAndImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_pipeline_validates_images_and_creates_responsive_renditions(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $asset = app(MediaAssetManager::class)->storeUpload(
            UploadedFile::fake()->image('family-care.jpg', 1800, 1000),
            $admin,
            ['alt_text' => 'A family planning care together', 'license' => 'Owned'],
        );

        Storage::disk('public')->assertExists($asset->path);
        $this->assertSame('A family planning care together', $asset->alt_text);
        $this->assertGreaterThanOrEqual(3, $asset->variants()->count());
        foreach ($asset->variants as $variant) {
            Storage::disk('public')->assertExists($variant->path);
            $this->assertSame('image/webp', $variant->mime_type);
        }
    }

    public function test_docx_import_creates_noindex_draft_with_review_warnings(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $path = base_path('blogs/Exploring Opportunities in Home Care Jobs.docx');

        $result = app(DocxBlogImporter::class)->import($path, $admin);

        $this->assertFalse($result['skipped']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertSame(BlogPost::STATUS_DRAFT, $result['post']->status);
        $this->assertFalse($result['post']->robots_index);
        $this->assertSame('noindex,nofollow', $result['post']->robots_directives);
        $this->assertSame(basename($path), $result['post']->source_import);
        $this->get('/blog/'.$result['post']->slug)->assertNotFound();
        $this->assertGreaterThan(0, MediaAsset::count() + $result['post']->word_count);
    }

    public function test_media_referenced_only_by_revision_history_cannot_be_deleted(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $asset = app(MediaAssetManager::class)->storeUpload(
            UploadedFile::fake()->image('historical.jpg', 1200, 800),
            $admin,
            ['alt_text' => 'Historical managed media'],
        );
        $post = BlogPost::query()->create([
            'title' => 'Historical draft', 'slug' => 'historical-draft', 'status' => BlogPost::STATUS_DRAFT,
            'content_json' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
        ]);
        BlogPostRevision::query()->create([
            'blog_post_id' => $post->id,
            'revision_number' => 1,
            'snapshot' => [
                'featured_media_asset_id' => null,
                'content_json' => ['type' => 'doc', 'content' => [[
                    'type' => 'image', 'attrs' => ['assetId' => $asset->id, 'alt' => 'Historical managed media'],
                ]]],
            ],
            'actor_user_id' => $admin->id,
        ]);

        $this->expectException(ValidationException::class);
        app(MediaAssetManager::class)->delete($asset);
    }
}
