<?php

namespace App\Services\Family;

use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\FamilyAccount;
use App\Support\FamilyActionInboxBuilder;
use Illuminate\Support\Collection;

class FamilyCareActionService
{
    public function __construct(
        private readonly FamilyActionInboxBuilder $actions,
    ) {}

    /**
     * Return unresolved family actions, optionally scoped to one recipient.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forAccount(FamilyAccount $account, ?string $recipient = null): Collection
    {
        $items = $this->actions->buildForAccount($account);

        if (! $recipient || $recipient === 'all') {
            return $items;
        }

        $requestIds = $items
            ->where('resource_type', 'care_request')
            ->pluck('resource_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique();
        $planIds = $items
            ->where('resource_type', 'care_plan')
            ->pluck('resource_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique();

        $requestRecipients = CareRequest::query()
            ->with('recipient:id,care_request_id,full_name')
            ->forFamilyAccount($account)
            ->whereIn('id', $requestIds)
            ->get(['id'])
            ->mapWithKeys(fn (CareRequest $request): array => [
                $request->id => $request->recipient?->full_name,
            ]);
        $planRecipients = CarePlan::query()
            ->forFamilyAccount($account)
            ->whereIn('id', $planIds)
            ->get(['id', 'recipient_snapshot'])
            ->mapWithKeys(fn (CarePlan $plan): array => [
                $plan->id => $plan->recipientName(),
            ]);

        return $items
            ->filter(function (array $item) use ($recipient, $requestRecipients, $planRecipients): bool {
                $itemRecipient = match ($item['resource_type'] ?? null) {
                    'care_request' => $requestRecipients->get((int) ($item['resource_id'] ?? 0)),
                    'care_plan' => $planRecipients->get((int) ($item['resource_id'] ?? 0)),
                    default => null,
                };

                return $itemRecipient === null || $itemRecipient === $recipient;
            })
            ->values();
    }
}
