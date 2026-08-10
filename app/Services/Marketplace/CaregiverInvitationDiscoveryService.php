<?php

namespace App\Services\Marketplace;

use App\Models\CareBooking;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestInvitation;
use App\Models\FamilyCaregiverFavorite;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Matching\CaregiverSuggestionService;
use App\Support\CaregiverCertificationCriteria;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CaregiverInvitationDiscoveryService
{
    public const SEARCH_LIMIT = 12;

    public const SECTION_LIMIT = 6;

    public function __construct(
        private readonly CaregiverSuggestionService $suggestions,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function search(
        CareRequest $request,
        User $family,
        string $search,
        ?CaregiverCertificationCriteria $criteria = null,
    ): Collection {
        $this->authorize($request, $family);
        $criteria ??= CaregiverCertificationCriteria::empty();

        $term = trim($search);
        if (Str::length($term) < 2) {
            return collect();
        }

        $safeTerm = str_replace(['%', '_'], '', $term);
        if (Str::length($safeTerm) < 2) {
            return collect();
        }

        $profiles = $this->eligibleProfilesQuery($criteria)
            ->whereHas('user', fn (Builder $query) => $query->where('role', 'caregiver'))
            ->where(function (Builder $profileQuery) use ($safeTerm): void {
                $profileQuery->whereHas('user', function (Builder $userQuery) use ($safeTerm): void {
                    $userQuery->where('name', 'like', '%'.$safeTerm.'%')
                        ->orWhere('city', 'like', '%'.$safeTerm.'%');
                });

                app(CaregiverCertificationFilter::class)->orWhereTextMatches($profileQuery, $safeTerm);
            })
            ->orderByDesc('top_caregiver')
            ->orderByDesc('average_rating')
            ->orderByDesc('reviews_count')
            ->limit(self::SEARCH_LIMIT)
            ->get();

        return $this->cards($request, $profiles, $criteria);
    }

    /**
     * @return array<int, array{key:string,title:string,description:string,caregivers:Collection<int, array<string, mixed>>}>
     */
    public function initialSections(
        CareRequest $request,
        User $family,
        ?CaregiverCertificationCriteria $criteria = null,
    ): array {
        $this->authorize($request, $family);
        $criteria ??= CaregiverCertificationCriteria::empty();

        $previousIds = CareBooking::query()
            ->forFamilyAccount(app(FamilyAccountContext::class)->account($family))
            ->where('status', '!=', CareBooking::STATUS_CANCELLED)
            ->selectRaw('caregiver_user_id, MAX(id) as latest_booking_id')
            ->groupBy('caregiver_user_id')
            ->orderByDesc('latest_booking_id')
            ->pluck('caregiver_user_id');

        $favoriteIds = FamilyCaregiverFavorite::query()
            ->forFamilyAccount(app(FamilyAccountContext::class)->account($family))
            ->whereNotIn('caregiver_user_id', $previousIds)
            ->latest('created_at')
            ->pluck('caregiver_user_id');

        $recommendedIds = $this->suggestions
            ->topMatchesForRequest($request, self::SECTION_LIMIT, $criteria)
            ->pluck('user_id')
            ->diff($previousIds)
            ->diff($favoriteIds)
            ->values();

        return [
            [
                'key' => 'previous',
                'title' => 'Caregivers you hired before',
                'description' => 'People who have already provided care for your family.',
                'caregivers' => $this->cardsForIds($request, $previousIds, $criteria)->take(self::SECTION_LIMIT),
            ],
            [
                'key' => 'favorites',
                'title' => 'Saved caregivers',
                'description' => 'Caregivers you saved while browsing profiles.',
                'caregivers' => $this->cardsForIds($request, $favoriteIds, $criteria)->take(self::SECTION_LIMIT),
            ],
            [
                'key' => 'recommended',
                'title' => 'Recommended for this request',
                'description' => 'Available caregivers whose location and schedule look like a good fit.',
                'caregivers' => $this->cardsForIds($request, $recommendedIds, $criteria)->take(self::SECTION_LIMIT),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function caregiver(
        CareRequest $request,
        User $family,
        int $caregiverUserId,
        ?CaregiverCertificationCriteria $criteria = null,
    ): ?array {
        $this->authorize($request, $family);
        $criteria ??= CaregiverCertificationCriteria::empty();

        $profile = $this->eligibleProfilesQuery($criteria)
            ->where('user_id', $caregiverUserId)
            ->whereHas('user', fn (Builder $query) => $query->where('role', 'caregiver'))
            ->first();

        if (! $profile) {
            return null;
        }

        return $this->cards($request, collect([$profile]), $criteria)->first();
    }

    private function authorize(CareRequest $request, User $family): void
    {
        if ($family->role !== 'family' || ! app(FamilyAccountContext::class)->canAccessRecord($family, $request)) {
            throw new AuthorizationException('You cannot search caregivers for this request.');
        }
    }

    private function eligibleProfilesQuery(?CaregiverCertificationCriteria $criteria = null): Builder
    {
        $criteria ??= CaregiverCertificationCriteria::empty();
        $query = CaregiverProfile::query()
            ->select([
                'id',
                'user_id',
                'slug',
                'profile_photo_path',
                'status',
                'bio',
                'years_experience',
                'service_area_zip',
                'service_radius_miles',
                'is_accepting_new_clients',
                'identity_verified_at',
                'identity_verification_status',
                'background_check_verified_at',
                'top_caregiver',
                'average_rating',
                'reviews_count',
                'reliability_score',
            ])
            ->with([
                'user:id,name,role,city,state',
                'availabilities:id,caregiver_profile_id,day_of_week,start_time,end_time',
                'skills:id,name',
                'languages:id,name',
                'careExperiences:id,label,sort_order,active',
                'publicSearchCertifications',
            ])
            ->where('status', 'active')
            ->whereNotNull('bio')
            ->where('bio', '!=', '')
            ->whereNotNull('years_experience')
            ->whereNotNull('service_area_zip')
            ->where('service_area_zip', '!=', '')
            ->whereNotNull('service_radius_miles')
            ->where(function (Builder $query): void {
                $query->whereNotNull('identity_verified_at')
                    ->orWhere('identity_verification_status', 'approved');
            })
            ->whereHas('skills')
            ->whereHas('languages')
            ->whereHas('availabilities');

        return app(CaregiverCertificationFilter::class)->apply($query, $criteria);
    }

    /**
     * @param  Collection<int, int|string>  $caregiverIds
     * @return Collection<int, array<string, mixed>>
     */
    private function cardsForIds(
        CareRequest $request,
        Collection $caregiverIds,
        CaregiverCertificationCriteria $criteria,
    ): Collection {
        if ($caregiverIds->isEmpty()) {
            return collect();
        }

        $profiles = $this->eligibleProfilesQuery($criteria)
            ->whereIn('user_id', $caregiverIds->all())
            ->whereHas('user', fn (Builder $query) => $query->where('role', 'caregiver'))
            ->get()
            ->keyBy('user_id');

        $ordered = $caregiverIds
            ->map(fn ($id) => $profiles->get((int) $id))
            ->filter()
            ->values();

        return $this->cards($request, $ordered, $criteria);
    }

    /**
     * @param  Collection<int, CaregiverProfile>  $profiles
     * @return Collection<int, array<string, mixed>>
     */
    private function cards(
        CareRequest $request,
        Collection $profiles,
        CaregiverCertificationCriteria $criteria,
    ): Collection {
        $caregiverIds = $profiles->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        if ($caregiverIds === []) {
            return collect();
        }

        $applications = $request->applications()
            ->whereIn('caregiver_user_id', $caregiverIds)
            ->with('conversation:id,care_request_application_id')
            ->get()
            ->keyBy('caregiver_user_id');
        $invitations = $request->invitations()
            ->whereIn('caregiver_user_id', $caregiverIds)
            ->get()
            ->keyBy('caregiver_user_id');

        return $profiles->map(function (CaregiverProfile $profile) use ($request, $applications, $invitations, $criteria): array {
            $user = $profile->user;
            $application = $applications->get($profile->user_id);
            $invitation = $invitations->get($profile->user_id);
            $relationship = $this->relationship($request, $profile, $application, $invitation);
            $name = (string) $user?->name;
            $firstName = trim((string) Str::of($name)->before(' '));

            return [
                'user_id' => (int) $profile->user_id,
                'name' => $name,
                'first_name' => $firstName !== '' ? $firstName : 'caregiver',
                'initials' => Str::of($name)->trim()->explode(' ')->filter()->take(2)
                    ->map(fn ($part) => Str::upper(Str::substr((string) $part, 0, 1)))->implode(''),
                'city' => (string) $user?->city,
                'state' => (string) $user?->state,
                'profile_photo_path' => $profile->profile_photo_path,
                'profile_url' => route('caregivers.show', [
                    'slug' => $profile->slug,
                    'careRequest' => $request->id,
                ]),
                'availability' => $this->availabilityLabel($request, $profile),
                'identity_verified' => $profile->hasIdentityVerifiedBadge(),
                'background_check' => $profile->hasBackgroundCheckBadge(),
                'top_caregiver' => $profile->hasTopCaregiverBadge(),
                'average_rating' => (float) $profile->average_rating,
                'reviews_count' => (int) $profile->reviews_count,
                'accepting_new_clients' => (bool) $profile->is_accepting_new_clients,
                'certification_summary' => $profile->publicCertificationSummary($criteria, 3),
                'care_experience_tags' => $profile->publicCareExperienceTags(3),
                'care_background_tags' => $profile->publicCareBackgroundTags(3),
                ...$relationship,
            ];
        })->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function relationship(
        CareRequest $request,
        CaregiverProfile $profile,
        ?CareRequestApplication $application,
        ?CareRequestInvitation $invitation,
    ): array {
        $requestOpen = $request->status === CareRequest::STATUS_OPEN;

        if ($application) {
            $isHired = $application->status === CareRequestApplication::STATUS_HIRED;

            return [
                'relationship_state' => $isHired ? 'hired' : 'replied',
                'status_label' => $isHired ? 'Selected caregiver' : 'Already replied',
                'status_detail' => $isHired ? 'This caregiver was selected for this request.' : 'Open the caregiver’s reply to continue.',
                'can_invite' => false,
                'can_reinvite' => false,
                'reply_url' => route('family.requests.show', ['careRequest' => $request->id, 'tab' => 'applicants']).'#caregiver-comparison-list',
                'invited_at' => null,
            ];
        }

        if ($invitation) {
            $status = $invitation->isExpired() ? CareRequestInvitation::STATUS_EXPIRED : $invitation->status;
            $label = match ($status) {
                CareRequestInvitation::STATUS_PENDING => 'Invitation sent',
                CareRequestInvitation::STATUS_ACCEPTED => 'Invitation accepted',
                CareRequestInvitation::STATUS_DECLINED => 'Invitation declined',
                CareRequestInvitation::STATUS_EXPIRED => 'Invitation expired',
                CareRequestInvitation::STATUS_CANCELLED => 'Invitation cancelled',
                default => 'Invitation handled',
            };
            $historical = in_array($status, [
                CareRequestInvitation::STATUS_DECLINED,
                CareRequestInvitation::STATUS_EXPIRED,
                CareRequestInvitation::STATUS_CANCELLED,
            ], true);

            return [
                'relationship_state' => $status,
                'status_label' => $label,
                'status_detail' => $status === CareRequestInvitation::STATUS_PENDING
                    ? 'Sent '.$invitation->created_at?->diffForHumans()
                    : 'This invitation is part of the request history.',
                'can_invite' => false,
                'can_reinvite' => $historical && $requestOpen && (bool) $profile->is_accepting_new_clients,
                'reply_url' => null,
                'invited_at' => $invitation->created_at?->toIso8601String(),
            ];
        }

        if (! $requestOpen) {
            return [
                'relationship_state' => 'request_unavailable',
                'status_label' => 'Request closed',
                'status_detail' => 'This request is no longer accepting invitations.',
                'can_invite' => false,
                'can_reinvite' => false,
                'reply_url' => null,
                'invited_at' => null,
            ];
        }

        if (! $profile->is_accepting_new_clients) {
            return [
                'relationship_state' => 'not_accepting',
                'status_label' => 'Not accepting new clients',
                'status_detail' => 'You can still view the profile, but an invitation is unavailable right now.',
                'can_invite' => false,
                'can_reinvite' => false,
                'reply_url' => null,
                'invited_at' => null,
            ];
        }

        return [
            'relationship_state' => 'available',
            'status_label' => 'Available to invite',
            'status_detail' => 'Accepting new clients.',
            'can_invite' => true,
            'can_reinvite' => false,
            'reply_url' => null,
            'invited_at' => null,
        ];
    }

    private function availabilityLabel(CareRequest $request, CaregiverProfile $profile): string
    {
        $ranges = $profile->availabilities;

        if ($request->request_type === CareRequest::TYPE_ONE_TIME) {
            $start = $request->requested_start_at;
            $end = $request->requested_end_at;
            if (! $start || ! $end) {
                return 'Schedule not provided';
            }

            $matches = $ranges->contains(function ($range) use ($start, $end): bool {
                return (int) $range->day_of_week === (int) $start->dayOfWeek
                    && $this->timesOverlap(
                        $this->timeToMinutes((string) $range->start_time),
                        $this->timeToMinutes((string) $range->end_time),
                        $this->timeToMinutes($start),
                        $this->timeToMinutes($end),
                    );
            });

            return $matches ? 'Matches the requested time' : 'Schedule may not match';
        }

        $days = collect($request->recurring_days ?? [])->map(fn ($day) => (int) $day)->unique();
        if ($days->isEmpty()) {
            return 'Recurring schedule not provided';
        }

        $matchedDays = $days->filter(function (int $day) use ($ranges, $request): bool {
            return $ranges->contains(function ($range) use ($day, $request): bool {
                return (int) $range->day_of_week === $day
                    && $this->timesOverlap(
                        $this->timeToMinutes((string) $range->start_time),
                        $this->timeToMinutes((string) $range->end_time),
                        $this->timeToMinutes((string) $request->recurring_start_time),
                        $this->timeToMinutes((string) $request->recurring_end_time),
                    );
            });
        })->count();

        if ($matchedDays === 0) {
            return 'Schedule may not match';
        }

        return $matchedDays === $days->count()
            ? 'Matches the recurring schedule'
            : 'Matches '.$matchedDays.' of '.$days->count().' requested days';
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

        [$hour, $minute] = array_pad(explode(':', $value), 2, 0);

        return ((int) $hour * 60) + (int) $minute;
    }
}
