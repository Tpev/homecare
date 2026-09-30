<?php

namespace App\Support;

use App\Models\CaregiverProfile;
use Illuminate\Support\Str;

class CaregiverSeo
{
    /** @return array<string, string> */
    public static function profile(CaregiverProfile $profile): array
    {
        $profile->loadMissing(['user', 'skills', 'languages', 'availabilities']);
        $name = self::plainText($profile->user?->name);
        $city = self::plainText($profile->user?->city);
        $state = self::plainText($profile->user?->state);
        $location = implode(', ', array_filter([$city, $state]));
        $bio = self::plainText($profile->bio);

        // Match directory completeness and exclusions, with enough public detail
        // to support a useful search result. Direct access for existing clients is unchanged.
        $indexable = ! CaregiverPrelaunch::enabled()
            && $profile->status === 'active'
            && ! in_array($profile->slug, config('marketplace.caregiver_discovery_excluded_slugs', []), true)
            && $name !== '' && $city !== '' && $state !== ''
            && mb_strlen($bio) >= 100
            && filled($profile->platform_hourly_rate)
            && filled($profile->years_experience)
            && filled($profile->service_area_zip)
            && filled($profile->service_radius_miles)
            && $profile->skills->isNotEmpty()
            && $profile->languages->isNotEmpty()
            && $profile->availabilities->isNotEmpty();

        return [
            'title' => ($name ?: 'Caregiver').($location ? ' — '.$location.' caregiver' : ' — Caregiver profile').' | LoLo Care',
            'description' => $bio !== ''
                ? Str::limit($bio, 155)
                : 'View this caregiver’s experience and non-medical support options on LoLo Care.',
            'canonical' => route('caregivers.show', ['slug' => $profile->slug]),
            'robots' => $indexable ? 'index,follow' : 'noindex,follow',
            'image' => asset('images/marketing/lolo-hero.jpg'),
            'image_alt' => 'LoLo Care — trusted help at home',
        ];
    }

    /** @return array<string, string> */
    public static function directory(): array
    {
        // Campaign tags do not change the content; filters and pagination do.
        $hasFilters = collect(array_keys(request()->query()))->contains(
            fn (string $key): bool => ! str_starts_with($key, 'utm_')
                && ! in_array($key, ['gclid', 'gbraid', 'wbraid', 'fbclid', 'msclkid'], true)
        );

        return [
            'title' => 'Find Caregivers in Raleigh & the Triangle | LoLo Care',
            'description' => 'Explore local caregivers for companionship and non-medical help at home. Compare experience, skills and availability with LoLo Care.',
            'canonical' => route('caregivers.search'),
            'robots' => $hasFilters || CaregiverPrelaunch::enabled() ? 'noindex,follow' : 'index,follow',
            'image' => asset('images/marketing/lolo-hero.jpg'),
            'image_alt' => 'LoLo Care — trusted help at home',
        ];
    }

    private static function plainText(?string $value): string
    {
        return Str::squish(strip_tags(html_entity_decode($value ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
