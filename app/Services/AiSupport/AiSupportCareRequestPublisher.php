<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportRequestDraft;
use App\Models\CareRecipientProfile;
use App\Models\CareRequest;
use App\Models\CareTask;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Support\FunnelTracker;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class AiSupportCareRequestPublisher
{
    public function __construct(
        private readonly FamilyAccountContext $families,
        private readonly AiSupportControlService $controls,
        private readonly AiSupportRequestDraftService $drafts,
    ) {}

    /**
     * @param  array<string,mixed>  $previewPayload
     * @return array{outcome_code:string,domain_reference_type:string,domain_reference_id:string,receipt_reference:string}
     */
    public function publish(User $actor, SupportTicket $ticket, array $previewPayload): array
    {
        $draft = AiSupportRequestDraft::query()->lockForUpdate()->find($previewPayload['draft_id'] ?? null);
        if (! $draft
            || (int) $draft->actor_user_id !== (int) $actor->id
            || (int) $draft->support_ticket_id !== (int) $ticket->id
            || ! $draft->isUsable()
            || $draft->version !== (int) ($previewPayload['draft_version'] ?? 0)
            || ! hash_equals((string) $draft->material_hash, (string) ($previewPayload['material_hash'] ?? ''))) {
            throw new AuthorizationException;
        }

        $account = $this->families->account($actor);
        if ((int) $draft->family_account_id !== (int) $account->id) {
            throw new AuthorizationException;
        }

        $commitControl = $draft->request_type === CareRequest::TYPE_ONE_TIME ? 'commit.one_time' : 'commit.recurring';
        if (! $this->controls->enabled($commitControl)) {
            throw new AuthorizationException;
        }

        $validation = $this->drafts->validatePayload($actor, $draft);
        if ($validation['errors'] !== []) {
            throw ValidationException::withMessages(['draft' => $validation['errors'][0]['message']]);
        }

        $payload = (array) $draft->payload;
        if ($this->materialHash($payload) !== (string) ($previewPayload['material_hash'] ?? '')) {
            throw new AuthorizationException;
        }

        $profile = null;
        if (! empty($payload['recipient_profile_id'])) {
            $profile = CareRecipientProfile::query()
                ->where('family_account_id', $account->id)
                ->where('status', CareRecipientProfile::STATUS_READY)
                ->whereNull('archived_at')
                ->find($payload['recipient_profile_id']);
            if (! $profile || (int) $profile->latest_ready_version_id !== (int) ($payload['recipient_profile_version_id'] ?? 0)) {
                throw ValidationException::withMessages(['draft' => 'The saved care recipient changed. Review that section again.']);
            }
        }

        $tasks = CareTask::query()->whereKey((array) $payload['task_ids'])->orderBy('name')->get();
        if ($tasks->count() !== count(array_unique((array) $payload['task_ids']))) {
            throw ValidationException::withMessages(['draft' => 'One kind of help is no longer available. Review that section again.']);
        }

        $oneTimeStart = $draft->request_type === CareRequest::TYPE_ONE_TIME
            ? CarbonImmutable::createFromFormat(
                'Y-m-d H:i',
                $payload['requested_start_date'].' '.$payload['requested_start_time'],
                'America/New_York',
            )
            : null;
        $recurringSchedule = $draft->request_type === CareRequest::TYPE_RECURRING
            ? $this->recurringSchedule((array) $payload['recurring_schedule'])
            : null;
        $firstRecurring = $recurringSchedule[0] ?? null;
        $ownership = $this->families->ownershipAttributes($actor);

        $careRequest = CareRequest::query()->create([
            ...$ownership,
            'created_by_user_id' => $actor->id,
            'is_system_generated' => false,
            'origin' => 'ai_support',
            'ai_support_ticket_id' => $ticket->id,
            'title' => $this->title($tasks->pluck('name')->all(), (string) $payload['recipient_full_name']),
            'additional_info' => filled($payload['additional_info'] ?? null) ? $payload['additional_info'] : null,
            'scope_of_work' => $this->scope($tasks, (array) ($payload['task_notes'] ?? [])),
            'time_expectations' => 'The schedule shown in this request is the requested care time.',
            'home_access_notes' => filled($payload['home_access_notes'] ?? null)
                ? $payload['home_access_notes']
                : 'Home access details will be shared after hire.',
            'preferred_response_hours' => (int) ($payload['preferred_response_hours'] ?? 12),
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => $draft->request_type,
            'requested_start_at' => $oneTimeStart,
            'requested_end_at' => $oneTimeStart?->addMinutes((int) $payload['duration_minutes']),
            'recurring_days' => $draft->request_type === CareRequest::TYPE_RECURRING
                ? array_values(array_unique(array_map('intval', (array) $payload['recurring_days'])))
                : null,
            'recurring_start_time' => $firstRecurring['start_time'] ?? null,
            'recurring_end_time' => $firstRecurring['end_time'] ?? null,
            'recurring_schedule' => $recurringSchedule,
            'recurring_starts_on' => $draft->request_type === CareRequest::TYPE_RECURRING ? $payload['recurring_starts_on'] : null,
            'recurring_ends_on' => $draft->request_type === CareRequest::TYPE_RECURRING ? (($payload['recurring_ends_on'] ?? null) ?: null) : null,
            'address_line1' => $payload['address_line1'],
            'address_line2' => ($payload['address_line2'] ?? null) ?: null,
            'city' => $payload['city'],
            'state' => $payload['state'],
            'zip' => $payload['zip'],
        ]);

        $careRequest->tasks()->sync($tasks->mapWithKeys(function (CareTask $task) use ($payload): array {
            $note = trim((string) (($payload['task_notes'] ?? [])[(string) $task->id] ?? ''));

            return [$task->id => ['task_note' => $note !== '' ? $note : null]];
        })->all());
        $careRequest->recipient()->create([
            'recipient_is_requester' => (bool) ($payload['recipient_is_requester'] ?? false),
            'full_name' => $payload['recipient_full_name'],
            'relationship_to_family' => ($payload['recipient_relationship'] ?? null)
                ?: ((bool) ($payload['recipient_is_requester'] ?? false) ? 'Self' : 'Family member'),
            'care_notes' => filled($payload['additional_info'] ?? null) ? $payload['additional_info'] : null,
            'care_recipient_profile_id' => $profile?->id,
            'care_recipient_profile_version_id' => $profile?->latest_ready_version_id,
        ]);

        $draft->forceFill([
            'state' => AiSupportRequestDraft::STATE_PUBLISHED,
            'published_care_request_id' => $careRequest->id,
            'published_at' => now(),
        ])->save();

        FunnelTracker::track('care_request_published', $actor, $careRequest, [
            'request_type' => $careRequest->request_type,
            'tasks_count' => $tasks->count(),
            'origin' => 'ai_support',
        ]);

        return [
            'outcome_code' => 'care_request_live',
            'domain_reference_type' => 'care_request',
            'domain_reference_id' => (string) $careRequest->id,
            'receipt_reference' => 'care-request-'.$careRequest->id,
        ];
    }

    /** @param list<array<string,mixed>> $slots @return list<array{day:int,start_time:string,end_time:string}> */
    private function recurringSchedule(array $slots): array
    {
        return collect($slots)->map(function (array $slot): array {
            $start = CarbonImmutable::createFromFormat('H:i', $slot['start_time'], 'America/New_York');

            return [
                'day' => (int) $slot['day'],
                'start_time' => $start->format('H:i'),
                'end_time' => $start->addMinutes((int) $slot['duration_minutes'])->format('H:i'),
            ];
        })->sortBy('day')->values()->all();
    }

    /** @param list<string> $taskNames */
    private function title(array $taskNames, string $recipient): string
    {
        return str(implode(', ', array_slice($taskNames, 0, 2)).' for '.$recipient)->limit(140, '')->value();
    }

    /**
     * @param  \Illuminate\Support\Collection<int,CareTask>  $tasks
     * @param  array<string,string|null>  $notes
     */
    private function scope($tasks, array $notes): string
    {
        return $tasks->map(function (CareTask $task) use ($notes): string {
            $note = trim((string) ($notes[(string) $task->id] ?? ''));

            return '- '.$task->name.($note !== '' ? ': '.$note : '');
        })->implode("\n");
    }

    /** @param array<string,mixed> $payload */
    private function materialHash(array $payload): string
    {
        unset($payload['_provenance']);
        $sort = function (mixed $value) use (&$sort): mixed {
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($sort, $value);
        };

        return hash('sha256', json_encode($sort($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
