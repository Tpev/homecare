<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportActionPreview;
use App\Models\AiSupportMessageAction;
use App\Models\AiSupportRequestDraft;
use App\Models\CareRecipientProfile;
use App\Models\CareRequest;
use App\Models\CareTask;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AiSupportRequestDraftService
{
    private const PATCHABLE_FIELDS = [
        'recipient_is_requester', 'recipient_profile_id', 'recipient_full_name',
        'recipient_relationship', 'task_ids', 'task_notes', 'requested_start_date',
        'requested_start_time', 'duration_minutes', 'recurring_days',
        'recurring_schedule', 'recurring_starts_on', 'recurring_ends_on',
        'address_line1', 'address_line2', 'city', 'state', 'zip',
        'additional_info', 'home_access_notes', 'preferred_response_hours',
    ];

    public function __construct(
        private readonly FamilyAccountContext $families,
        private readonly AiSupportEligibilityService $eligibility,
        private readonly AiSupportFamilyContextService $context,
        private readonly AiSupportEventRecorder $events,
    ) {}

    public function start(User $actor, SupportTicket $ticket, string $requestType): AiSupportRequestDraft
    {
        $this->authorize($actor, $ticket);
        if (! in_array($requestType, [CareRequest::TYPE_ONE_TIME, CareRequest::TYPE_RECURRING], true)) {
            throw ValidationException::withMessages(['path' => 'Choose one-time care or regular care.']);
        }

        $account = $this->families->account($actor);
        $authorized = $this->context->read($actor, $ticket);

        return DB::transaction(function () use ($actor, $ticket, $requestType, $account, $authorized): AiSupportRequestDraft {
            $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->authorize($actor, $lockedTicket);
            $existing = AiSupportRequestDraft::query()->lockForUpdate()
                ->where('support_ticket_id', $lockedTicket->id)
                ->where('actor_user_id', $actor->id)
                ->first();

            if ($existing && $existing->isUsable() && $existing->request_type !== $requestType) {
                throw ValidationException::withMessages([
                    'draft' => 'A different saved request is already in this conversation. Resume it or discard it first.',
                ]);
            }

            $payload = $existing?->isUsable() ? (array) $existing->payload : [];
            $payload['request_type'] = $requestType;
            $payload['_provenance']['request_type'] = 'explicit_user_selection';

            if (! $existing?->isUsable()) {
                $profile = count($authorized['recipient_profiles']) === 1
                    ? $authorized['recipient_profiles'][0]
                    : null;
                if ($profile) {
                    $payload['recipient_profile_id'] = $profile['id'];
                    $payload['recipient_profile_version_id'] = $profile['version_id'];
                    $payload['recipient_full_name'] = $profile['name'];
                    $payload['recipient_is_requester'] = $profile['recipient_is_requester'];
                    $payload['recipient_relationship'] = filled($profile['relationship'] ?? null)
                        ? $profile['relationship']
                        : ((bool) $profile['recipient_is_requester'] ? 'Self' : 'Family member');
                    foreach (['recipient_profile_id', 'recipient_full_name', 'recipient_is_requester', 'recipient_relationship'] as $field) {
                        $payload['_provenance'][$field] = 'authorized_profile_proposal';
                    }
                }

                $household = (array) ($authorized['household'] ?? []);
                foreach (['address_line1', 'address_line2', 'city', 'state', 'zip', 'home_access_notes'] as $field) {
                    if (filled($household[$field] ?? null)) {
                        $payload[$field] = $household[$field];
                        $payload['_provenance'][$field] = 'authorized_household_proposal';
                    }
                }
                $payload['preferred_response_hours'] = (int) ($household['preferred_response_hours'] ?? 12);
                $payload['_provenance']['preferred_response_hours'] = isset($household['preferred_response_hours'])
                    ? 'authorized_household_proposal'
                    : 'deterministic_default';
            }

            $now = now();
            $values = [
                'actor_user_id' => $actor->id,
                'family_account_id' => $account->id,
                'request_type' => $requestType,
                'state' => AiSupportRequestDraft::STATE_COLLECTING,
                'payload' => $payload,
                'material_hash' => $this->materialHash($payload),
                'version' => $existing ? $existing->version + 1 : 1,
                'expires_at' => $now->copy()->addDays((int) config('ai_support.draft_retention_days', 7)),
                'discarded_at' => null,
                'published_care_request_id' => null,
                'published_at' => null,
                'last_error_code' => null,
            ];

            if ($existing) {
                $existing->forceFill($values)->save();
                $draft = $existing->fresh();
            } else {
                $draft = AiSupportRequestDraft::query()->create([
                    'id' => (string) Str::uuid(),
                    'support_ticket_id' => $lockedTicket->id,
                    ...$values,
                ]);
            }

            $this->invalidateConfirmations($lockedTicket, 'draft_started_or_changed');
            $this->events->record($lockedTicket, 'care_path_selected', [
                'capability_id' => 'care_intake_v1',
                'result_code' => $requestType,
            ], $actor);
            $this->events->record($lockedTicket, 'request_draft_started', [
                'capability_id' => 'care_request_draft_v1',
                'result_code' => $requestType,
            ], $actor);

            return $draft;
        }, 3);
    }

    public function startFromRequest(
        User $actor,
        SupportTicket $ticket,
        CareRequest $source,
        string $copyMode = 'duplicate',
        ?string $replacementType = null,
    ): AiSupportRequestDraft {
        $this->authorize($actor, $ticket);
        $account = $this->families->account($actor);
        $source = CareRequest::query()->forFamilyAccount($account)
            ->with(['recipient', 'tasks'])
            ->findOrFail($source->id);
        if (! in_array($copyMode, ['duplicate', 'reuse', 'expired_copy', 'withdrawn_copy', 'replacement'], true)) {
            throw ValidationException::withMessages(['draft' => 'This request copy type is not supported.']);
        }
        $requestType = $replacementType ?: $source->request_type;
        if (! in_array($requestType, [CareRequest::TYPE_ONE_TIME, CareRequest::TYPE_RECURRING], true)) {
            throw ValidationException::withMessages(['draft' => 'Choose one-time care or regular care for the new request.']);
        }

        $draft = $this->start($actor, $ticket, $requestType);
        $patch = [
            'patch_fields' => [
                'recipient_is_requester', 'recipient_profile_id', 'recipient_full_name',
                'recipient_relationship', 'task_ids', 'task_notes', 'address_line1',
                'address_line2', 'city', 'state', 'zip', 'additional_info',
                'home_access_notes', 'preferred_response_hours',
            ],
            'recipient_is_requester' => (bool) ($source->recipient?->recipient_is_requester ?? false),
            'recipient_profile_id' => $source->recipient?->care_recipient_profile_id,
            'recipient_full_name' => (string) ($source->recipient?->full_name ?? ''),
            'recipient_relationship' => (string) ($source->recipient?->relationship_to_family ?? ''),
            'task_ids' => $source->tasks->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'task_notes' => $source->tasks->map(fn ($task): array => [
                'task_id' => (int) $task->id,
                'note' => (string) ($task->pivot?->task_note ?? ''),
            ])->values()->all(),
            'address_line1' => (string) $source->address_line1,
            'address_line2' => (string) $source->address_line2,
            'city' => (string) $source->city,
            'state' => (string) $source->state,
            'zip' => (string) $source->zip,
            'additional_info' => (string) $source->additional_info,
            'home_access_notes' => (string) $source->home_access_notes,
            'preferred_response_hours' => (int) ($source->preferred_response_hours ?: 12),
        ];

        if ($requestType === CareRequest::TYPE_RECURRING && $source->request_type === CareRequest::TYPE_RECURRING) {
            $schedule = collect($source->recurringScheduleSlots())->map(function (array $slot): array {
                $start = CarbonImmutable::createFromFormat('!H:i', (string) $slot['start_time'], 'America/New_York');
                $end = CarbonImmutable::createFromFormat('!H:i', (string) $slot['end_time'], 'America/New_York');
                if ($end->lessThanOrEqualTo($start)) {
                    $end = $end->addDay();
                }

                return [
                    'day' => (int) $slot['day'],
                    'start_time' => (string) $slot['start_time'],
                    'duration_minutes' => (int) $start->diffInMinutes($end),
                ];
            })->values()->all();
            $patch['patch_fields'][] = 'recurring_days';
            $patch['patch_fields'][] = 'recurring_schedule';
            $patch['recurring_days'] = collect($schedule)->pluck('day')->unique()->values()->all();
            $patch['recurring_schedule'] = $schedule;
        }

        $draft = $this->applyPatch($actor, $ticket, $patch, $draft->version);
        $payload = (array) $draft->payload;
        $payload['_source_request'] = [
            'id' => (int) $source->id,
            'mode' => $copyMode,
            'original_status' => (string) $source->status,
            'original_remains_unchanged' => true,
        ];
        $payload['_provenance']['source_request'] = 'authorized_explicit_copy';
        $draft->forceFill([
            'payload' => $payload,
            'material_hash' => $this->materialHash($payload),
            'version' => $draft->version + 1,
            'state' => AiSupportRequestDraft::STATE_COLLECTING,
            'ready_at' => null,
            'last_error_code' => $requestType === CareRequest::TYPE_ONE_TIME
                ? 'missing_requested_start_date'
                : 'missing_recurring_starts_on',
        ])->save();
        $this->invalidateConfirmations($ticket, 'request_copy_started');
        $this->events->record($ticket, 'request_copy_started', [
            'capability_id' => 'care_request_draft_v1',
            'result_code' => $copyMode,
            'safe_metadata' => ['source_request_id' => (string) $source->id],
        ], $actor);

        return $draft->fresh();
    }

    /** @param array<string,mixed> $patch */
    public function applyPatch(
        User $actor,
        SupportTicket $ticket,
        array $patch,
        ?int $expectedVersion = null,
    ): AiSupportRequestDraft {
        $this->authorize($actor, $ticket);

        return DB::transaction(function () use ($actor, $ticket, $patch, $expectedVersion): AiSupportRequestDraft {
            $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->authorize($actor, $lockedTicket);
            $draft = AiSupportRequestDraft::query()->lockForUpdate()
                ->where('support_ticket_id', $lockedTicket->id)
                ->where('actor_user_id', $actor->id)
                ->firstOrFail();
            $this->authorizeDraft($actor, $lockedTicket, $draft);
            if ($expectedVersion !== null && $draft->version !== $expectedVersion) {
                throw ValidationException::withMessages([
                    'draft' => 'This saved request changed in another tab. Reload it before continuing.',
                ]);
            }

            $fields = array_values(array_unique(array_map('strval', (array) ($patch['patch_fields'] ?? []))));
            if ($fields === [] || array_diff($fields, self::PATCHABLE_FIELDS) !== []) {
                throw ValidationException::withMessages(['draft' => 'No supported request detail was supplied.']);
            }

            $payload = (array) $draft->payload;
            foreach ($fields as $field) {
                $payload[$field] = $this->normalizeField($actor, $draft, $field, $patch[$field] ?? null);
                $payload['_provenance'][$field] = 'model_extraction_from_user_message';
            }

            if (in_array('recipient_profile_id', $fields, true) && $payload['recipient_profile_id']) {
                $profile = CareRecipientProfile::query()
                    ->where('family_account_id', $draft->family_account_id)
                    ->where('status', CareRecipientProfile::STATUS_READY)
                    ->whereNull('archived_at')
                    ->find($payload['recipient_profile_id']);
                if (! $profile) {
                    throw new AuthorizationException;
                }
                $payload['recipient_profile_version_id'] = $profile->latest_ready_version_id;
                $payload['recipient_full_name'] = $profile->displayName();
                $payload['recipient_is_requester'] = (bool) $profile->recipient_is_requester;
                $payload['recipient_relationship'] = filled($profile->relationship_to_family)
                    ? $profile->relationship_to_family
                    : ($profile->recipient_is_requester ? 'Self' : 'Family member');
            }

            if ($draft->request_type === CareRequest::TYPE_RECURRING
                && filled($payload['recurring_starts_on'] ?? null)
                && (array) ($payload['recurring_days'] ?? []) !== []) {
                try {
                    $original = $this->strictDate((string) $payload['recurring_starts_on']);
                    if (! $original) {
                        throw new \RuntimeException;
                    }
                    $candidate = $original;
                    $days = array_map('intval', (array) $payload['recurring_days']);
                    while (! in_array($candidate->dayOfWeek, $days, true)) {
                        $candidate = $candidate->addDay();
                    }
                    if (! $candidate->equalTo($original)) {
                        $payload['_recurring_start_adjusted_from'] = $original->toDateString();
                        $payload['recurring_starts_on'] = $candidate->toDateString();
                        $payload['_provenance']['recurring_starts_on'] = 'deterministic_weekday_alignment';
                    }
                } catch (\Throwable) {
                    // The validator below returns the user-facing date correction.
                }
            }

            $validation = $this->validatePayload($actor, $draft, $payload);
            $ready = $validation['errors'] === [];
            $draft->forceFill([
                'payload' => $payload,
                'material_hash' => $this->materialHash($payload),
                'version' => $draft->version + 1,
                'state' => $ready
                    ? AiSupportRequestDraft::STATE_READY_FOR_RECAP
                    : AiSupportRequestDraft::STATE_COLLECTING,
                'ready_at' => $ready ? now() : null,
                'expires_at' => now()->addDays((int) config('ai_support.draft_retention_days', 7)),
                'last_error_code' => $validation['errors'][0]['code'] ?? null,
            ])->save();

            $this->invalidateConfirmations($lockedTicket, 'draft_material_changed');
            $this->events->record($lockedTicket, 'request_draft_updated', [
                'capability_id' => 'care_request_draft_v1',
                'result_code' => $ready ? 'ready_for_recap' : 'collecting',
            ], $actor);

            return $draft->fresh();
        }, 3);
    }

    public function discard(User $actor, SupportTicket $ticket): void
    {
        $this->authorize($actor, $ticket);
        DB::transaction(function () use ($actor, $ticket): void {
            $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->authorize($actor, $lockedTicket);
            $draft = AiSupportRequestDraft::query()->lockForUpdate()
                ->where('support_ticket_id', $lockedTicket->id)
                ->where('actor_user_id', $actor->id)
                ->first();
            if (! $draft) {
                return;
            }
            $this->authorizeDraft($actor, $lockedTicket, $draft);
            $draft->forceFill([
                'payload' => null,
                'state' => AiSupportRequestDraft::STATE_DISCARDED,
                'discarded_at' => now(),
                'expires_at' => now(),
                'material_hash' => null,
                'version' => $draft->version + 1,
            ])->save();
            $this->invalidateConfirmations($lockedTicket, 'draft_discarded');
            $this->events->record($lockedTicket, 'request_draft_discarded', [
                'capability_id' => 'care_request_draft_v1',
                'result_code' => 'discarded',
            ], $actor);
        }, 3);
    }

    /** @return array{errors:list<array{field:string,code:string,message:string}>} */
    public function validatePayload(User $actor, AiSupportRequestDraft $draft, ?array $payload = null): array
    {
        $this->authorizeDraft($actor, $draft->ticket()->firstOrFail(), $draft);
        $payload ??= (array) $draft->payload;
        $errors = [];
        $required = function (string $field, string $message) use (&$errors, $payload): void {
            $value = $payload[$field] ?? null;
            if ($value === null || $value === '' || $value === []) {
                $errors[] = ['field' => $field, 'code' => 'missing_'.$field, 'message' => $message];
            }
        };

        $required('recipient_full_name', 'Who needs care—you, or someone else?');
        $required('task_ids', 'What kind of help is needed?');
        $required('address_line1', 'What is the street address where care is needed?');
        $required('city', 'What city is the care address in?');
        $required('state', 'What state is the care address in?');
        $required('zip', 'What is the ZIP code for the care address?');

        $taskIds = array_values(array_unique(array_map('intval', (array) ($payload['task_ids'] ?? []))));
        if ($taskIds !== [] && CareTask::query()->whereKey($taskIds)->count() !== count($taskIds)) {
            $errors[] = ['field' => 'task_ids', 'code' => 'invalid_task_ids', 'message' => 'Please choose one of the available kinds of help.'];
        }

        if ($draft->request_type === CareRequest::TYPE_ONE_TIME) {
            $required('requested_start_date', 'What date is the visit?');
            $required('requested_start_time', 'What time should care start?');
            $required('duration_minutes', 'How long should the visit last?');
            if (filled($payload['requested_start_date'] ?? null) && filled($payload['requested_start_time'] ?? null)) {
                $start = $this->strictDateTime(
                    (string) $payload['requested_start_date'],
                    (string) $payload['requested_start_time'],
                );
                if (! $start || ! $start->isFuture()) {
                    $errors[] = ['field' => 'requested_start_date', 'code' => 'start_not_future', 'message' => 'Choose a date and time in the future.'];
                }
            }
        } else {
            $required('recurring_days', 'Which days of the week is care needed?');
            $required('recurring_schedule', 'What start time and duration should we use for each selected day?');
            $required('recurring_starts_on', 'What date should regular care begin?');
            $days = array_values(array_unique(array_map('intval', (array) ($payload['recurring_days'] ?? []))));
            $schedule = collect((array) ($payload['recurring_schedule'] ?? []));
            $scheduleDays = $schedule->pluck('day')->map(fn ($day): int => (int) $day)->unique()->sort()->values()->all();
            $sortedDays = $days;
            sort($sortedDays);
            if ($days !== [] && ($scheduleDays !== $sortedDays
                || $schedule->count() !== count($scheduleDays)
                || collect($days)->contains(fn (int $day): bool => $day < 0 || $day > 6))) {
                $errors[] = ['field' => 'recurring_schedule', 'code' => 'incomplete_recurring_schedule', 'message' => 'Please give a start time and duration for every selected day.'];
            }
            $startsOn = filled($payload['recurring_starts_on'] ?? null)
                ? $this->strictDate((string) $payload['recurring_starts_on'])
                : null;
            if (filled($payload['recurring_starts_on'] ?? null)
                && (! $startsOn || $startsOn->isBefore(now('America/New_York')->startOfDay()))) {
                $errors[] = ['field' => 'recurring_starts_on', 'code' => 'recurring_start_invalid', 'message' => 'Choose a regular-care start date that is not in the past.'];
            }
            if (filled($payload['recurring_ends_on'] ?? null)) {
                $endsOn = $this->strictDate((string) $payload['recurring_ends_on']);
                if (! $endsOn) {
                    $errors[] = ['field' => 'recurring_ends_on', 'code' => 'recurring_end_invalid', 'message' => 'Choose a valid regular-care end date.'];
                } elseif ($startsOn && $endsOn->isBefore($startsOn)) {
                    $errors[] = ['field' => 'recurring_ends_on', 'code' => 'end_before_start', 'message' => 'The regular-care end date must be on or after the start date.'];
                }
            }
        }

        $durationValues = $draft->request_type === CareRequest::TYPE_ONE_TIME
            ? [(int) ($payload['duration_minutes'] ?? 0)]
            : collect((array) ($payload['recurring_schedule'] ?? []))->pluck('duration_minutes')->map(fn ($value): int => (int) $value)->all();
        foreach ($durationValues as $duration) {
            if ($duration < 60 || $duration > 720 || $duration % 30 !== 0) {
                $errors[] = ['field' => 'duration_minutes', 'code' => 'invalid_duration', 'message' => 'Choose a duration from 1 to 12 hours in 30-minute steps.'];
                break;
            }
        }

        foreach ((array) ($payload['recurring_schedule'] ?? []) as $slot) {
            if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) ($slot['start_time'] ?? '')) !== 1) {
                $errors[] = ['field' => 'recurring_schedule', 'code' => 'invalid_recurring_time', 'message' => 'Use a clear start time for every selected day.'];
                break;
            }
        }

        if (filled($payload['state'] ?? null) && preg_match('/^[A-Z]{2}$/', (string) $payload['state']) !== 1) {
            $errors[] = ['field' => 'state', 'code' => 'invalid_state', 'message' => 'Use the two-letter state abbreviation.'];
        }
        if (filled($payload['zip'] ?? null) && preg_match('/^\d{5}(?:-\d{4})?$/', (string) $payload['zip']) !== 1) {
            $errors[] = ['field' => 'zip', 'code' => 'invalid_zip', 'message' => 'Use a valid 5-digit ZIP code.'];
        }
        $responseHours = (int) ($payload['preferred_response_hours'] ?? 12);
        if ($responseHours < 1 || $responseHours > 72) {
            $errors[] = ['field' => 'preferred_response_hours', 'code' => 'invalid_response_hours', 'message' => 'Choose a caregiver response time from 1 to 72 hours.'];
        }

        return ['errors' => $errors];
    }

    public function nextQuestion(User $actor, AiSupportRequestDraft $draft): ?string
    {
        return $this->validatePayload($actor, $draft)['errors'][0]['message'] ?? null;
    }

    /** @return array<string,mixed> */
    public function recap(User $actor, AiSupportRequestDraft $draft): array
    {
        $validation = $this->validatePayload($actor, $draft);
        if ($validation['errors'] !== []) {
            throw ValidationException::withMessages(['draft' => $validation['errors'][0]['message']]);
        }
        $payload = (array) $draft->payload;
        $tasks = CareTask::query()->whereKey((array) $payload['task_ids'])->orderBy('name')->pluck('name')->all();
        $schedule = $draft->request_type === CareRequest::TYPE_ONE_TIME
            ? CarbonImmutable::parse($payload['requested_start_date'].' '.$payload['requested_start_time'], 'America/New_York')->format('l, F j, Y \a\t g:i A')
                .' for '.$this->durationLabel((int) $payload['duration_minutes']).' Eastern Time'
            : $this->recurringLabel($payload);

        return [
            'draft_id' => $draft->id,
            'draft_version' => $draft->version,
            'request_type' => $draft->request_type,
            'request_type_label' => $draft->request_type === CareRequest::TYPE_ONE_TIME ? 'One-time care' : 'Regular care',
            'recipient' => $payload['recipient_full_name'],
            'tasks' => $tasks,
            'schedule' => $schedule,
            'address' => implode(', ', array_filter([
                $payload['address_line1'], $payload['address_line2'] ?? null,
                ($payload['city'] ?? '').', '.($payload['state'] ?? '').' '.($payload['zip'] ?? ''),
            ])),
            'additional_info' => $payload['additional_info'] ?? null,
            'home_access_notes' => $payload['home_access_notes'] ?? null,
            'preferred_response_hours' => (int) ($payload['preferred_response_hours'] ?? 12),
            'provenance' => (array) ($payload['_provenance'] ?? []),
            'schedule_adjustment' => filled($payload['_recurring_start_adjusted_from'] ?? null)
                ? 'The start date moved from '.$payload['_recurring_start_adjusted_from'].' to '.$payload['recurring_starts_on'].' so it matches a selected weekday.'
                : null,
            'source_disclosure' => filled(data_get($payload, '_source_request.id'))
                ? 'This is a new request copied from request #'.data_get($payload, '_source_request.id').'. The original stays unchanged. You must review a fresh schedule before publishing.'
                : null,
            'disclosure' => 'The request will become live and eligible caregivers can see it. No caregiver will be hired, and no payment will be authorized.',
        ];
    }

    private function authorize(User $actor, SupportTicket $ticket): void
    {
        if ($actor->role !== 'family'
            || ! $this->eligibility->evaluate($actor, 'care_request_draft_v1', $ticket)->allowed) {
            throw new AuthorizationException;
        }
        $account = $this->families->account($actor);
        if ((int) $ticket->family_account_id !== (int) $account->id) {
            throw new AuthorizationException;
        }
    }

    private function authorizeDraft(User $actor, SupportTicket $ticket, AiSupportRequestDraft $draft): void
    {
        $account = $this->families->account($actor);
        if ((int) $draft->actor_user_id !== (int) $actor->id
            || (int) $draft->family_account_id !== (int) $account->id
            || (int) $draft->support_ticket_id !== (int) $ticket->id
            || ! $draft->isUsable()) {
            throw new AuthorizationException;
        }
    }

    private function normalizeField(User $actor, AiSupportRequestDraft $draft, string $field, mixed $value): mixed
    {
        return match ($field) {
            'recipient_is_requester' => $value === null ? null : (bool) $value,
            'recipient_profile_id', 'duration_minutes', 'preferred_response_hours' => $value === null ? null : (int) $value,
            'task_ids', 'recurring_days' => array_values(array_unique(array_map('intval', (array) $value))),
            'task_notes' => collect((array) $value)->mapWithKeys(fn ($item): array => [
                (string) ((int) ($item['task_id'] ?? 0)) => Str::limit(trim((string) ($item['note'] ?? '')), 500, ''),
            ])->filter(fn ($note, $id): bool => (int) $id > 0)->all(),
            'recurring_schedule' => collect((array) $value)->map(fn ($slot): array => [
                'day' => (int) ($slot['day'] ?? -1),
                'start_time' => substr(trim((string) ($slot['start_time'] ?? '')), 0, 5),
                'duration_minutes' => (int) ($slot['duration_minutes'] ?? 0),
            ])->values()->all(),
            'state' => strtoupper(trim((string) $value)),
            'requested_start_time' => substr(trim((string) $value), 0, 5),
            default => $value === null ? null : trim(Str::limit((string) $value, in_array($field, ['additional_info', 'home_access_notes'], true) ? 3000 : 255, '')),
        };
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

    private function invalidateConfirmations(SupportTicket $ticket, string $reason): void
    {
        $now = now();
        AiSupportActionPreview::query()->where('support_ticket_id', $ticket->id)
            ->whereNull('content_deleted_at')->update([
                'preview_payload' => null,
                'invalidated_at' => $now,
                'invalidation_reason' => $reason,
                'content_deleted_at' => $now,
            ]);
        AiSupportMessageAction::query()->where('support_ticket_id', $ticket->id)
            ->where('action_type', AiSupportMessageAction::TYPE_RECAP)
            ->whereNull('invalidated_at')->update([
                'payload' => null,
                'invalidated_at' => $now,
                'invalidation_reason' => $reason,
            ]);
    }

    private function durationLabel(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return trim(($hours ? $hours.' '.Str::plural('hour', $hours) : '').($remaining ? ' '.$remaining.' minutes' : ''));
    }

    private function strictDate(string $date): ?CarbonImmutable
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'America/New_York');

            return $parsed && $parsed->format('Y-m-d') === $date ? $parsed : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function strictDateTime(string $date, string $time): ?CarbonImmutable
    {
        $value = $date.' '.$time;

        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d H:i', $value, 'America/New_York');

            return $parsed && $parsed->format('Y-m-d H:i') === $value ? $parsed : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string,mixed> $payload */
    private function recurringLabel(array $payload): string
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $rows = collect((array) $payload['recurring_schedule'])->map(function (array $slot) use ($days): string {
            $time = CarbonImmutable::createFromFormat('H:i', $slot['start_time'], 'America/New_York')->format('g:i A');

            return $days[(int) $slot['day']].' at '.$time.' for '.$this->durationLabel((int) $slot['duration_minutes']);
        })->implode('; ');
        $end = filled($payload['recurring_ends_on'] ?? null) ? ' through '.$payload['recurring_ends_on'] : ', ongoing';

        return $rows.', starting '.$payload['recurring_starts_on'].$end.' Eastern Time';
    }
}
