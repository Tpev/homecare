<?php

namespace App\Services\Matching;

use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Services\Marketplace\CaregiverCertificationFilter;
use App\Support\CaregiverCertificationCriteria;
use App\Support\CaregiverPrelaunch;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class CaregiverSuggestionService
{
    /**
     * @return Collection<int, array{user_id:int,name:string,profile_photo_path:?string,score:int,proximity:string,reasons:array<int,string>,hourly_rate:float,average_rating:float,reviews_count:int,identity_verified:bool,background_check:bool,top_caregiver:bool}>
     */
    public function topMatchesForRequest(
        CareRequest $request,
        int $limit = 3,
        ?CaregiverCertificationCriteria $criteria = null,
    ): Collection {
        if (CaregiverPrelaunch::enabled()) {
            return collect();
        }

        $criteria ??= CaregiverCertificationCriteria::empty();
        $excluded = collect()
            ->merge($request->applications()->pluck('caregiver_user_id'))
            ->merge($request->invitations()->pluck('caregiver_user_id'))
            ->unique()
            ->values();

        $profileQuery = CaregiverProfile::query()
            ->with([
                'user:id,name,city,state',
                'availabilities',
                'skills:id,name',
                'languages:id,name',
                'careExperiences:id,label,sort_order,active',
                'publicSearchCertifications',
            ])
            ->where('status', 'active')
            ->where('is_accepting_new_clients', true)
            ->whereNotNull('bio')
            ->whereNotNull('years_experience')
            ->whereNotNull('service_area_zip')
            ->whereNotNull('service_radius_miles')
            ->where(function ($query): void {
                $query->whereNotNull('identity_verified_at')
                    ->orWhere('identity_verification_status', 'approved');
            })
            ->whereHas('skills')
            ->whereHas('languages')
            ->whereHas('availabilities')
            ->whereHas('user', fn ($query) => $query->where('role', 'caregiver'))
            ->whereNotIn('user_id', $excluded);

        app(CaregiverCertificationFilter::class)->apply($profileQuery, $criteria);

        $profiles = $profileQuery
            ->orderByDesc('top_caregiver')
            ->orderByDesc('average_rating')
            ->limit(max(24, $limit * 8))
            ->get();

        return $profiles
            ->map(fn (CaregiverProfile $profile) => $this->scoreProfile($request, $profile, $criteria))
            ->filter(fn (?array $row) => $row !== null)
            ->sortByDesc('score')
            ->values()
            ->take($limit);
    }

    /**
     * @return array{user_id:int,name:string,profile_photo_path:?string,score:int,proximity:string,reasons:array<int,string>,hourly_rate:float,average_rating:float,reviews_count:int,identity_verified:bool,background_check:bool,top_caregiver:bool}|null
     */
    private function scoreProfile(
        CareRequest $request,
        CaregiverProfile $profile,
        CaregiverCertificationCriteria $criteria,
    ): ?array {
        $user = $profile->user;
        if (! $user || ! $profile->isMarketplaceReady()) {
            return null;
        }

        $score = 0;
        $reasons = [];
        $proximity = $this->proximityScore($request, $profile);
        $score += $proximity['score'];
        $reasons[] = $proximity['label'];

        $availability = $this->availabilityScore($request, $profile);
        if ($availability['score'] <= 0) {
            return null;
        }
        $score += $availability['score'];
        $reasons[] = $availability['label'];

        if ($profile->hasIdentityVerifiedBadge()) {
            $score += 12;
            $reasons[] = 'Identity verified';
        }
        if ($profile->hasBackgroundCheckBadge()) {
            $score += 12;
            $reasons[] = 'Background check';
        }
        if ($profile->hasTopCaregiverBadge()) {
            $score += 10;
            $reasons[] = 'Top caregiver';
        }

        $score += (int) min(18, round(((float) $profile->reliability_score / 100) * 18));
        $score += (int) min(10, round((((float) $profile->invite_response_rate ?: 0) / 100) * 10));

        $rating = (float) $profile->average_rating;
        if ($rating > 0) {
            $score += (int) min(10, round($rating * 2));
        }

        return [
            'user_id' => (int) $profile->user_id,
            'name' => (string) $user->name,
            'profile_photo_path' => $profile->profile_photo_path,
            'score' => max(1, $score),
            'proximity' => $proximity['label'],
            'reasons' => collect($reasons)->filter()->unique()->values()->all(),
            'hourly_rate' => (float) $profile->resolvePlatformHourlyRate(),
            'average_rating' => (float) $profile->average_rating,
            'reviews_count' => (int) $profile->reviews_count,
            'identity_verified' => $profile->hasIdentityVerifiedBadge(),
            'background_check' => $profile->hasBackgroundCheckBadge(),
            'top_caregiver' => $profile->hasTopCaregiverBadge(),
            'certification_summary' => $profile->publicCertificationSummary($criteria, 3),
            'care_experience_tags' => $profile->publicCareExperienceTags(3),
            'care_background_tags' => $profile->publicCareBackgroundTags(3),
        ];
    }

    /**
     * @return array{score:int,label:string}
     */
    private function proximityScore(CareRequest $request, CaregiverProfile $profile): array
    {
        if ($profile->service_area_zip && $request->zip && $profile->service_area_zip === $request->zip) {
            return ['score' => 28, 'label' => 'Same ZIP service area'];
        }

        if (strcasecmp((string) $profile->user?->city, (string) $request->city) === 0) {
            return ['score' => 20, 'label' => 'Same city'];
        }

        if (strcasecmp((string) $profile->user?->state, (string) $request->state) === 0) {
            return ['score' => 12, 'label' => 'Same state'];
        }

        return ['score' => 3, 'label' => 'Outside primary area'];
    }

    /**
     * @return array{score:int,label:string}
     */
    private function availabilityScore(CareRequest $request, CaregiverProfile $profile): array
    {
        $ranges = $profile->availabilities;
        if ($ranges->isEmpty()) {
            return ['score' => 0, 'label' => 'No availability set'];
        }

        if ($request->request_type === CareRequest::TYPE_ONE_TIME) {
            $start = $request->requested_start_at;
            $end = $request->requested_end_at;

            if (! $start || ! $end) {
                return ['score' => 0, 'label' => 'No request time provided'];
            }

            $matches = $ranges->filter(function ($range) use ($start, $end) {
                if ((int) $range->day_of_week !== (int) $start->dayOfWeek) {
                    return false;
                }

                return $this->timesOverlap(
                    $this->timeToMinutes($range->start_time),
                    $this->timeToMinutes($range->end_time),
                    $this->timeToMinutes($start),
                    $this->timeToMinutes($end)
                );
            });

            if ($matches->isEmpty()) {
                return ['score' => 0, 'label' => 'No overlap with requested time'];
            }

            return ['score' => 24, 'label' => 'Matches requested time window'];
        }

        $days = collect($request->recurring_days ?? [])
            ->map(fn ($d) => (int) $d)
            ->unique()
            ->values();
        if ($days->isEmpty()) {
            return ['score' => 0, 'label' => 'No recurring days set'];
        }

        $startMinutes = $this->timeToMinutes((string) $request->recurring_start_time);
        $endMinutes = $this->timeToMinutes((string) $request->recurring_end_time);

        $matchedDays = $days->filter(function (int $day) use ($ranges, $startMinutes, $endMinutes) {
            return $ranges->contains(function ($range) use ($day, $startMinutes, $endMinutes) {
                if ((int) $range->day_of_week !== $day) {
                    return false;
                }

                return $this->timesOverlap(
                    $this->timeToMinutes($range->start_time),
                    $this->timeToMinutes($range->end_time),
                    $startMinutes,
                    $endMinutes
                );
            });
        })->count();

        if ($matchedDays === 0) {
            return ['score' => 0, 'label' => 'No recurring overlap'];
        }

        $coverage = (int) round(($matchedDays / max(1, $days->count())) * 100);
        $score = 10 + (int) round(($coverage / 100) * 18);

        return [
            'score' => $score,
            'label' => 'Recurring match '.$coverage.'%',
        ];
    }

    private function timesOverlap(int $aStart, int $aEnd, int $bStart, int $bEnd): bool
    {
        return $aStart < $bEnd && $bStart < $aEnd;
    }

    private function timeToMinutes(CarbonInterface|string $value): int
    {
        if ($value instanceof CarbonInterface) {
            return ($value->hour * 60) + $value->minute;
        }

        $parts = explode(':', $value);
        if (count($parts) < 2) {
            return 0;
        }

        return ((int) $parts[0] * 60) + (int) $parts[1];
    }
}
