<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportRequestDraft;
use App\Models\CareRecipientProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Auth\Access\AuthorizationException;

class FamilyProfileRequestStateReader
{
    public function __construct(private readonly FamilyAccountContext $familyAccounts) {}

    /** @return array<string,mixed> */
    public function read(User $actor): array
    {
        $account = $this->authorizedAccount($actor);
        $defaultId = (int) ($account->default_care_recipient_profile_id ?? 0);

        $profiles = CareRecipientProfile::query()
            ->forFamilyAccount($account)
            ->withCount(['requestRecipients', 'carePlans', 'continuousCoveragePlans'])
            ->latest('updated_at')
            ->get()
            ->map(function (CareRecipientProfile $profile) use ($defaultId): array {
                $missing = [];
                if (trim((string) $profile->preferred_name) === '') {
                    $missing[] = 'preferred name';
                }
                if (! $profile->hasMeaningfulShareableContent()) {
                    $missing[] = 'at least one helpful care detail';
                }
                if (! $profile->sharing_acknowledged_at) {
                    $missing[] = 'caregiver-sharing confirmation';
                }

                return [
                    'id' => (int) $profile->id,
                    'name' => $profile->displayName(),
                    'status' => (string) $profile->status,
                    'ready' => $profile->isReady(),
                    'archived' => $profile->isArchived(),
                    'default' => $defaultId === (int) $profile->id,
                    'revision' => (int) $profile->revision,
                    'missing_for_ready' => $profile->isReady() ? [] : $missing,
                    'dependencies' => [
                        'requests' => (int) $profile->request_recipients_count,
                        'regular_care' => (int) $profile->care_plans_count,
                        'continuous_coverage' => (int) $profile->continuous_coverage_plans_count,
                    ],
                    'updated_at' => $profile->updated_at?->toIso8601String(),
                ];
            })->values()->all();

        $requests = CareRequest::query()
            ->forFamilyAccount($account)
            ->with(['recipient:id,care_request_id,full_name', 'booking:id,care_request_id,status'])
            ->withCount([
                'applications as active_application_count' => fn ($query) => $query->whereIn('status', [
                    CareRequestApplication::STATUS_APPLIED,
                    CareRequestApplication::STATUS_SHORTLISTED,
                ]),
                'invitations as pending_invitation_count' => fn ($query) => $query->where('status', 'pending'),
            ])
            ->latest('updated_at')
            ->limit(20)
            ->get()
            ->map(fn (CareRequest $request): array => [
                'id' => (int) $request->id,
                'title' => trim((string) ($request->title ?: 'Care request #'.$request->id)),
                'recipient' => trim((string) ($request->recipient?->full_name ?: 'Care receiver')),
                'type' => (string) $request->request_type,
                'status' => (string) $request->status,
                'status_label' => $this->requestStatusLabel($request),
                'blocker' => $this->requestBlocker($request),
                'active_applications' => (int) $request->active_application_count,
                'pending_invitations' => (int) $request->pending_invitation_count,
                'has_visit' => $request->booking !== null,
                'updated_at' => $request->updated_at?->toIso8601String(),
            ])->values()->all();

        $chatDraft = AiSupportRequestDraft::query()
            ->where('family_account_id', $account->id)
            ->where('actor_user_id', $actor->id)
            ->latest('updated_at')
            ->first();

        return [
            'profiles' => $profiles,
            'requests' => $requests,
            'chat_draft' => $chatDraft && $chatDraft->isUsable() ? [
                'id' => (string) $chatDraft->id,
                'type' => (string) $chatDraft->request_type,
                'state' => (string) $chatDraft->state,
                'version' => (int) $chatDraft->version,
                'last_error_code' => $chatDraft->last_error_code,
                'expires_at' => $chatDraft->expires_at?->toIso8601String(),
            ] : null,
            'state_hash' => hash('sha256', json_encode([
                collect($profiles)->map(fn (array $profile) => [$profile['id'], $profile['status'], $profile['revision']])->all(),
                collect($requests)->map(fn (array $request) => [$request['id'], $request['status'], $request['updated_at']])->all(),
                $chatDraft?->id,
                $chatDraft?->version,
            ], JSON_THROW_ON_ERROR)),
        ];
    }

    /** @return array<string,mixed>|null */
    public function profile(User $actor, ?int $profileId = null, bool $includeArchived = false): ?array
    {
        $state = $this->read($actor);
        $profiles = collect($state['profiles'])->when(! $includeArchived, fn ($rows) => $rows->where('archived', false));

        return $profileId ? $profiles->firstWhere('id', $profileId) : $profiles->first();
    }

    /** @return array<string,mixed>|null */
    public function request(User $actor, ?int $requestId = null): ?array
    {
        $requests = collect($this->read($actor)['requests']);

        return $requestId ? $requests->firstWhere('id', $requestId) : $requests->first();
    }

    private function requestStatusLabel(CareRequest $request): string
    {
        return match ($request->status) {
            CareRequest::STATUS_DRAFT => 'Draft — not visible to caregivers',
            CareRequest::STATUS_OPEN => 'Live — caregivers can apply',
            CareRequest::STATUS_FILLED => 'Caregiver selected',
            CareRequest::STATUS_CANCELLED => 'Withdrawn',
            CareRequest::STATUS_EXPIRED => 'Expired',
            default => 'Status unavailable',
        };
    }

    private function requestBlocker(CareRequest $request): ?string
    {
        return match ($request->status) {
            CareRequest::STATUS_DRAFT => 'This request has not been published.',
            CareRequest::STATUS_CANCELLED => 'A withdrawn request cannot be reopened; start a fresh copy instead.',
            CareRequest::STATUS_EXPIRED => 'An expired request cannot be reopened; start a fresh copy instead.',
            CareRequest::STATUS_FILLED => 'This request already has a selected caregiver; use the visit or recurring care flow for changes.',
            default => null,
        };
    }

    private function authorizedAccount(User $actor): mixed
    {
        if ($actor->role !== 'family' || ! $this->familyAccounts->membershipFor($actor, false)) {
            throw new AuthorizationException('An active Family Account is required.');
        }

        return $this->familyAccounts->account($actor);
    }
}
