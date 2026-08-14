<?php

namespace App\Services\AiSupport;

use App\Models\CareRecipientProfile;
use App\Models\CareRequest;
use App\Models\CareTask;
use App\Models\FamilyHouseholdProfile;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Auth\Access\AuthorizationException;

class AiSupportFamilyContextService
{
    public function __construct(
        private readonly FamilyAccountContext $families,
        private readonly AiSupportEligibilityService $eligibility,
        private readonly AiSupportEventRecorder $events,
    ) {}

    /** @return array<string,mixed> */
    public function read(User $actor, SupportTicket $ticket, bool $includePreviousRequest = false): array
    {
        if ($actor->role !== 'family'
            || ! $this->eligibility->evaluate($actor, 'family_context_v1', $ticket)->allowed) {
            throw new AuthorizationException;
        }

        $membership = $this->families->membership($actor);
        $account = $membership->familyAccount;
        if ((int) $ticket->family_account_id !== (int) $account->id) {
            throw new AuthorizationException;
        }

        $profiles = CareRecipientProfile::query()
            ->where('family_account_id', $account->id)
            ->where('status', CareRecipientProfile::STATUS_READY)
            ->whereNull('archived_at')
            ->orderBy('id')
            ->get()
            ->map(fn (CareRecipientProfile $profile): array => [
                'id' => $profile->id,
                'version_id' => $profile->latest_ready_version_id,
                'name' => $profile->displayName(),
                'recipient_is_requester' => (bool) $profile->recipient_is_requester,
                'relationship' => $profile->relationship_to_family,
            ])->values()->all();

        $household = FamilyHouseholdProfile::query()
            ->where('family_account_id', $account->id)
            ->first();

        $previous = null;
        if ($includePreviousRequest) {
            $request = CareRequest::query()
                ->where('family_account_id', $account->id)
                ->with(['recipient', 'tasks:id,name'])
                ->latest('created_at')
                ->first();
            if ($request) {
                $previous = [
                    'id' => $request->id,
                    'request_type' => $request->request_type,
                    'recipient' => $request->recipient?->full_name,
                    'task_ids' => $request->tasks->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                    'address' => [
                        'line1' => $request->address_line1,
                        'line2' => $request->address_line2,
                        'city' => $request->city,
                        'state' => $request->state,
                        'zip' => $request->zip,
                    ],
                ];
            }
        }

        $this->events->record($ticket, 'authorized_context_read', [
            'capability_id' => 'family_context_v1',
            'result_code' => $includePreviousRequest ? 'current_and_explicit_previous' : 'current_only',
        ], $actor);

        return [
            'family_account_reference' => (string) $account->id,
            'recipient_profiles' => $profiles,
            'household' => $household ? [
                'address_line1' => $household->address_line1,
                'address_line2' => $household->address_line2,
                'city' => $household->city,
                'state' => $household->state,
                'zip' => $household->zip,
                'home_access_notes' => $household->home_access_notes,
                'preferred_response_hours' => (int) ($household->preferred_response_hours ?: 12),
            ] : null,
            'care_tasks' => CareTask::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (CareTask $task): array => ['id' => $task->id, 'name' => $task->name])
                ->values()->all(),
            'previous_request' => $previous,
        ];
    }
}
