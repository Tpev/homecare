<?php

namespace App\Services\Images;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CaregiverProfilePhotoProcessor
{
    private const STORAGE_DIRECTORY = 'caregiver-photos';

    private const SUPPORTED_EXTENSIONS = [
        'avif',
        'bmp',
        'gif',
        'heic',
        'heif',
        'jpeg',
        'jpg',
        'png',
        'tif',
        'tiff',
        'webp',
    ];

    private const SUPPORTED_MIME_TYPES = [
        'image/avif',
        'image/bmp',
        'image/gif',
        'image/heic',
        'image/heic-sequence',
        'image/heif',
        'image/heif-sequence',
        'image/jpeg',
        'image/pjpeg',
        'image/png',
        'image/tiff',
        'image/webp',
        'image/x-ms-bmp',
        'image/x-tiff',
    ];

    public function validationRules(): array
    {
        return [
            'nullable',
            'file',
            'max:'.$this->maxUploadKilobytes(),
            function (string $attribute, mixed $value, callable $fail): void {
                if (! $value instanceof UploadedFile) {
                    return;
                }

                if (! $this->looksLikeSupportedImage($value)) {
                    $fail($this->unsupportedFormatMessage());
                }
            },
        ];
    }

    public function validationMessages(string $field = 'profile_photo'): array
    {
        return [
            $field.'.file' => 'Choose a valid image file for your profile photo.',
            $field.'.max' => 'Your profile photo must be '.$this->maxUploadMegabytes().' MB or smaller.',
        ];
    }

    public function maxUploadKilobytes(): int
    {
        return max(1024, (int) config('marketplace.caregiver_profile_photo.max_upload_kb', 65536));
    }

    public function maxUploadMegabytes(): int
    {
        return (int) ceil($this->maxUploadKilobytes() / 1024);
    }

    public function maxDimension(): int
    {
        return max(400, (int) config('marketplace.caregiver_profile_photo.max_dimension', 1600));
    }

    public function quality(): int
    {
        return max(40, min(95, (int) config('marketplace.caregiver_profile_photo.quality', 86)));
    }

    public function store(UploadedFile $file): string
    {
        if (! $this->looksLikeSupportedImage($file)) {
            throw ValidationException::withMessages([
                'profile_photo' => $this->unsupportedFormatMessage(),
            ]);
        }

        $contents = $this->encodeAsJpeg($file);

        if (! $contents) {
            throw ValidationException::withMessages([
                'profile_photo' => 'We could not read that photo. Try another image or export it as JPG, PNG, or HEIC.',
            ]);
        }

        $path = self::STORAGE_DIRECTORY.'/'.Str::uuid().'.jpg';

        Storage::disk('public')->put($path, $contents, [
            'visibility' => 'public',
        ]);

        return $path;
    }

    public function deleteIfManaged(?string $path): void
    {
        if (! $path || ! str_starts_with($path, self::STORAGE_DIRECTORY.'/')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    private function looksLikeSupportedImage(UploadedFile $file): bool
    {
        $mimeType = strtolower((string) $file->getMimeType());
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->guessExtension()));

        return in_array($mimeType, self::SUPPORTED_MIME_TYPES, true)
            || in_array($extension, self::SUPPORTED_EXTENSIONS, true);
    }

    private function unsupportedFormatMessage(): string
    {
        return 'Use JPG, PNG, WEBP, HEIC, HEIF, AVIF, GIF, BMP, or TIFF for your profile photo.';
    }

    private function encodeAsJpeg(UploadedFile $file): ?string
    {
        return $this->encodeWithImagick($file->getRealPath())
            ?? $this->encodeWithGd($file);
    }

    private function encodeWithImagick(?string $path): ?string
    {
        if (! $path || ! class_exists(\Imagick::class)) {
            return null;
        }

        try {
            $image = new \Imagick;
            $image->readImage($path);

            if ($image->getNumberImages() > 1) {
                $image->setIteratorIndex(0);
            }

            if (method_exists($image, 'autoOrient')) {
                $image->autoOrient();
            } elseif (method_exists($image, 'autoOrientImage')) {
                $image->autoOrientImage();
            }
            $image->setImageColorspace(\Imagick::COLORSPACE_SRGB);
            $image->stripImage();
            $image->resizeImage(...$this->resizeArguments($image->getImageWidth(), $image->getImageHeight()));

            $canvas = new \Imagick;
            $canvas->newImage($image->getImageWidth(), $image->getImageHeight(), new \ImagickPixel('white'), 'jpeg');
            $canvas->compositeImage($image, \Imagick::COMPOSITE_OVER, 0, 0);
            $canvas->setImageFormat('jpeg');
            $canvas->setImageCompressionQuality($this->quality());

            $contents = $canvas->getImageBlob();

            $image->clear();
            $image->destroy();
            $canvas->clear();
            $canvas->destroy();

            return $contents ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private function resizeArguments(int $width, int $height): array
    {
        [$targetWidth, $targetHeight] = $this->targetDimensions($width, $height);

        return [$targetWidth, $targetHeight, \Imagick::FILTER_LANCZOS, 1];
    }

    private function encodeWithGd(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();

        if (! $path || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = @imagecreatefromstring((string) @file_get_contents($path));

        if (! $source) {
            return null;
        }

        $source = $this->orientGdImage($source, $path, (string) $file->getMimeType());

        $width = imagesx($source);
        $height = imagesy($source);
        [$targetWidth, $targetHeight] = $this->targetDimensions($width, $height);

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if (! $canvas) {
            imagedestroy($source);

            return null;
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imageinterlace($canvas, true);

        ob_start();
        imagejpeg($canvas, null, $this->quality());
        $contents = ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        return is_string($contents) && $contents !== '' ? $contents : null;
    }

    private function targetDimensions(int $width, int $height): array
    {
        $longestSide = max($width, $height);

        if ($longestSide <= $this->maxDimension()) {
            return [$width, $height];
        }

        $ratio = $this->maxDimension() / $longestSide;

        return [
            max(1, (int) round($width * $ratio)),
            max(1, (int) round($height * $ratio)),
        ];
    }

    private function orientGdImage(\GdImage $image, string $path, string $mimeType): \GdImage
    {
        if (! function_exists('exif_read_data') || ! str_contains(strtolower($mimeType), 'jpeg')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);

        return match ($orientation) {
            2 => tap($image, static fn (\GdImage $gd): bool => imageflip($gd, IMG_FLIP_HORIZONTAL)),
            3 => imagerotate($image, 180, 0) ?: $image,
            4 => tap($image, static fn (\GdImage $gd): bool => imageflip($gd, IMG_FLIP_VERTICAL)),
            5 => $this->rotateAndFlip($image, -90),
            6 => imagerotate($image, -90, 0) ?: $image,
            7 => $this->rotateAndFlip($image, 90),
            8 => imagerotate($image, 90, 0) ?: $image,
            default => $image,
        };
    }

    private function rotateAndFlip(\GdImage $image, int $angle): \GdImage
    {
        $rotated = imagerotate($image, $angle, 0) ?: $image;
        imageflip($rotated, IMG_FLIP_HORIZONTAL);

        return $rotated;
    }
}
