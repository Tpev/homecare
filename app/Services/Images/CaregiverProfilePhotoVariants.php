<?php

namespace App\Services\Images;

use Illuminate\Support\Facades\Storage;
use Throwable;

class CaregiverProfilePhotoVariants
{
    /**
     * @return array<int, int>
     */
    public function widths(): array
    {
        return collect(config('marketplace.caregiver_profile_photo.responsive_widths', [480, 768]))
            ->map(fn (mixed $width): int => (int) $width)
            ->filter(fn (int $width): bool => $width >= 200 && $width <= 2000)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function quality(): int
    {
        return max(40, min(95, (int) config('marketplace.caregiver_profile_photo.responsive_quality', 78)));
    }

    public function generate(string $sourcePath, bool $force = false): int
    {
        $disk = Storage::disk('public');

        if (! $this->isManagedSource($sourcePath) || ! $disk->exists($sourcePath)) {
            return 0;
        }

        try {
            $source = $disk->get($sourcePath);
            $generated = 0;

            foreach ($this->widths() as $width) {
                $variantPath = $this->pathFor($sourcePath, $width);

                if (! $force && $disk->exists($variantPath)) {
                    continue;
                }

                $contents = $this->encodeWebp($source, $width);

                if (! $contents) {
                    continue;
                }

                $disk->put($variantPath, $contents, ['visibility' => 'public']);
                $generated++;
            }

            return $generated;
        } catch (Throwable) {
            // A failed derivative must never prevent the original profile photo being saved.
            return 0;
        }
    }

    /**
     * @return array<int, string>
     */
    public function urlsFor(string $sourcePath): array
    {
        $disk = Storage::disk('public');
        $urls = [];

        foreach ($this->widths() as $width) {
            $variantPath = $this->pathFor($sourcePath, $width);

            if ($disk->exists($variantPath)) {
                $urls[$width] = $disk->url($variantPath);
            }
        }

        return $urls;
    }

    public function hasAll(string $sourcePath): bool
    {
        $disk = Storage::disk('public');

        return collect($this->widths())
            ->every(fn (int $width): bool => $disk->exists($this->pathFor($sourcePath, $width)));
    }

    public function deleteForSource(string $sourcePath): void
    {
        Storage::disk('public')->delete(
            collect($this->widths())
                ->map(fn (int $width): string => $this->pathFor($sourcePath, $width))
                ->all()
        );
    }

    public function pathFor(string $sourcePath, int $width): string
    {
        $directory = pathinfo($sourcePath, PATHINFO_DIRNAME);
        $filename = pathinfo($sourcePath, PATHINFO_FILENAME);
        $prefix = $directory === '.' ? '' : trim($directory, '/').'/';

        return $prefix.$filename.'-'.$width.'.webp';
    }

    private function isManagedSource(string $sourcePath): bool
    {
        return str_starts_with($sourcePath, 'caregiver-photos/')
            && ! str_ends_with(strtolower($sourcePath), '.webp');
    }

    private function encodeWebp(string $source, int $targetWidth): ?string
    {
        return $this->encodeWithImagick($source, $targetWidth)
            ?? $this->encodeWithGd($source, $targetWidth);
    }

    private function encodeWithImagick(string $source, int $targetWidth): ?string
    {
        if (! class_exists(\Imagick::class)) {
            return null;
        }

        try {
            $image = new \Imagick;
            $image->readImageBlob($source);

            if ($image->getNumberImages() > 1) {
                $image->setIteratorIndex(0);
            }

            $width = min($targetWidth, $image->getImageWidth());
            $height = max(1, (int) round($image->getImageHeight() * ($width / $image->getImageWidth())));

            $image->setImageColorspace(\Imagick::COLORSPACE_SRGB);
            $image->stripImage();
            $image->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1);
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality($this->quality());
            $contents = $image->getImageBlob();
            $image->clear();
            $image->destroy();

            return $contents ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private function encodeWithGd(string $source, int $targetWidth): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            return null;
        }

        $image = @imagecreatefromstring($source);

        if (! $image) {
            return null;
        }

        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        $width = min($targetWidth, $sourceWidth);
        $height = max(1, (int) round($sourceHeight * ($width / $sourceWidth)));
        $canvas = imagecreatetruecolor($width, $height);

        if (! $canvas) {
            imagedestroy($image);

            return null;
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        ob_start();
        imagewebp($canvas, null, $this->quality());
        $contents = ob_get_clean();

        imagedestroy($image);
        imagedestroy($canvas);

        return is_string($contents) && $contents !== '' ? $contents : null;
    }
}
