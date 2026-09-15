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
use App\Support\CaregiverCertificationCriteria;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CaregiverInvitationDiscoveryService
{
    public const SEARCH_LIMIT = 12;

    public const MAX_DISCOVERY_LIMIT = 40;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function search(
        CareRequest $request,
        User $family,
        string $search,
        ?CaregiverCertificationCriteria $criteria = null,
        int $limit = self::SEARCH_LIMIT,
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
            ->orderBy('user_id')
            ->limit(max(1, min(self::MAX_DISCOVERY_LIMIT, $limit)))
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
        int $limit = self::SEARCH_LIMIT,
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

        // Rank the whole eligible pool before limiting it. Saved availability
        // does not determine who families can discover or invite.
        $query = $this->eligibleProfilesQuery($criteria);
        $this->prioritizeIds($query, $previousIds);
        $this->prioritizeIds($query, $favoriteIds);
        $query->orderByDesc('has_platform_visits');
        $profiles = $query
            ->orderByRaw('CASE WHEN service_area_zip = ? THEN 0 ELSE 1 END', [$request->zip])
            ->orderByRaw('CASE WHEN EXISTS (SELECT 1 FROM users WHERE users.id = caregiver_profiles.user_id AND users.city = ? AND users.state = ?) THEN 0 ELSE 1 END', [$request->city, $request->state])
            ->orderByRaw('CASE WHEN EXISTS (SELECT 1 FROM users WHERE users.id = caregiver_profiles.user_id AND users.state = ?) THEN 0 ELSE 1 END', [$request->state])
            ->orderByDesc('is_accepting_new_clients')
            ->orderByDesc('top_caregiver')
            ->orderByDesc('average_rating')
            ->orderByDesc('reviews_count')
            ->orderBy('user_id')
            ->limit(max(1, min(self::MAX_DISCOVERY_LIMIT, $limit)))
            ->get();
        $cards = $this->cards($request, $profiles, $criteria);

        return [
            [
                'key' => 'previous',
                'title' => 'Caregivers you hired before',
                'description' => 'People who have already provided care for your family.',
                'caregivers' => $cards->filter(fn (array $card) => $previousIds->contains($card['user_id']))->values(),
            ],
            [
                'key' => 'favorites',
                'title' => 'Saved caregivers',
                'description' => 'Caregivers you saved while browsing profiles.',
                'caregivers' => $cards->filter(fn (array $card) => $favoriteIds->contains($card['user_id']))->values(),
            ],
            [
                'key' => 'recommended',
                'title' => 'Recommended for this request',
                'description' => 'Caregivers with completed LoLo visits first, followed by more profiles near you.',
                'caregivers' => $cards->reject(fn (array $card) => $previousIds->contains($card['user_id']) || $favoriteIds->contains($card['user_id']))->values(),
            ],
        ];
    }

    private function prioritizeIds(Builder $query, Collection $ids): void
    {
        if ($ids->isNotEmpty()) {
            $placeholders = implode(',', array_fill(0, $ids->count(), '?'));
            $query->orderByRaw('CASE WHEN caregiver_profiles.user_id IN ('.$placeholders.') THEN 0 ELSE 1 END', $ids->all());
        }
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
            ->discoverable()
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
            ->addSelect([
                // Only expose whether care was completed. Other families' visit
                // details remain private, even while ranking across the platform.
                'has_platform_visits' => CareBooking::query()
                    ->withoutGlobalScope('authenticated_family_account')
                    ->selectRaw('1')
                    ->whereColumn('caregiver_user_id', 'caregiver_profiles.user_id')
                    ->whereIn('status', [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED])
                    ->limit(1),
            ])
            ->with([
                'user:id,name,role,city,state',
                'skills:id,name',
                'languages:id,name',
                'careExperiences:id,label,sort_order,active',
                'publicSearchCertifications',
            ])
            ->where('status', 'active')
            ->whereHas('user', fn (Builder $query) => $query->where('role', 'caregiver'))
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
            ->whereHas('languages');

        return app(CaregiverCertificationFilter::class)->apply($query, $criteria);
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
                'identity_verified' => $profile->hasIdentityVerifiedBadge(),
                'background_check' => $profile->hasBackgroundCheckBadge(),
                'top_caregiver' => $profile->hasTopCaregiverBadge(),
                'has_platform_visits' => (bool) $profile->has_platform_visits,
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
}
