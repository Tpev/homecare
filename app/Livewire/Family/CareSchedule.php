<?php

namespace App\Livewire\Family;

use App\Services\Family\FamilyCarePresentationService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class CareSchedule extends Component
{
    private const PAGE_SIZE = 8;

    #[Url(as: 'type')]
    public string $careType = 'all';

    #[Url(as: 'person')]
    public string $recipient = 'all';

    public int $visibleLimit = self::PAGE_SIZE;

    public array $careTypeOptions = [
        ['label' => 'All upcoming care', 'value' => 'all'],
        ['label' => 'One-time visits', 'value' => 'one_time'],
        ['label' => 'Recurring care visits', 'value' => 'regular'],
        ['label' => 'Extra visits', 'value' => 'extra'],
        ['label' => 'Continuous care', 'value' => 'coverage'],
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'family', 403);
    }

    public function updatingCareType(): void
    {
        $this->visibleLimit = self::PAGE_SIZE;
    }

    public function updatingRecipient(): void
    {
        $this->visibleLimit = self::PAGE_SIZE;
    }

    public function loadMoreVisits(): void
    {
        $this->visibleLimit += self::PAGE_SIZE;
    }

    public function render(FamilyCarePresentationService $presentation)
    {
        $account = app(FamilyAccountContext::class)->account(auth()->user());
        $careType = $this->careType === 'all' ? null : $this->careType;
        $recipient = $this->recipient === 'all' ? null : $this->recipient;
        $loadedVisits = $presentation->upcomingVisits(
            $account,
            $this->visibleLimit + 1,
            $careType,
            $recipient,
        );
        $hasMoreVisits = $loadedVisits->count() > $this->visibleLimit;
        $visits = $loadedVisits->take($this->visibleLimit)->values();
        $totalVisitCount = $presentation->upcomingVisitCount($account, $careType, $recipient);

        $recipientOptions = collect([['label' => 'Everyone', 'value' => 'all']])
            ->merge(
                $presentation->upcomingRecipientNames($account)
                    ->map(fn (string $name): array => ['label' => $name, 'value' => $name])
            )
            ->all();

        $now = now();
        $endOfWeek = $now->copy()->endOfWeek();
        $visitSections = collect([
            'today' => [
                'label' => 'Today',
                'description' => 'Care happening now or scheduled today.',
                'visits' => $visits->filter(fn (array $visit): bool => $visit['starts_at']?->lte($now->copy()->endOfDay()) ?? false)->values(),
            ],
            'week' => [
                'label' => 'This week',
                'description' => 'The rest of this week, grouped by day.',
                'visits' => $visits->filter(fn (array $visit): bool => ($visit['starts_at']?->isAfter($now->copy()->endOfDay()) ?? false)
                    && ($visit['starts_at']?->lte($endOfWeek) ?? false))->values(),
            ],
            'later' => [
                'label' => 'Later',
                'description' => 'Confirmed care after this week.',
                'visits' => $visits->filter(fn (array $visit): bool => $visit['starts_at']?->gt($endOfWeek) ?? false)->values(),
            ],
        ])->filter(fn (array $section): bool => $section['visits']->isNotEmpty());

        return view('livewire.family.care-schedule', [
            'visits' => $visits,
            'visitSections' => $visitSections,
            'recipientOptions' => $recipientOptions,
            'totalVisitCount' => $totalVisitCount,
            'hasMoreVisits' => $hasMoreVisits,
        ]);
    }
}
