<?php

namespace App\Livewire\Family;

use App\Services\Family\FamilyCarePresentationService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
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

    #[Url(as: 'view', except: 'list')]
    public string $scheduleView = 'list';

    #[Url(except: '')]
    public string $month = '';

    #[Url(as: 'day', except: '')]
    public string $selectedDate = '';

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

    public function setView(string $view): void
    {
        $this->scheduleView = $view === 'calendar' ? 'calendar' : 'list';
    }

    public function previousMonth(): void
    {
        $this->month = $this->calendarMonth()->subMonthNoOverflow()->format('Y-m');
        $this->selectedDate = '';
    }

    public function nextMonth(): void
    {
        $this->month = $this->calendarMonth()->addMonthNoOverflow()->format('Y-m');
        $this->selectedDate = '';
    }

    public function goToToday(): void
    {
        $this->month = now()->format('Y-m');
        $this->selectedDate = now()->toDateString();
    }

    public function selectDate(string $date): void
    {
        if (! $this->validDate($date)) {
            return;
        }

        $this->selectedDate = $date;
        $this->month = substr($date, 0, 7);
    }

    private function validDate(string $date): bool
    {
        return preg_match('/^[1-9]\d{3}-\d{2}-\d{2}$/', $date) === 1
            && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4));
    }

    private function calendarMonth(): Carbon
    {
        if (! $this->validDate($this->month.'-01')) {
            $this->month = now()->format('Y-m');
        }

        return Carbon::createFromFormat('!Y-m-d', $this->month.'-01');
    }

    public function render(FamilyCarePresentationService $presentation)
    {
        $this->scheduleView = $this->scheduleView === 'calendar' ? 'calendar' : 'list';
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

        $calendar = [];
        if ($this->scheduleView === 'calendar') {
            // Open on the nearest visit, so a future care arrangement is immediately visible.
            if ($this->month === '') {
                $firstStart = $visits->first()['starts_at'] ?? null;
                $this->month = $this->validDate($this->selectedDate) ? substr($this->selectedDate, 0, 7)
                    : ($firstStart && $firstStart->isFuture() ? $firstStart->format('Y-m') : now()->format('Y-m'));
            }
            $calendarMonth = $this->calendarMonth();
            $gridStart = $calendarMonth->copy()->startOfWeek(CarbonInterface::SUNDAY);
            $gridUntil = $calendarMonth->copy()->endOfMonth()->endOfWeek(CarbonInterface::SATURDAY)->addDay()->startOfDay();
            $calendarVisits = $presentation->upcomingVisitsInRange($account, $gridStart, $gridUntil, $careType, $recipient);
            $calendarDays = collect();
            for ($day = $gridStart->copy(); $day->lt($gridUntil); $day->addDay()) {
                $nextDay = $day->copy()->addDay();
                $dayVisits = $calendarVisits->filter(fn (array $visit): bool => $visit['starts_at']->lt($nextDay)
                    && ($visit['ends_at'] ? $visit['ends_at']->gt($day) : $visit['starts_at']->gte($day)))->values();
                $calendarDays->push(['date' => $day->copy(), 'visits' => $dayVisits, 'in_month' => $day->format('Y-m') === $this->month]);
            }
            if (! $this->validDate($this->selectedDate) || substr($this->selectedDate, 0, 7) !== $this->month) {
                $firstDayWithCare = $calendarDays->first(fn (array $day): bool => $day['in_month'] && $day['visits']->isNotEmpty());
                $this->selectedDate = $firstDayWithCare ? $firstDayWithCare['date']->toDateString()
                    : ($calendarMonth->isCurrentMonth() ? now()->toDateString() : $calendarMonth->toDateString());
            }
            $selectedDay = Carbon::createFromFormat('!Y-m-d', $this->selectedDate);
            $selectedVisits = $calendarDays->first(fn (array $day): bool => $day['date']->isSameDay($selectedDay))['visits'];
            $monthVisitCount = $calendarDays->where('in_month', true)->flatMap(fn (array $day) => $day['visits'])->unique('id')->count();
            $calendar = compact('calendarMonth', 'calendarVisits', 'calendarDays', 'selectedDay', 'selectedVisits', 'monthVisitCount');
        }

        return view('livewire.family.care-schedule', [
            'visits' => $visits,
            'visitSections' => $visitSections,
            'recipientOptions' => $recipientOptions,
            'totalVisitCount' => $totalVisitCount,
            'hasMoreVisits' => $hasMoreVisits,
            ...$calendar,
        ]);
    }
}
