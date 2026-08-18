<?php

namespace App\Services\AiSupport;

use App\Models\CareBooking;
use App\Models\CareRecipientProfile;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Support\FamilyActionInboxBuilder;

class FamilyAssistantHomeService
{
    public function __construct(
        private readonly FamilyAccountContext $familyAccounts,
        private readonly FamilyActionInboxBuilder $actionInbox,
        private readonly FamilyPaymentMethodStatusReader $paymentStatus,
    ) {}

    /** @return array{personalized:list<array{label:string,message:string}>,general:list<array{label:string,message:?string}>} */
    public function for(User $actor): array
    {
        $membership = $this->familyAccounts->membershipFor($actor, false);
        if ($actor->role !== 'family' || ! $membership) {
            return ['personalized' => [], 'general' => []];
        }

        $personalized = collect();
        foreach ($this->actionInbox->buildForAccount($membership->familyAccount)->take(3) as $item) {
            $label = trim((string) ($item['label'] ?? $item['title'] ?? 'Review care item'));
            $personalized->push(['label' => $label, 'message' => 'Help me '.$this->lowerFirst($label)]);
        }

        if ($personalized->count() < 3) {
            $draft = CareRecipientProfile::query()
                ->forFamilyAccount($membership->familyAccount)
                ->where('status', CareRecipientProfile::STATUS_DRAFT)
                ->oldest('updated_at')
                ->first();
            if ($draft) {
                $personalized->push([
                    'label' => 'Finish '.$draft->displayName().'\'s profile',
                    'message' => 'Help me finish a care receiver profile',
                ]);
            }
        }

        if ($personalized->count() < 3) {
            $next = CareBooking::query()
                ->forFamilyAccount($membership->familyAccount)
                ->whereIn('status', [CareBooking::STATUS_SCHEDULED, CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED])
                ->where('scheduled_end_at', '>=', now())
                ->orderBy('scheduled_start_at')
                ->first();
            if ($next) {
                $personalized->push(['label' => 'Check my next visit', 'message' => 'Show me my next visit']);
            }
        }

        if ($personalized->count() < 3) {
            try {
                $payment = $this->paymentStatus->read($actor);
                if (($payment['attention'] ?? 'ready') !== 'ready') {
                    $personalized->push([
                        'label' => ($payment['ready'] ?? false) ? 'Update payment method' : 'Add payment method',
                        'message' => ($payment['ready'] ?? false) ? 'Help me update my payment method' : 'Help me add a payment method',
                    ]);
                }
            } catch (\Throwable) {
                // The general Payment help choice remains available without inventing state.
            }
        }

        return [
            'personalized' => $personalized->unique('label')->take(3)->values()->all(),
            'general' => [
                ['label' => 'See what needs my attention', 'message' => 'What needs my attention?'],
                ['label' => 'Create a care request', 'message' => 'I need to create a care request'],
                ['label' => 'Check my next visit', 'message' => 'Show me my next visit'],
                ['label' => 'Payment help', 'message' => 'I need help with a payment'],
                ['label' => 'Something else', 'message' => null],
                ['label' => 'Talk to a person', 'message' => 'I want to talk to a person'],
            ],
        ];
    }

    private function lowerFirst(string $value): string
    {
        return mb_strtolower(mb_substr($value, 0, 1)).mb_substr($value, 1);
    }
}
