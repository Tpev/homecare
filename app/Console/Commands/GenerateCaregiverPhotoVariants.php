<?php

namespace App\Console\Commands;

use App\Models\CaregiverProfile;
use App\Services\Images\CaregiverProfilePhotoVariants;
use Illuminate\Console\Command;

class GenerateCaregiverPhotoVariants extends Command
{
    protected $signature = 'caregiver-photos:generate-variants {--force : Regenerate variants that already exist}';

    protected $description = 'Generate responsive WebP variants for caregiver profile photos';

    public function handle(CaregiverProfilePhotoVariants $variants): int
    {
        $profiles = 0;
        $generated = 0;
        $incomplete = 0;
        $force = (bool) $this->option('force');

        CaregiverProfile::query()
            ->whereNotNull('profile_photo_path')
            ->select(['id', 'profile_photo_path'])
            ->orderBy('id')
            ->chunkById(100, function ($caregivers) use ($variants, $force, &$profiles, &$generated, &$incomplete): void {
                foreach ($caregivers as $caregiver) {
                    $profiles++;
                    $generated += $variants->generate((string) $caregiver->profile_photo_path, $force);

                    if (! $variants->hasAll((string) $caregiver->profile_photo_path)) {
                        $incomplete++;
                    }
                }
            });

        $this->info("Checked {$profiles} caregiver photo(s); generated {$generated} responsive variant(s).");

        if ($incomplete > 0) {
            $this->warn("{$incomplete} photo(s) could not produce every variant; their original image remains available.");
        }

        return self::SUCCESS;
    }
}
