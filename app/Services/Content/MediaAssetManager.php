<?php

namespace App\Services\Content;

use App\Models\BlogPost;
use App\Models\BlogPostRevision;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MediaAssetManager
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
    ];

    private const VARIANTS = [
        'small' => 480,
        'medium' => 960,
        'large' => 1600,
    ];

    private const MAX_IMAGE_DIMENSION = 12000;

    private const MAX_IMAGE_PIXELS = 25000000;

    public function storeUpload(UploadedFile $file, User $actor, array $metadata = []): MediaAsset
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['media' => 'The uploaded file is not valid.']);
        }

        $binary = $file->get();

        return $this->storeBinary(
            $binary,
            $file->getClientOriginalName(),
            (string) ($file->getMimeType() ?: $file->getClientMimeType()),
            $actor,
            $metadata,
        );
    }

    public function storeBinary(
        string $binary,
        string $originalFilename,
        string $mimeType,
        ?User $actor = null,
        array $metadata = [],
    ): MediaAsset {
        $mime = $this->detectMime($binary) ?: strtolower($mimeType);
        if (! in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::withMessages(['media' => 'Use a JPG, PNG, WEBP, or GIF image.']);
        }

        if (strlen($binary) > 20 * 1024 * 1024) {
            throw ValidationException::withMessages(['media' => 'Images must be no larger than 20 MB.']);
        }

        $dimensions = @getimagesizefromstring($binary);
        if (! is_array($dimensions) || ($dimensions[0] ?? 0) < 1 || ($dimensions[1] ?? 0) < 1) {
            throw ValidationException::withMessages(['media' => 'The image content could not be decoded.']);
        }
        $width = (int) $dimensions[0];
        $height = (int) $dimensions[1];
        if ($width > self::MAX_IMAGE_DIMENSION || $height > self::MAX_IMAGE_DIMENSION || $width * $height > self::MAX_IMAGE_PIXELS) {
            throw ValidationException::withMessages(['media' => 'Images must be at most 12,000 pixels on either side and 25 megapixels in total.']);
        }

        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        };
        $disk = 'public';
        $directory = 'content/'.now()->format('Y/m');
        $basename = Str::uuid()->toString();
        $path = $directory.'/'.$basename.'.'.$extension;
        $writtenPaths = [$path];

        if (! Storage::disk($disk)->put($path, $binary, ['visibility' => 'public'])) {
            throw ValidationException::withMessages(['media' => 'The image could not be stored.']);
        }

        try {
            return DB::transaction(function () use ($disk, $path, $binary, $originalFilename, $mime, $dimensions, $actor, $metadata, $directory, $basename, &$writtenPaths): MediaAsset {
                $asset = MediaAsset::query()->create([
                    'disk' => $disk,
                    'path' => $path,
                    'original_filename' => Str::limit(basename($originalFilename), 255, ''),
                    'mime_type' => $mime,
                    'size_bytes' => strlen($binary),
                    'width' => (int) $dimensions[0],
                    'height' => (int) $dimensions[1],
                    'alt_text' => trim((string) ($metadata['alt_text'] ?? '')) ?: null,
                    'caption' => trim((string) ($metadata['caption'] ?? '')) ?: null,
                    'credit' => trim((string) ($metadata['credit'] ?? '')) ?: null,
                    'license' => trim((string) ($metadata['license'] ?? '')) ?: null,
                    'source_url' => trim((string) ($metadata['source_url'] ?? '')) ?: null,
                    'metadata' => array_filter([
                        'sha256' => hash('sha256', $binary),
                        'import' => $metadata['import'] ?? null,
                    ]),
                    'uploaded_by_user_id' => $actor?->id,
                ]);

                $this->createVariants($asset, $binary, $directory, $basename, $writtenPaths);

                return $asset->fresh('variants');
            });
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($writtenPaths);
            throw $exception;
        }
    }

    public function delete(MediaAsset $asset): void
    {
        if ($asset->blogPosts()->exists() || $asset->authorProfiles()->exists() || $this->isReferencedInContent($asset->id)) {
            throw ValidationException::withMessages(['media' => 'This asset is in use. Replace it before deleting it.']);
        }

        DB::transaction(function () use ($asset): void {
            $asset->loadMissing('variants');
            foreach ($asset->variants as $variant) {
                Storage::disk($variant->disk)->delete($variant->path);
            }
            Storage::disk($asset->disk)->delete($asset->path);
            $asset->forceDelete();
        });
    }

    /** @param list<string> $writtenPaths */
    private function createVariants(MediaAsset $asset, string $binary, string $directory, string $basename, array &$writtenPaths): void
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            return;
        }

        $source = @imagecreatefromstring($binary);
        if (! $source) {
            return;
        }

        try {
            $generatedWidths = [];
            foreach (self::VARIANTS as $name => $maxWidth) {
                $width = min($asset->width, $maxWidth);
                if (in_array($width, $generatedWidths, true)) {
                    continue;
                }
                $generatedWidths[] = $width;
                $height = max(1, (int) round($asset->height * ($width / $asset->width)));
                $target = imagecreatetruecolor($width, $height);
                if (! $target) {
                    continue;
                }

                imagealphablending($target, false);
                imagesavealpha($target, true);
                $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
                imagefilledrectangle($target, 0, 0, $width, $height, $transparent);
                imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, $asset->width, $asset->height);

                ob_start();
                imagewebp($target, null, 82);
                $variantBinary = (string) ob_get_clean();
                imagedestroy($target);

                if ($variantBinary === '') {
                    continue;
                }

                $path = $directory.'/'.$basename.'-'.$name.'.webp';
                if (! Storage::disk($asset->disk)->put($path, $variantBinary, ['visibility' => 'public'])) {
                    continue;
                }
                $writtenPaths[] = $path;

                $asset->variants()->create([
                    'variant' => $name,
                    'disk' => $asset->disk,
                    'path' => $path,
                    'mime_type' => 'image/webp',
                    'size_bytes' => strlen($variantBinary),
                    'width' => $width,
                    'height' => $height,
                ]);
            }
        } finally {
            imagedestroy($source);
        }
    }

    private function detectMime(string $binary): ?string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($binary);

        return is_string($mime) ? strtolower($mime) : null;
    }

    private function isReferencedInContent(int $assetId): bool
    {
        foreach (BlogPost::withTrashed()->select(['content_json', 'featured_media_asset_id'])->cursor() as $post) {
            if ((int) $post->featured_media_asset_id === $assetId || $this->documentReferencesAsset((array) $post->content_json, $assetId)) {
                return true;
            }
        }

        foreach (BlogPostRevision::query()->select('snapshot')->cursor() as $revision) {
            $snapshot = (array) $revision->snapshot;
            if ((int) ($snapshot['featured_media_asset_id'] ?? 0) === $assetId
                || $this->documentReferencesAsset((array) ($snapshot['content_json'] ?? []), $assetId)
            ) {
                return true;
            }
        }

        return false;
    }

    private function documentReferencesAsset(array $node, int $assetId): bool
    {
        if (($node['type'] ?? null) === 'image' && (int) data_get($node, 'attrs.assetId') === $assetId) {
            return true;
        }
        foreach ((array) ($node['content'] ?? []) as $child) {
            if (is_array($child) && $this->documentReferencesAsset($child, $assetId)) {
                return true;
            }
        }

        return false;
    }
}
