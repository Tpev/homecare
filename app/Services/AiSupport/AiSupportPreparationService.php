<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportMessageAction;
use App\Models\AiSupportPreparation;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AiSupportPreparationService
{
    public const SESSION_KEY = 'ai_support.preparation_id';

    private const FORBIDDEN_KEYS = [
        'password', 'card', 'card_number', 'cvc', 'cvv', 'bank', 'bank_account', 'routing_number',
        'token', 'verification_code', 'identity_document', 'secret', 'api_key',
    ];

    public function __construct(
        private readonly AiSupportPreparationContractRegistry $contracts,
        private readonly FamilyAccountContext $familyAccounts,
        private readonly NavigationTargetRegistry $navigation,
        private readonly AiSupportEventRecorder $events,
    ) {}

    /**
     * @param  array<string,mixed>  $fields
     * @param  array{resource_type?:string|null,resource_id?:int|null,target_id?:string|null,intent_id?:string|null}  $context
     */
    public function prepare(
        User $actor,
        SupportTicket $ticket,
        string $contractId,
        array $fields,
        array $context = [],
    ): AiSupportPreparation {
        $this->authorize($actor, $ticket);
        $definition = $this->contracts->definition($contractId);
        $fields = $this->validatedFields($definition, $fields);
        $targetId = (string) ($context['target_id'] ?? $definition['target']);
        $resource = [
            'resource_type' => $context['resource_type'] ?? null,
            'resource_id' => $context['resource_id'] ?? null,
        ];
        if (! $this->navigation->allowedFor($actor, $targetId, $resource)) {
            throw new AuthorizationException('The preparation destination is not authorized for this Family account.');
        }

        return DB::transaction(function () use ($actor, $ticket, $contractId, $definition, $fields, $targetId, $resource, $context): AiSupportPreparation {
            $locked = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->authorize($actor, $locked);
            AiSupportPreparation::query()
                ->where('actor_user_id', $actor->id)
                ->where('contract_id', $contractId)
                ->whereIn('state', [AiSupportPreparation::STATE_READY, AiSupportPreparation::STATE_APPLIED])
                ->update(['state' => AiSupportPreparation::STATE_CANCELLED, 'cancelled_at' => now()]);

            $preparation = AiSupportPreparation::query()->create([
                'support_ticket_id' => $locked->id,
                'actor_user_id' => $actor->id,
                'family_account_id' => $this->familyAccounts->account($actor)->id,
                'contract_id' => $contractId,
                'contract_version' => AiSupportPreparationContractRegistry::VERSION,
                'state' => AiSupportPreparation::STATE_READY,
                'navigation_target_id' => $targetId,
                'resource_type' => $resource['resource_type'],
                'resource_id' => $resource['resource_id'],
                'payload' => ['fields' => $fields],
                'fields_hash' => hash('sha256', json_encode($fields, JSON_THROW_ON_ERROR)),
                'expires_at' => now()->addHours(24),
            ]);
            $message = $this->automatedMessage(
                $locked,
                'I prepared these '.$definition['label'].' details for you to review. They are visible below and have not been saved, sent, submitted, approved, or confirmed.',
            );
            AiSupportMessageAction::query()->create([
                'id' => (string) Str::uuid(),
                'support_ticket_message_id' => $message->id,
                'support_ticket_id' => $locked->id,
                'actor_user_id' => $actor->id,
                'action_type' => AiSupportMessageAction::TYPE_PREPARATION,
                'payload' => [
                    'preparation_id' => $preparation->id,
                    'label' => 'Review in the app',
                    'title' => Str::headline((string) $definition['label']),
                    'visible_fields' => $this->visibleFields($fields),
                ],
                'expires_at' => $preparation->expires_at,
            ]);
            $this->events->record($locked, 'intent_prepared', [
                'support_ticket_message_id' => $message->id,
                'capability_id' => 'family_preparation_v1',
                'navigation_target_id' => $targetId,
                'result_code' => 'prepared_not_committed',
                'safe_metadata' => [
                    'intent_id' => (string) ($context['intent_id'] ?? 'FAM-SUPPORT-004'),
                    'preparation_contract_id' => $contractId,
                ],
            ], $actor);

            return $preparation;
        }, 3);
    }

    public function applyFromAction(User $actor, SupportTicket $ticket, string $actionId): string
    {
        $this->authorize($actor, $ticket);

        return DB::transaction(function () use ($actor, $ticket, $actionId): string {
            $action = AiSupportMessageAction::query()->lockForUpdate()
                ->whereKey($actionId)
                ->where('support_ticket_id', $ticket->id)
                ->where('actor_user_id', $actor->id)
                ->where('action_type', AiSupportMessageAction::TYPE_PREPARATION)
                ->firstOrFail();
            if (! $action->isActive()) {
                throw ValidationException::withMessages(['preparation' => 'This prepared form expired. Ask me to prepare it again.']);
            }
            $preparation = AiSupportPreparation::query()->lockForUpdate()
                ->whereKey((string) data_get($action->payload, 'preparation_id'))
                ->where('actor_user_id', $actor->id)
                ->firstOrFail();
            if (! $preparation->isUsable()) {
                throw ValidationException::withMessages(['preparation' => 'This prepared form is no longer available.']);
            }
            $resource = ['resource_type' => $preparation->resource_type, 'resource_id' => $preparation->resource_id];
            if (! $this->navigation->allowedFor($actor, $preparation->navigation_target_id, $resource)) {
                throw new AuthorizationException;
            }
            $preparation->forceFill([
                'state' => AiSupportPreparation::STATE_APPLIED,
                'applied_at' => now(),
                'version' => $preparation->version + 1,
            ])->save();
            session()->put(self::SESSION_KEY, $preparation->id);
            $this->events->record($ticket, 'intent_preparation_opened', [
                'capability_id' => 'family_preparation_v1',
                'navigation_target_id' => $preparation->navigation_target_id,
                'result_code' => 'opened_editable_form',
                'safe_metadata' => ['preparation_contract_id' => $preparation->contract_id],
            ], $actor);

            return $this->navigation->urlFor($actor, $preparation->navigation_target_id, $resource);
        }, 3);
    }

    /** @return array<string,mixed> */
    public function consume(User $actor, string $contractId, ?string $resourceType = null, ?int $resourceId = null): array
    {
        $id = (string) session()->get(self::SESSION_KEY, '');
        if ($id === '') {
            return [];
        }
        $preparation = AiSupportPreparation::query()
            ->whereKey($id)
            ->where('actor_user_id', $actor->id)
            ->where('contract_id', $contractId)
            ->first();
        if (! $preparation || ! $preparation->isUsable()
            || ($resourceType !== null && $preparation->resource_type !== $resourceType)
            || ($resourceId !== null && (int) $preparation->resource_id !== $resourceId)) {
            return [];
        }

        return (array) data_get($preparation->payload, 'fields', []);
    }

    public function cancel(User $actor, string $preparationId): void
    {
        $preparation = AiSupportPreparation::query()->whereKey($preparationId)->where('actor_user_id', $actor->id)->firstOrFail();
        $preparation->forceFill(['state' => AiSupportPreparation::STATE_CANCELLED, 'cancelled_at' => now()])->save();
        if (session()->get(self::SESSION_KEY) === $preparation->id) {
            session()->forget(self::SESSION_KEY);
        }
    }

    /** @param array<string,mixed> $definition @param array<string,mixed> $fields @return array<string,mixed> */
    private function validatedFields(array $definition, array $fields): array
    {
        $unknown = array_diff(array_keys($fields), (array) $definition['fields']);
        $forbidden = array_intersect(array_map('strtolower', array_keys($fields)), self::FORBIDDEN_KEYS);
        if ($unknown !== [] || $forbidden !== []) {
            throw ValidationException::withMessages(['preparation' => 'The prepared form contains a field that is not allowed.']);
        }
        $clean = [];
        foreach ($fields as $key => $value) {
            if (is_array($value)) {
                if (count($value) > 30 || collect($value)->contains(fn ($item): bool => ! is_scalar($item))) {
                    throw ValidationException::withMessages(['preparation' => 'Prepared list values are invalid.']);
                }
                $clean[$key] = collect($value)
                    ->map(fn ($item) => Str::limit(trim((string) $item), 500, ''))
                    ->all();

                continue;
            }
            if (! is_scalar($value) && $value !== null) {
                throw ValidationException::withMessages(['preparation' => 'Prepared values must be simple and editable.']);
            }
            $text = Str::limit(trim((string) $value), 3000, '');
            if (preg_match('/\b(?:password|cvc|cvv|verification code|api key|secret token)\b/i', $text)
                || preg_match('/\b(?:\d[ -]*?){13,19}\b/', $text)) {
                throw ValidationException::withMessages(['preparation' => 'Passwords, card details, verification codes, and secrets cannot be prepared in chat.']);
            }
            $clean[$key] = $text;
        }
        if ($clean === []) {
            throw ValidationException::withMessages(['preparation' => 'Add at least one detail before preparing the form.']);
        }

        return $clean;
    }

    /** @param array<string,mixed> $fields @return list<array{label:string,value:string}> */
    private function visibleFields(array $fields): array
    {
        return collect($fields)
            ->reject(fn ($value, string $key): bool => str_ends_with($key, '_id')
                || in_array($key, ['route_name', 'resource_type'], true))
            ->map(fn ($value, string $key): array => [
                'label' => Str::headline($key),
                'value' => is_array($value) ? implode(', ', $value) : (string) $value,
            ])->values()->all();
    }

    private function authorize(User $actor, SupportTicket $ticket): void
    {
        if ($actor->role !== 'family'
            || (int) $ticket->opener_user_id !== (int) $actor->id
            || ! Gate::forUser($actor)->allows('view', $ticket)
            || $ticket->responder_mode !== SupportTicket::RESPONDER_MODE_AUTOMATED
            || ! $this->familyAccounts->membershipFor($actor, false)) {
            throw new AuthorizationException;
        }
    }

    private function automatedMessage(SupportTicket $ticket, string $body): SupportTicketMessage
    {
        $message = SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'sender_user_id' => null,
            'kind' => SupportTicketMessage::KIND_PUBLIC,
            'responder_type' => SupportTicketMessage::RESPONDER_AUTOMATED,
            'body' => $body,
            'client_message_id' => (string) Str::uuid(),
        ]);
        $ticket->forceFill([
            'last_public_message_at' => $message->created_at,
            'last_public_message_sender_id' => null,
            'opener_last_read_at' => null,
        ])->save();

        return $message;
    }
}
