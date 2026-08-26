<?php

namespace App\Livewire\Family;

use App\Services\Family\FamilyCareActionService;
use App\Services\Family\FamilyCarePresentationService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class CareActionsIndex extends Component
{
    private const PAGE_SIZE = 8;

    #[Url(as: 'person')]
    public string $recipient = 'all';

    public int $visibleLimit = self::PAGE_SIZE;

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'family', 403);
    }

    public function updatingRecipient(): void
    {
        $this->visibleLimit = self::PAGE_SIZE;
    }

    public function loadMoreActions(): void
    {
        $this->visibleLimit += self::PAGE_SIZE;
    }

    public function render(
        FamilyCareActionService $actions,
        FamilyCarePresentationService $presentation,
    ) {
        $account = app(FamilyAccountContext::class)->account(auth()->user());
        $selectedRecipient = $this->recipient === 'all' ? null : $this->recipient;
        $allActions = $actions->forAccount($account, $selectedRecipient);
        $recipientOptions = collect([['label' => 'Everyone', 'value' => 'all']])
            ->merge($presentation->careRecipientNames($account)->map(
                fn (string $name): array => ['label' => $name, 'value' => $name]
            ))
            ->all();

        return view('livewire.family.care-actions-index', [
            'actions' => $allActions->take($this->visibleLimit)->values(),
            'totalActionCount' => $allActions->count(),
            'hasMoreActions' => $allActions->count() > $this->visibleLimit,
            'recipientOptions' => $recipientOptions,
        ]);
    }
}
