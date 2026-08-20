<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportConfirmedActionEvidence;
use App\Models\AiSupportGuidedTask;
use App\Models\AiSupportMessageAction;
use App\Models\AiSupportPreparation;
use App\Models\CareRecipientProfile;
use App\Models\CareRequest;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\CareRecipientProfiles\CareRecipientProfileService;
use App\Services\CareRequests\CareRequestLifecycleService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FamilyLifecycleActionService
{
    public const PROFILE_CONTRACT = 'care_profile_chat_v1';

    private const PROFILE_DRAFT_INTENTS = [
        'FAM-PROFILE-003', 'FAM-PROFILE-004', 'FAM-PROFILE-005', 'FAM-PROFILE-007',
        'FAM-PROFILE-008', 'FAM-PROFILE-009', 'FAM-PROFILE-010', 'FAM-PROFILE-011',
        'FAM-PROFILE-012', 'FAM-PROFILE-013', 'FAM-PROFILE-014', 'FAM-PROFILE-018',
        'FAM-REQUEST-006',
    ];

    public function __construct(
        private readonly FamilyAccountContext $familyAccounts,
        private readonly FamilyProfileRequestStateReader $state,
        private readonly CareRecipientProfileService $profiles,
        private readonly CareRequestLifecycleService $requests,
        private readonly AiSupportRequestDraftService $requestDrafts,
        private readonly AiSupportActionEvidenceService $evidence,
        private readonly AiSupportEligibilityService $eligibility,
        private readonly AiSupportEventRecorder $events,
        private readonly AiSupportGuidedTaskService $guidedTasks,
        private readonly FamilyAdministrationActionService $administration,
        private readonly FamilyCareOperationsActionService $careOperations,
    ) {}

    /** @param array<string,mixed> $record */
    public function respond(User $actor, SupportTicket $ticket, array $record, string $message): bool
    {
        $intentId = (string) $record['intent_id'];

        if ($this->administration->respond($actor, $ticket, $record, $message)) {
            return true;
        }

        if ($this->careOperations->respond($actor, $ticket, $record, $message)) {
            return true;
        }

        if (in_array($intentId, self::PROFILE_DRAFT_INTENTS, true)) {
            if (! $this->available($actor, $ticket)) {
                return false;
            }
            $makeReady = $intentId === 'FAM-PROFILE-005';
            $create = in_array($intentId, ['FAM-PROFILE-003', 'FAM-REQUEST-006'], true);
            $activePreparation = $this->activeProfileDraft($actor, $ticket);
            $profile = $activePreparation?->resource_id
                ? $this->ownedProfile($actor, (int) $activePreparation->resource_id, true)
                : ($activePreparation ? null : ($create ? null : $this->selectProfile($actor, $message, false)));
            if (! $create && ! $activePreparation && ! $profile) {
                $this->automatedMessage($ticket, 'I could not safely identify one editable care receiver profile. Please say its name, or open Care receiver profiles.');

                return true;
            }
            $patch = $this->profilePatchFromMessage($intentId, $message);
            if (! $makeReady && $patch === []) {
                $this->startOrUpdateProfileDraft($actor, $ticket, $profile, [], $intentId, false);
                $this->automatedMessage($ticket, ($create || ($activePreparation && ! $profile))
                    ? 'What name should caregivers use for this care receiver?'
                    : 'What would you like to change in '.$profile->displayName().'’s profile?');

                return true;
            }
            $preparation = $this->startOrUpdateProfileDraft($actor, $ticket, $profile, $patch, $intentId, $makeReady);
            $this->issueProfileRecap($actor, $ticket, $preparation);

            return true;
        }

        if (in_array($intentId, ['FAM-PROFILE-019', 'FAM-PROFILE-020', 'FAM-PROFILE-021'], true)) {
            if (! $this->available($actor, $ticket)) {
                return false;
            }
            $profile = $this->selectProfile($actor, $message, $intentId === 'FAM-PROFILE-021');
            if (! $profile) {
                $this->automatedMessage($ticket, 'I could not safely identify one care receiver profile. Please say its name.');

                return true;
            }
            $tool = match ($intentId) {
                'FAM-PROFILE-019' => 'family-profile.make-default',
                'FAM-PROFILE-020' => 'family-profile.archive',
                default => 'family-profile.restore',
            };
            $this->issueResourceRecap($actor, $ticket, $tool, $profile, $intentId);

            return true;
        }

        if ($intentId === 'FAM-REQUEST-038') {
            if (! $this->available($actor, $ticket)) {
                return false;
            }
            $request = $this->selectRequest($actor, $message, [CareRequest::STATUS_DRAFT, CareRequest::STATUS_OPEN]);
            if (! $request) {
                $this->automatedMessage($ticket, 'I could not find one draft or open request that can be withdrawn. Nothing was changed.');

                return true;
            }
            $this->issueResourceRecap($actor, $ticket, 'care-request.withdraw', $request, $intentId);

            return true;
        }

        if (in_array($intentId, ['FAM-REQUEST-020', 'FAM-REQUEST-036', 'FAM-REQUEST-037', 'FAM-REQUEST-039', 'FAM-REQUEST-040', 'FAM-HISTORY-013'], true)) {
            if (! $this->available($actor, $ticket)) {
                return false;
            }
            $request = $this->selectRequest($actor, $message);
            if (! $request) {
                $this->automatedMessage($ticket, 'I could not find an authorized earlier request to copy. Nothing was changed.');

                return true;
            }
            if ($intentId === 'FAM-REQUEST-039' && ! in_array($request->status, [CareRequest::STATUS_CANCELLED, CareRequest::STATUS_EXPIRED], true)) {
                $this->automatedMessage($ticket, 'That request is not withdrawn or expired. I did not reopen or copy it.');

                return true;
            }
            $mode = match ($intentId) {
                'FAM-REQUEST-020', 'FAM-HISTORY-013' => 'reuse',
                'FAM-REQUEST-036', 'FAM-REQUEST-037' => 'replacement',
                'FAM-REQUEST-039' => $request->status === CareRequest::STATUS_EXPIRED ? 'expired_copy' : 'withdrawn_copy',
                default => 'duplicate',
            };
            $replacementType = $intentId === 'FAM-REQUEST-037'
                ? ($request->request_type === CareRequest::TYPE_ONE_TIME ? CareRequest::TYPE_RECURRING : CareRequest::TYPE_ONE_TIME)
                : null;
            try {
                $draft = $this->requestDrafts->startFromRequest($actor, $ticket, $request, $mode, $replacementType);
                $question = $this->requestDrafts->nextQuestion($actor, $draft);
                $this->automatedMessage(
                    $ticket,
                    'I started a new private copy of request #'.$request->id.'. The original remains unchanged. '.($question ?: 'Please review the new request.'),
                );
            } catch (ValidationException $exception) {
                $this->automatedMessage($ticket, (string) collect($exception->errors())->flatten()->first());
            }

            return true;
        }

        if (in_array($intentId, ['FAM-PROFILE-002', 'FAM-PROFILE-015', 'FAM-PROFILE-016'], true)) {
            $state = $this->state->read($actor);
            $active = collect($state['profiles'])->where('archived', false);
            $archived = collect($state['profiles'])->where('archived', true);
            $body = $active->isEmpty()
                ? 'You do not have an active care receiver profile yet.'
                : 'You have '.$active->count().' active care receiver '.Str::plural('profile', $active->count()).'. '.
                    $active->map(fn (array $row): string => $row['name'].' is '.($row['ready'] ? 'ready' : 'a draft'))->implode('; ').'.';
            if ($archived->isNotEmpty()) {
                $body .= ' You also have '.$archived->count().' archived.';
            }
            $body .= $intentId === 'FAM-PROFILE-015'
                ? ' Candidate caregivers see only the approved candidate-sharing profile.'
                : ($intentId === 'FAM-PROFILE-016' ? ' A hired caregiver may see the assigned-care version.' : '');
            $this->guidedTasks->offerFamilyReadResult(
                $actor,
                $ticket,
                $body,
                $intentId,
                'profile_state_read',
                [[
                    'task_type' => AiSupportGuidedTask::TYPE_FAMILY_CARE_PROFILE,
                    'target_id' => 'family.care_profiles',
                    'label' => 'Open care receiver profiles',
                    'verifier_id' => 'authoritative_family_state_v1',
                ]],
            );

            return true;
        }

        if (in_array($intentId, ['FAM-REQUEST-034', 'FAM-REQUEST-035'], true)) {
            $row = $this->state->request($actor, $this->idFromMessage($message));
            if (! $row) {
                $this->automatedMessage($ticket, 'I could not find an authorized care request to check.');

                return true;
            }
            $body = $row['title'].' (request #'.$row['id'].') is '.$row['status_label'].'.';
            if ($intentId === 'FAM-REQUEST-035') {
                $body .= $row['active_applications'] > 0
                    ? ' '.$row['active_applications'].' caregiver'.($row['active_applications'] === 1 ? ' is' : 's are').' waiting for your review.'
                    : ' There are no active caregiver responses waiting for your review.';
                $body .= ' LoLo does not track or claim caregiver views.';
            }
            if ($row['blocker']) {
                $body .= ' '.$row['blocker'];
            }
            $this->guidedTasks->offerFamilyReadResult(
                $actor,
                $ticket,
                $body,
                $intentId,
                'request_state_read',
                [[
                    'task_type' => AiSupportGuidedTask::TYPE_FAMILY_REQUEST,
                    'target_id' => $intentId === 'FAM-REQUEST-035' ? 'family.request.applicants' : 'family.request.overview',
                    'resource_type' => 'care_request',
                    'resource_id' => (int) $row['id'],
                    'label' => $intentId === 'FAM-REQUEST-035' ? 'Review caregivers' : 'Open this request',
                    'verifier_id' => 'authoritative_family_state_v1',
                ]],
            );

            return true;
        }

        return false;
    }

    /** @param array<string,mixed> $patch */
    public function applyProfilePatch(User $actor, SupportTicket $ticket, array $patch, ?int $expectedVersion = null): AiSupportMessageAction
    {
        $preparation = $this->activeProfileDraft($actor, $ticket);
        if (! $preparation) {
            throw ValidationException::withMessages(['profile' => 'Start a care receiver profile change first.']);
        }
        if ($expectedVersion !== null && (int) $preparation->version !== $expectedVersion) {
            throw ValidationException::withMessages(['profile' => 'This profile draft changed. Review the latest recap before continuing.']);
        }
        $fields = array_values(array_unique(array_map('strval', (array) ($patch['patch_fields'] ?? []))));
        if ($fields === [] || array_diff($fields, $this->profiles->writableFields()) !== []) {
            throw ValidationException::withMessages(['profile' => 'No supported profile detail was supplied.']);
        }
        $values = Arr::only($patch, $fields);
        if (in_array('support_details', $fields, true)) {
            $values['support_details'] = collect((array) ($values['support_details'] ?? []))
                ->mapWithKeys(fn ($row): array => [
                    (string) ($row['area'] ?? '') => (string) ($row['detail'] ?? ''),
                ])->filter(fn ($detail, $area): bool => $area !== '' && trim($detail) !== '')->all();
        }
        $profile = $preparation->resource_id
            ? $this->ownedProfile($actor, (int) $preparation->resource_id, true)
            : null;
        $this->profiles->mergedData($profile, array_replace((array) data_get($preparation->payload, 'fields', []), $values));

        $payload = (array) $preparation->payload;
        $payload['fields'] = array_replace((array) ($payload['fields'] ?? []), $values);
        $preparation->forceFill([
            'payload' => $payload,
            'fields_hash' => $this->hashFields((array) $payload['fields']),
            'version' => (int) $preparation->version + 1,
            'expires_at' => now()->addHours(24),
        ])->save();

        return $this->issueProfileRecap($actor, $ticket, $preparation->fresh());
    }

    /** @return array<string,mixed>|null */
    public function activeProfileContext(User $actor, SupportTicket $ticket): ?array
    {
        $preparation = $this->activeProfileDraft($actor, $ticket);
        if (! $preparation) {
            return null;
        }
        $profile = $preparation->resource_id
            ? $this->ownedProfile($actor, (int) $preparation->resource_id, true)
            : null;

        return [
            'preparation_id' => (string) $preparation->id,
            'version' => (int) $preparation->version,
            'profile_id' => $profile?->id,
            'profile_name' => $profile?->displayName(),
            'expected_revision' => data_get($preparation->payload, 'expected_revision'),
            'desired_state' => (string) data_get($preparation->payload, 'desired_state', 'draft'),
            'proposed_fields' => (array) data_get($preparation->payload, 'fields', []),
            'allowed_fields' => $this->profiles->writableFields(),
            'enum_values' => [
                'age_range' => array_keys(CareRecipientProfile::AGE_RANGES),
                'communication_preferences' => array_keys(CareRecipientProfile::COMMUNICATION_OPTIONS),
                'support_areas' => array_keys(CareRecipientProfile::SUPPORT_AREAS),
                'mobility_level' => array_keys(CareRecipientProfile::MOBILITY_LEVELS),
                'comfort_needs' => array_keys(CareRecipientProfile::COMFORT_NEEDS),
                'safety_items' => array_keys(CareRecipientProfile::SAFETY_ITEMS),
                'caregiver_quality_preferences' => array_keys(CareRecipientProfile::CAREGIVER_QUALITIES),
            ],
        ];
    }

    public function cancelActiveProfileDraft(User $actor, SupportTicket $ticket): bool
    {
        return DB::transaction(function () use ($actor, $ticket): bool {
            $preparation = AiSupportPreparation::query()
                ->lockForUpdate()
                ->where('support_ticket_id', $ticket->id)
                ->where('actor_user_id', $actor->id)
                ->where('contract_id', self::PROFILE_CONTRACT)
                ->whereIn('state', [AiSupportPreparation::STATE_READY, AiSupportPreparation::STATE_APPLIED])
                ->where('expires_at', '>', now())
                ->latest('updated_at')
                ->first();
            if (! $preparation) {
                return false;
            }

            $cancelledAt = now();
            $preparation->forceFill([
                'state' => AiSupportPreparation::STATE_CANCELLED,
                'cancelled_at' => $cancelledAt,
            ])->save();
            AiSupportMessageAction::query()
                ->lockForUpdate()
                ->where('support_ticket_id', $ticket->id)
                ->where('actor_user_id', $actor->id)
                ->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)
                ->whereNull('invalidated_at')
                ->get()
                ->filter(fn (AiSupportMessageAction $action): bool => (string) data_get($action->payload, 'preparation_id') === $preparation->id)
                ->each(fn (AiSupportMessageAction $action) => $action->forceFill([
                    'invalidated_at' => $cancelledAt,
                    'invalidation_reason' => 'preparation_cancelled',
                ])->save());

            return true;
        }, 3);
    }

    public function confirm(User $actor, SupportTicket $ticket, string $actionId): AiSupportConfirmedActionEvidence
    {
        $action = $this->domainAction($actor, $ticket, $actionId);
        $payload = (array) $action->payload;
        if ($this->administration->ownsTool((string) ($payload['tool_id'] ?? ''))) {
            return $this->administration->confirm($actor, $ticket, $actionId);
        }
        if ($this->careOperations->ownsTool((string) ($payload['tool_id'] ?? ''))) {
            return $this->careOperations->confirm($actor, $ticket, $actionId);
        }
        if ($action->consumed_at) {
            return AiSupportConfirmedActionEvidence::query()
                ->where('idempotency_key', (string) ($payload['idempotency_key'] ?? ''))
                ->where('actor_user_id', $actor->id)
                ->firstOrFail();
        }
        if (! $action->isActive()) {
            throw ValidationException::withMessages(['confirmation' => 'This confirmation expired or changed. Review a fresh recap and confirm again.']);
        }

        $tool = (string) $payload['tool_id'];
        $confirmed = $this->evidence->commitConfirmedAction(
            $actor,
            (string) $payload['confirmation_reference'],
            (string) $payload['idempotency_key'],
            (string) $payload['confirmation_action'],
            fn (array $preview): array => $this->commit($actor, $ticket, $tool, $preview),
        );

        DB::transaction(function () use ($actor, $ticket, $action, $payload, $confirmed): void {
            $locked = AiSupportMessageAction::query()->lockForUpdate()->findOrFail($action->id);
            if ($locked->consumed_at) {
                return;
            }
            $locked->forceFill([
                'payload' => [
                    'idempotency_key' => $confirmed->idempotency_key,
                    'tool_id' => $confirmed->tool_id,
                    'confirmed_action_evidence_id' => $confirmed->id,
                ],
                'consumed_at' => now(),
            ])->save();
            if (filled($payload['preparation_id'] ?? null)) {
                AiSupportPreparation::query()->whereKey($payload['preparation_id'])->update([
                    'state' => AiSupportPreparation::STATE_CANCELLED,
                    'cancelled_at' => now(),
                ]);
            }
            $message = $this->automatedMessage($ticket, $this->receiptText($confirmed));
            AiSupportMessageAction::query()->create([
                'id' => (string) Str::uuid(),
                'support_ticket_message_id' => $message->id,
                'support_ticket_id' => $ticket->id,
                'actor_user_id' => $actor->id,
                'action_type' => AiSupportMessageAction::TYPE_DOMAIN_RECEIPT,
                'payload' => [
                    'title' => 'Done and checked',
                    'receipt' => $confirmed->receipt_reference,
                    'url' => $this->receiptUrl($actor, $confirmed),
                    'label' => $confirmed->domain_reference_type === 'care_request' ? 'View request' : 'View care profiles',
                ],
            ]);
            $this->events->record($ticket, 'intent_completed', [
                'support_ticket_message_id' => $message->id,
                'capability_id' => 'family_lifecycle_action_v1',
                'tool_id' => $confirmed->tool_id,
                'tool_version' => $confirmed->tool_version,
                'result_code' => 'authoritative_receipt_created',
                'safe_metadata' => ['intent_id' => (string) ($payload['intent_id'] ?? '')],
            ], $actor);
        }, 3);

        return $confirmed;
    }

    public function renew(User $actor, SupportTicket $ticket, string $actionId): AiSupportMessageAction
    {
        $action = $this->domainAction($actor, $ticket, $actionId, false);
        if ($action->consumed_at) {
            throw ValidationException::withMessages(['confirmation' => 'This action was already completed.']);
        }
        $payload = (array) $action->payload;
        $tool = (string) ($payload['tool_id'] ?? '');
        if ($this->administration->ownsTool($tool)) {
            return $this->administration->renew($actor, $ticket, $actionId);
        }
        if ($this->careOperations->ownsTool($tool)) {
            return $this->careOperations->renew($actor, $ticket, $actionId);
        }
        if (str_starts_with($tool, 'family-profile.save-') || $tool === 'family-profile.make-ready') {
            $preparation = AiSupportPreparation::query()
                ->whereKey((string) ($payload['preparation_id'] ?? ''))
                ->where('support_ticket_id', $ticket->id)
                ->where('actor_user_id', $actor->id)
                ->whereIn('state', [AiSupportPreparation::STATE_READY, AiSupportPreparation::STATE_APPLIED])
                ->where('expires_at', '>', now())
                ->firstOrFail();

            return $this->issueProfileRecap($actor, $ticket, $preparation);
        }
        if (str_starts_with($tool, 'family-profile.')) {
            $profile = $this->ownedProfile($actor, (int) ($payload['resource_id'] ?? 0), true);

            return $this->issueResourceRecap($actor, $ticket, $tool, $profile, (string) ($payload['intent_id'] ?? ''));
        }
        if ($tool === 'care-request.withdraw') {
            $request = $this->ownedRequest($actor, (int) ($payload['resource_id'] ?? 0));

            return $this->issueResourceRecap($actor, $ticket, $tool, $request, (string) ($payload['intent_id'] ?? ''));
        }

        throw ValidationException::withMessages(['confirmation' => 'This action can no longer be renewed.']);
    }

    private function issueProfileRecap(User $actor, SupportTicket $ticket, AiSupportPreparation $preparation): AiSupportMessageAction
    {
        $profile = $preparation->resource_id
            ? $this->ownedProfile($actor, (int) $preparation->resource_id, true)
            : null;
        $fields = (array) data_get($preparation->payload, 'fields', []);
        $desiredState = (string) data_get($preparation->payload, 'desired_state', 'draft');
        $merged = $this->profiles->mergedData($profile, $fields);
        if ($desiredState === 'ready') {
            $candidate = $profile ? $profile->replicate() : new CareRecipientProfile;
            $candidate->fill($merged);
            $missing = [];
            if (trim((string) $candidate->preferred_name) === '') {
                $missing[] = 'preferred name';
            }
            if (! $candidate->hasMeaningfulShareableContent()) {
                $missing[] = 'at least one helpful care detail';
            }
            if ($missing !== []) {
                throw ValidationException::withMessages(['profile' => 'Before this profile can be ready, add '.implode(' and ', $missing).'.']);
            }
        }
        $tool = $desiredState === 'ready' ? 'family-profile.make-ready' : 'family-profile.save-draft';
        $previewPayload = [
            'preparation_id' => (string) $preparation->id,
            'preparation_version' => (int) $preparation->version,
            'profile_id' => $profile?->id,
            'expected_revision' => $profile ? (int) data_get($preparation->payload, 'expected_revision') : null,
            'desired_state' => $desiredState,
            'fields' => $fields,
            'fields_hash' => $this->hashFields($fields),
        ];

        return $this->issueAction(
            $actor,
            $ticket,
            $tool,
            $previewPayload,
            (string) data_get($preparation->payload, 'intent_id', 'FAM-PROFILE-003'),
            $profile ? 'Review changes to '.$profile->displayName() : 'Review new care receiver profile',
            $desiredState === 'ready'
                ? 'This will save a new ready version and share the approved profile details with eligible caregivers.'
                : 'This saves the profile only. It does not silently change any live request, visit, or regular care.',
            $this->visibleFields($fields),
            $desiredState === 'ready' ? 'Confirm profile is ready' : 'Confirm and save',
            ['preparation_id' => $preparation->id],
        );
    }

    private function issueResourceRecap(User $actor, SupportTicket $ticket, string $tool, mixed $resource, string $intentId): AiSupportMessageAction
    {
        if ($resource instanceof CareRecipientProfile) {
            $state = $this->state->profile($actor, (int) $resource->id, true);
            $title = match ($tool) {
                'family-profile.make-default' => 'Make '.$resource->displayName().' the default profile?',
                'family-profile.archive' => 'Archive '.$resource->displayName().'?',
                default => 'Restore '.$resource->displayName().'?',
            };
            $summary = match ($tool) {
                'family-profile.make-default' => 'New care requests will propose this profile by default. Existing care is unchanged.',
                'family-profile.archive' => 'The profile will stop being proposed for new requests. Existing records remain intact.',
                default => 'The profile will be available to use again. It will not automatically become the default.',
            };
            $fields = [
                ['label' => 'Profile', 'value' => $resource->displayName()],
                ['label' => 'Current status', 'value' => (string) $resource->status],
                ['label' => 'Used by', 'value' => ($state['dependencies']['requests'] ?? 0).' requests, '.($state['dependencies']['regular_care'] ?? 0).' regular-care plans'],
            ];
            $preview = [
                'profile_id' => (int) $resource->id,
                'expected_revision' => (int) $resource->revision,
                'expected_status' => (string) $resource->status,
            ];
        } else {
            $resource->loadMissing(['recipient', 'booking']);
            $title = 'Withdraw request #'.$resource->id.'?';
            $summary = 'Caregivers will no longer be able to apply. Active applicants and pending invitations will be closed. A request with a visit cannot be withdrawn here.';
            $fields = [
                ['label' => 'Request', 'value' => (string) ($resource->title ?: '#'.$resource->id)],
                ['label' => 'Care receiver', 'value' => (string) ($resource->recipient?->full_name ?: 'Care receiver')],
                ['label' => 'Current status', 'value' => (string) $resource->status],
            ];
            $preview = [
                'care_request_id' => (int) $resource->id,
                'expected_status' => (string) $resource->status,
                'expected_updated_at' => $resource->updated_at?->toIso8601String(),
            ];
        }

        return $this->issueAction(
            $actor, $ticket, $tool, $preview, $intentId, $title, $summary, $fields,
            match ($tool) {
                'family-profile.make-default' => 'Confirm default profile',
                'family-profile.archive' => 'Confirm archive',
                'family-profile.restore' => 'Confirm restore',
                default => 'Confirm withdrawal',
            },
            ['resource_id' => (int) $resource->id],
        );
    }

    /** @param array<string,mixed> $previewPayload @param list<array{label:string,value:string}> $fields @param array<string,mixed> $extra */
    private function issueAction(
        User $actor,
        SupportTicket $ticket,
        string $tool,
        array $previewPayload,
        string $intentId,
        string $title,
        string $summary,
        array $fields,
        string $confirmLabel,
        array $extra = [],
    ): AiSupportMessageAction {
        $created = $this->evidence->createPreview(
            $actor,
            $ticket,
            'family_lifecycle_action_v1',
            $tool,
            'v1',
            $previewPayload,
            now()->addMinutes((int) config('ai_support.confirmation_validity_minutes', 30)),
        );

        return DB::transaction(function () use ($actor, $ticket, $tool, $intentId, $title, $summary, $fields, $confirmLabel, $extra, $created): AiSupportMessageAction {
            AiSupportMessageAction::query()
                ->where('support_ticket_id', $ticket->id)
                ->where('actor_user_id', $actor->id)
                ->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)
                ->whereNull('consumed_at')->whereNull('invalidated_at')
                ->update(['invalidated_at' => now(), 'invalidation_reason' => 'superseded_domain_recap']);
            $message = $this->automatedMessage($ticket, 'Please review this recap. Nothing changes until you press the confirmation button.');
            $action = AiSupportMessageAction::query()->create([
                'id' => (string) Str::uuid(),
                'support_ticket_message_id' => $message->id,
                'support_ticket_id' => $ticket->id,
                'actor_user_id' => $actor->id,
                'action_type' => AiSupportMessageAction::TYPE_DOMAIN_RECAP,
                'payload' => [
                    'tool_id' => $tool,
                    'intent_id' => $intentId,
                    'title' => $title,
                    'summary' => $summary,
                    'fields' => $fields,
                    'confirm_label' => $confirmLabel,
                    'confirmation_reference' => $created['confirmation_reference'],
                    'idempotency_key' => (string) Str::uuid(),
                    'confirmation_action' => str_replace(['.', '-'], '_', $tool),
                    ...$extra,
                ],
                'expires_at' => now()->addMinutes((int) config('ai_support.confirmation_validity_minutes', 30)),
            ]);
            $this->events->record($ticket, 'intent_action_offered', [
                'support_ticket_message_id' => $message->id,
                'capability_id' => 'family_lifecycle_action_v1',
                'tool_id' => $tool,
                'tool_version' => 'v1',
                'result_code' => 'explicit_confirmation_issued',
                'safe_metadata' => ['intent_id' => $intentId],
            ], $actor);

            return $action;
        }, 3);
    }

    /** @param array<string,mixed> $preview @return array{outcome_code:string,domain_reference_type:string,domain_reference_id:string,receipt_reference:string} */
    private function commit(User $actor, SupportTicket $ticket, string $tool, array $preview): array
    {
        if (str_starts_with($tool, 'family-profile.save-') || $tool === 'family-profile.make-ready') {
            $preparation = AiSupportPreparation::query()->lockForUpdate()
                ->whereKey((string) ($preview['preparation_id'] ?? ''))
                ->where('actor_user_id', $actor->id)
                ->where('support_ticket_id', $ticket->id)
                ->whereIn('state', [AiSupportPreparation::STATE_READY, AiSupportPreparation::STATE_APPLIED])
                ->where('expires_at', '>', now())
                ->firstOrFail();
            if ((int) $preparation->version !== (int) ($preview['preparation_version'] ?? 0)
                || ! hash_equals($preparation->fields_hash, (string) ($preview['fields_hash'] ?? ''))) {
                throw ValidationException::withMessages(['confirmation' => 'The profile draft changed. Review the latest recap and confirm again.']);
            }
            $profile = filled($preview['profile_id'] ?? null)
                ? $this->ownedProfile($actor, (int) $preview['profile_id'], true, true)
                : null;
            if ($profile && (int) $profile->revision !== (int) ($preview['expected_revision'] ?? -1)) {
                throw ValidationException::withMessages(['confirmation' => 'This profile changed. Review the current profile and confirm again.']);
            }
            $data = $this->profiles->mergedData($profile, (array) ($preview['fields'] ?? []));
            if ($tool === 'family-profile.make-ready') {
                if (! $profile) {
                    $profile = $this->profiles->saveDraft($actor, null, $data);
                }
                $profile = $this->profiles->makeReady($actor, $profile, $data, (int) $profile->revision, true);
                $outcome = 'profile_ready_verified';
            } else {
                $profile = $this->profiles->saveDraft($actor, $profile, $data, $profile?->revision);
                $outcome = 'profile_saved_verified';
            }
            $verified = $this->ownedProfile($actor, (int) $profile->id, true);
            if ((int) $verified->revision !== (int) $profile->revision
                || ($tool === 'family-profile.make-ready' && ! $verified->isReady())) {
                throw ValidationException::withMessages(['receipt' => 'The saved profile could not be verified.']);
            }

            return [
                'outcome_code' => $outcome,
                'domain_reference_type' => 'care_profile',
                'domain_reference_id' => (string) $verified->id,
                'receipt_reference' => 'care-profile-'.$verified->id.'-revision-'.$verified->revision,
            ];
        }

        if (str_starts_with($tool, 'family-profile.')) {
            $profile = $this->ownedProfile($actor, (int) ($preview['profile_id'] ?? 0), true, true);
            if ((int) $profile->revision !== (int) ($preview['expected_revision'] ?? -1)
                || ! hash_equals((string) $profile->status, (string) ($preview['expected_status'] ?? ''))) {
                throw ValidationException::withMessages(['confirmation' => 'This profile changed. Review a fresh recap and confirm again.']);
            }
            $outcome = match ($tool) {
                'family-profile.make-default' => function () use ($actor, $profile): string {
                    $this->profiles->makeDefault($actor, $profile);

                    return 'profile_default_verified';
                },
                'family-profile.archive' => function () use ($actor, $profile): string {
                    $this->profiles->archive($actor, $profile);

                    return 'profile_archived_verified';
                },
                'family-profile.restore' => function () use ($actor, $profile): string {
                    $this->profiles->restore($actor, $profile);

                    return 'profile_restored_verified';
                },
                default => throw new AuthorizationException,
            };
            $outcomeCode = $outcome();
            $verified = $this->ownedProfile($actor, (int) $profile->id, true);
            $valid = match ($tool) {
                'family-profile.make-default' => (int) $this->familyAccounts->account($actor)->default_care_recipient_profile_id === (int) $verified->id,
                'family-profile.archive' => $verified->isArchived(),
                'family-profile.restore' => ! $verified->isArchived(),
                default => false,
            };
            if (! $valid) {
                throw ValidationException::withMessages(['receipt' => 'The profile action could not be verified.']);
            }

            return [
                'outcome_code' => $outcomeCode,
                'domain_reference_type' => 'care_profile',
                'domain_reference_id' => (string) $verified->id,
                'receipt_reference' => 'care-profile-'.$verified->id.'-revision-'.$verified->revision,
            ];
        }

        if ($tool === 'care-request.withdraw') {
            $request = $this->ownedRequest($actor, (int) ($preview['care_request_id'] ?? 0), true);
            if (! hash_equals((string) $request->status, (string) ($preview['expected_status'] ?? ''))
                || ! hash_equals((string) $request->updated_at?->toIso8601String(), (string) ($preview['expected_updated_at'] ?? ''))) {
                throw ValidationException::withMessages(['confirmation' => 'This request changed. Review a fresh recap and confirm again.']);
            }
            $result = $this->requests->withdraw($actor, $request);
            $verified = $this->ownedRequest($actor, (int) $request->id);
            if ($verified->status !== CareRequest::STATUS_CANCELLED) {
                throw ValidationException::withMessages(['receipt' => 'The request withdrawal could not be verified.']);
            }

            return [
                'outcome_code' => 'request_withdrawn_verified',
                'domain_reference_type' => 'care_request',
                'domain_reference_id' => (string) $verified->id,
                'receipt_reference' => 'care-request-'.$verified->id.'-withdrawn',
            ];
        }

        throw new AuthorizationException;
    }

    /** @param array<string,mixed> $patch */
    private function startOrUpdateProfileDraft(User $actor, SupportTicket $ticket, ?CareRecipientProfile $profile, array $patch, string $intentId, bool $makeReady): AiSupportPreparation
    {
        $account = $this->familyAccounts->account($actor);

        return DB::transaction(function () use ($actor, $ticket, $profile, $patch, $intentId, $makeReady, $account): AiSupportPreparation {
            $existing = AiSupportPreparation::query()->lockForUpdate()
                ->where('support_ticket_id', $ticket->id)
                ->where('actor_user_id', $actor->id)
                ->where('contract_id', self::PROFILE_CONTRACT)
                ->whereIn('state', [AiSupportPreparation::STATE_READY, AiSupportPreparation::STATE_APPLIED])
                ->where('expires_at', '>', now())
                ->latest('updated_at')->first();
            if ($existing && (int) ($existing->resource_id ?? 0) !== (int) ($profile?->id ?? 0)) {
                $existing->forceFill(['state' => AiSupportPreparation::STATE_CANCELLED, 'cancelled_at' => now()])->save();
                $existing = null;
            }
            $fields = array_replace((array) data_get($existing?->payload, 'fields', []), $patch);
            $this->profiles->mergedData($profile, $fields);
            $payload = [
                'fields' => $fields,
                'intent_id' => $intentId,
                'desired_state' => $makeReady ? 'ready' : (string) data_get($existing?->payload, 'desired_state', 'draft'),
                'expected_revision' => $profile?->revision,
            ];
            $values = [
                'support_ticket_id' => $ticket->id,
                'actor_user_id' => $actor->id,
                'family_account_id' => $account->id,
                'contract_id' => self::PROFILE_CONTRACT,
                'contract_version' => AiSupportPreparationContractRegistry::VERSION,
                'state' => AiSupportPreparation::STATE_READY,
                'navigation_target_id' => $profile ? 'family.care_profile.edit' : 'family.care_profile.create',
                'resource_type' => $profile ? 'care_profile' : null,
                'resource_id' => $profile?->id,
                'payload' => $payload,
                'fields_hash' => $this->hashFields($fields),
                'version' => $existing ? (int) $existing->version + 1 : 1,
                'expires_at' => now()->addHours(24),
                'cancelled_at' => null,
            ];
            if ($existing) {
                $existing->forceFill($values)->save();

                return $existing->fresh();
            }

            return AiSupportPreparation::query()->create(['id' => (string) Str::uuid(), ...$values]);
        }, 3);
    }

    private function activeProfileDraft(User $actor, SupportTicket $ticket): ?AiSupportPreparation
    {
        return AiSupportPreparation::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('actor_user_id', $actor->id)
            ->where('contract_id', self::PROFILE_CONTRACT)
            ->whereIn('state', [AiSupportPreparation::STATE_READY, AiSupportPreparation::STATE_APPLIED])
            ->where('expires_at', '>', now())
            ->latest('updated_at')->first();
    }

    /** @return array<string,mixed> */
    private function profilePatchFromMessage(string $intentId, string $message): array
    {
        $text = trim($message);
        $detail = trim((string) preg_replace('/^.*?\b(?:to|is|as|that|saying|notes?|information)\b\s*/iu', '', $text, 1));
        $detail = trim((string) preg_replace('/^say\s*:?\s*/iu', '', $detail, 1));
        $detail = Str::limit($detail !== '' ? $detail : $text, 3000, '');
        $name = null;
        if (preg_match('/\b(?:profile\s+(?:for|named)|preferred\s+name\s+(?:is|to)|name\s+is)\s+([\p{L}][\p{L}\p{M}\'’ .-]{0,79}?)(?=\s*(?:[?.!,]|$))/iu', $text, $matches) === 1) {
            $name = trim((string) $matches[1]);
        }

        return array_filter(match ($intentId) {
            'FAM-PROFILE-003', 'FAM-REQUEST-006' => ['preferred_name' => $name],
            'FAM-PROFILE-007' => $this->identityPatch($text, $name),
            'FAM-PROFILE-008' => $this->aboutPatch($text, $detail),
            'FAM-PROFILE-009' => ['communication_notes' => $detail],
            'FAM-PROFILE-010' => ['everyday_health_context' => $detail],
            'FAM-PROFILE-011' => ['mobility_level' => $this->mobilityLevel($text), 'mobility_notes' => $detail],
            'FAM-PROFILE-012' => [str_contains(mb_strtolower($text), 'food') || str_contains(mb_strtolower($text), 'allerg')
                ? 'food_and_drink_notes' : (str_contains(mb_strtolower($text), 'overnight') ? 'sleep_overnight_notes' : 'routine_notes') => $detail],
            'FAM-PROFILE-013' => ['safety_notes' => $detail],
            'FAM-PROFILE-014' => $this->contactPatch($text),
            'FAM-PROFILE-018' => ['about_them' => $detail],
            default => [],
        }, static fn ($value): bool => $value !== null && $value !== '');
    }

    /** @return array<string,string> */
    private function aboutPatch(string $text, string $detail): array
    {
        $lower = mb_strtolower($text);
        $genericEditRequest = preg_match('/\b(?:finish|edit|update|change|correct)\b.*\bprofile\b/u', $lower) === 1
            && preg_match('/\b(?:add|about|description|interest|comfort|enjoy|like|prefer|note|saying|that|with)\w*\b/u', $lower) !== 1;

        return $genericEditRequest ? [] : ['about_them' => $detail];
    }

    /** @return array<string,mixed> */
    private function identityPatch(string $text, ?string $name): array
    {
        $patch = $name ? ['preferred_name' => $name] : [];
        if (preg_match('/\b(?:born|date of birth|dob)\s+(?:is\s+)?(\d{4}-\d{2}-\d{2})\b/i', $text, $matches)) {
            $patch['date_of_birth'] = $matches[1];
        }
        if (preg_match('/\bpronouns?\s+(?:are|is|to)\s+([\p{L}\/-]{2,30})/iu', $text, $matches)) {
            $patch['pronouns'] = $matches[1];
        }
        if (preg_match('/\b(?:relationship|my)\s+(?:is\s+)?(mother|father|parent|spouse|partner|sister|brother|friend|self)\b/i', $text, $matches)) {
            $patch['relationship_to_family'] = Str::ucfirst(mb_strtolower($matches[1]));
        }

        return $patch;
    }

    /** @return array<string,mixed> */
    private function contactPatch(string $text): array
    {
        $patch = ['include_additional_contact' => true];
        if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text, $matches)) {
            $patch['additional_contact_email'] = $matches[0];
        }
        if (preg_match('/\+?1?[ .-]?(?:\(?\d{3}\)?[ .-]?)\d{3}[ .-]?\d{4}/', $text, $matches)) {
            $patch['additional_contact_phone'] = $matches[0];
        }
        if (preg_match('/\bcontact\s+(?:is|named)\s+([\p{L}][\p{L}\p{M}\'’ .-]{1,79})/iu', $text, $matches)) {
            $patch['additional_contact_name'] = trim($matches[1]);
        }

        return count($patch) > 1 ? $patch : [];
    }

    private function mobilityLevel(string $text): ?string
    {
        $lower = mb_strtolower($text);

        return match (true) {
            str_contains($lower, 'wheelchair'), str_contains($lower, 'walker'), str_contains($lower, 'cane') => 'uses_aid',
            str_contains($lower, 'transfer') => 'transfer_help',
            str_contains($lower, 'hands-on'), str_contains($lower, 'hands on') => 'hands_on',
            str_contains($lower, 'nearby') => 'someone_nearby',
            str_contains($lower, 'independent') => 'independent',
            str_contains($lower, 'not sure') => 'not_sure',
            default => null,
        };
    }

    private function selectProfile(User $actor, string $message, bool $archived): ?CareRecipientProfile
    {
        $account = $this->familyAccounts->account($actor);
        $query = CareRecipientProfile::query()->forFamilyAccount($account)->latest('updated_at');
        $archived
            ? $query->where('status', CareRecipientProfile::STATUS_ARCHIVED)
            : $query->where('status', '!=', CareRecipientProfile::STATUS_ARCHIVED);
        $id = $this->idFromMessage($message);
        if ($id) {
            return (clone $query)->whereKey($id)->first();
        }
        $profiles = $query->get();
        $named = $profiles->first(fn (CareRecipientProfile $profile): bool => str_contains(mb_strtolower($message), mb_strtolower($profile->displayName())));

        return $named ?: ($profiles->count() === 1 ? $profiles->first() : null);
    }

    /** @param list<string>|null $statuses */
    private function selectRequest(User $actor, string $message, ?array $statuses = null): ?CareRequest
    {
        $account = $this->familyAccounts->account($actor);
        $query = CareRequest::query()->forFamilyAccount($account)->latest('updated_at');
        if ($statuses) {
            $query->whereIn('status', $statuses);
        }
        $id = $this->idFromMessage($message);

        return $id ? (clone $query)->whereKey($id)->first() : $query->first();
    }

    private function ownedProfile(User $actor, int $id, bool $includeArchived, bool $lock = false): CareRecipientProfile
    {
        $query = CareRecipientProfile::query()->forFamilyAccount($this->familyAccounts->account($actor));
        if (! $includeArchived) {
            $query->where('status', '!=', CareRecipientProfile::STATUS_ARCHIVED);
        }
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->findOrFail($id);
    }

    private function ownedRequest(User $actor, int $id, bool $lock = false): CareRequest
    {
        $query = CareRequest::query()->forFamilyAccount($this->familyAccounts->account($actor));
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->findOrFail($id);
    }

    private function idFromMessage(string $message): ?int
    {
        return preg_match('/(?:#|request\s+|profile\s+)(\d{1,10})\b/i', $message, $matches)
            ? (int) $matches[1]
            : null;
    }

    /** @param array<string,mixed> $fields @return list<array{label:string,value:string}> */
    private function visibleFields(array $fields): array
    {
        return collect($fields)->map(fn ($value, string $field): array => [
            'label' => Str::headline($field),
            'value' => is_array($value) ? collect($value)->map(fn ($item) => is_array($item) ? json_encode($item) : (string) $item)->implode(', ') : (is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value),
        ])->values()->all();
    }

    /** @param array<string,mixed> $fields */
    private function hashFields(array $fields): string
    {
        ksort($fields);

        return hash('sha256', json_encode($fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function available(User $actor, SupportTicket $ticket): bool
    {
        return $this->eligibility->evaluate($actor, 'family_lifecycle_action_v1', $ticket)->allowed;
    }

    private function domainAction(User $actor, SupportTicket $ticket, string $actionId, bool $active = true): AiSupportMessageAction
    {
        $action = AiSupportMessageAction::query()
            ->whereKey($actionId)
            ->where('support_ticket_id', $ticket->id)
            ->where('actor_user_id', $actor->id)
            ->where('action_type', AiSupportMessageAction::TYPE_DOMAIN_RECAP)
            ->firstOrFail();
        if ($active && ! $action->isActive() && ! $action->consumed_at) {
            throw ValidationException::withMessages(['confirmation' => 'This confirmation expired or changed.']);
        }

        return $action;
    }

    private function receiptText(AiSupportConfirmedActionEvidence $evidence): string
    {
        return match ($evidence->outcome_code) {
            'profile_saved_verified' => 'Your care receiver profile was saved and checked. Existing requests and care were not silently changed.',
            'profile_ready_verified' => 'The care receiver profile is ready and the saved version was checked.',
            'profile_default_verified' => 'The default care receiver profile was changed and checked.',
            'profile_archived_verified' => 'The care receiver profile was archived and checked.',
            'profile_restored_verified' => 'The care receiver profile was restored and checked.',
            'request_withdrawn_verified' => 'The request was withdrawn and checked. Caregivers can no longer apply.',
            default => 'The action was completed and checked.',
        };
    }

    private function receiptUrl(User $actor, AiSupportConfirmedActionEvidence $evidence): string
    {
        if ($evidence->domain_reference_type === 'care_request') {
            return route('family.requests.show', $this->ownedRequest($actor, (int) $evidence->domain_reference_id));
        }

        return route('family.care-profiles.index');
    }

    private function automatedMessage(SupportTicket $ticket, string $body): SupportTicketMessage
    {
        return DB::transaction(function () use ($ticket, $body): SupportTicketMessage {
            $locked = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->responder_mode !== SupportTicket::RESPONDER_MODE_AUTOMATED
                || $locked->status === SupportTicket::STATUS_CLOSED
                || $locked->transcript_deleted_at) {
                throw new AuthorizationException;
            }
            $message = SupportTicketMessage::query()->create([
                'support_ticket_id' => $locked->id,
                'sender_user_id' => null,
                'kind' => SupportTicketMessage::KIND_PUBLIC,
                'responder_type' => SupportTicketMessage::RESPONDER_AUTOMATED,
                'body' => Str::limit(trim($body), 1000, ''),
                'client_message_id' => (string) Str::uuid(),
            ]);
            $locked->forceFill([
                'last_public_message_at' => $message->created_at,
                'last_public_message_sender_id' => null,
                'opener_last_read_at' => null,
            ])->save();

            return $message;
        }, 3);
    }
}
