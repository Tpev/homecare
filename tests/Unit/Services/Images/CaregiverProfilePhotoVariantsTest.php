<?php

namespace Tests\Unit\Services\Images;

use App\Services\Images\CaregiverProfilePhotoVariants;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CaregiverProfilePhotoVariantsTest extends TestCase
{
    public function test_it_generates_responsive_webp_variants_with_predictable_paths(): void
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            $this->markTestSkipped('GD WebP support is required for this test.');
        }

        Storage::fake('public');
        config([
            'marketplace.caregiver_profile_photo.responsive_widths' => [480, 768],
            'marketplace.caregiver_profile_photo.responsive_quality' => 75,
        ]);

        $image = imagecreatetruecolor(900, 600);
        $color = imagecolorallocate($image, 23, 63, 53);
        imagefilledrectangle($image, 0, 0, 899, 599, $color);
        ob_start();
        imagejpeg($image, null, 85);
        $jpeg = ob_get_clean();
        imagedestroy($image);

        Storage::disk('public')->put('caregiver-photos/example.jpg', $jpeg);

        $variants = app(CaregiverProfilePhotoVariants::class);

        $this->assertSame(2, $variants->generate('caregiver-photos/example.jpg'));
        Storage::disk('public')->assertExists('caregiver-photos/example-480.webp');
        Storage::disk('public')->assertExists('caregiver-photos/example-768.webp');
        $this->assertTrue($variants->hasAll('caregiver-photos/example.jpg'));
        $this->assertSame(0, $variants->generate('caregiver-photos/example.jpg'));
    }
}
