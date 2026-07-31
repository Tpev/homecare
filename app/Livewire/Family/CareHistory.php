<?php

namespace App\Livewire\Family;

use App\Services\Family\FamilyCareHistoryService;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class CareHistory extends Component
{
    use WithPagination;

    #[Url(as: 'range')]
    public string $range = 'all';

    #[Url(as: 'from')]
    public string $from = '';

    #[Url(as: 'to')]
    public string $to = '';

    #[Url(as: 'recipient')]
    public string $recipient = '';

    #[Url(as: 'caregiver')]
    public string $caregiver = '';

    #[Url(as: 'plan')]
    public string $plan = '';

    #[Url(as: 'type')]
    public string $careType = 'all';

    #[Url(as: 'visit')]
    public string $visitStatus = 'all';

    #[Url(as: 'payment')]
    public string $paymentStatus = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    public array $rangeOptions = [
        ['label' => 'All time', 'value' => 'all'],
        ['label' => 'Past 30 days', 'value' => '30_days'],
        ['label' => 'Past 3 months', 'value' => '3_months'],
        ['label' => 'Past year', 'value' => 'year'],
        ['label' => 'Custom dates', 'value' => 'custom'],
    ];

    public array $careTypeOptions = [
        ['label' => 'All care types', 'value' => 'all'],
        ['label' => 'One-time', 'value' => 'one_time'],
        ['label' => 'Regular care', 'value' => 'regular'],
        ['label' => 'Extra visit', 'value' => 'extra'],
    ];

    public array $visitStatusOptions = [
        ['label' => 'All visit statuses', 'value' => 'all'],
        ['label' => 'Completed', 'value' => 'completed'],
        ['label' => 'Awaiting hours approval', 'value' => 'awaiting_approval'],
        ['label' => 'Check-in missing', 'value' => 'check_in_missing'],
        ['label' => 'Disputed', 'value' => 'disputed'],
        ['label' => 'Cancelled', 'value' => 'cancelled'],
        ['label' => 'Caregiver no-show', 'value' => 'no_show'],
        ['label' => 'Adjusted', 'value' => 'adjusted'],
    ];

    public array $paymentStatusOptions = [
        ['label' => 'All payment statuses', 'value' => 'all'],
        ['label' => 'Any billed care', 'value' => 'charged'],
        ['label' => 'Paid', 'value' => 'paid'],
        ['label' => 'Card authorized', 'value' => 'authorized'],
        ['label' => 'Payment issue', 'value' => 'payment_issue'],
        ['label' => 'Partially refunded', 'value' => 'partially_refunded'],
        ['label' => 'Refunded', 'value' => 'refunded'],
        ['label' => 'Not charged', 'value' => 'not_charged'],
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()?->role === 'family', 403);
        $this->range = (string) request()->query('range', $this->range);
        $this->from = (string) request()->query('from', $this->from);
        $this->to = (string) request()->query('to', $this->to);
        $this->recipient = (string) request()->query('recipient', $this->recipient);
        $this->caregiver = (string) request()->query('caregiver', $this->caregiver);
        $this->plan = (string) request()->query('plan', $this->plan);
        $this->careType = (string) request()->query('type', $this->careType);
        $this->visitStatus = (string) request()->query('visit', $this->visitStatus);
        $this->paymentStatus = (string) request()->query('payment', $this->paymentStatus);
        $this->search = (string) request()->query('q', $this->search);
        $this->normalizePublicFilters();
    }

    public function updated(string $property): void
    {
        if (in_array($property, [
            'range', 'from', 'to', 'recipient', 'caregiver', 'plan', 'careType', 'visitStatus', 'paymentStatus', 'search',
        ], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['from', 'to', 'recipient', 'caregiver', 'plan', 'search']);
        $this->range = 'all';
        $this->careType = 'all';
        $this->visitStatus = 'all';
        $this->paymentStatus = 'all';
        $this->resetPage();
    }

    public function render(FamilyCareHistoryService $history)
    {
        $family = auth()->user();
        $filters = $this->filters();
        $query = $history->query($family, $filters);
        /** @var LengthAwarePaginator $items */
        $items = $query->paginate(12);
        $items->through(fn ($booking): array => $history->present($booking));
        $options = $history->filterOptions($family);

        return view('livewire.family.care-history', [
            'historyItems' => $items,
            'summary' => $history->summary($family, $filters),
            'caregiverOptions' => collect([['label' => 'All caregivers', 'value' => '']])->concat($options['caregivers'])->all(),
            'recipientOptions' => collect([['label' => 'All recipients', 'value' => '']])->concat($options['recipients'])->all(),
            'planOptions' => collect([['label' => 'All regular-care plans', 'value' => '']])->concat($options['plans'])->all(),
            'activeFilterCount' => $this->activeFilterCount(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function filters(): array
    {
        return [
            'range' => $this->allowed($this->range, array_column($this->rangeOptions, 'value'), 'all'),
            'from' => $this->validDate($this->from),
            'to' => $this->validDate($this->to),
            'recipient' => mb_substr(trim($this->recipient), 0, 160),
            'caregiver' => $this->positiveInteger($this->caregiver),
            'plan' => $this->positiveInteger($this->plan),
            'type' => $this->allowed($this->careType, array_column($this->careTypeOptions, 'value'), 'all'),
            'visit' => $this->allowed($this->visitStatus, array_column($this->visitStatusOptions, 'value'), 'all'),
            'payment' => $this->allowed($this->paymentStatus, array_column($this->paymentStatusOptions, 'value'), 'all'),
            'search' => mb_substr(trim($this->search), 0, 120),
        ];
    }

    private function normalizePublicFilters(): void
    {
        $filters = $this->filters();
        $this->range = $filters['range'];
        $this->from = $filters['from'];
        $this->to = $filters['to'];
        $this->recipient = $filters['recipient'];
        $this->caregiver = $filters['caregiver'];
        $this->plan = $filters['plan'];
        $this->careType = $filters['type'];
        $this->visitStatus = $filters['visit'];
        $this->paymentStatus = $filters['payment'];
        $this->search = $filters['search'];
    }

    private function activeFilterCount(): int
    {
        return collect($this->filters())->filter(fn (string $value, string $key): bool => match ($key) {
            'range', 'type', 'visit', 'payment' => $value !== 'all',
            default => $value !== '',
        })->count();
    }

    /** @param array<int, string> $allowed */
    private function allowed(string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function positiveInteger(string $value): string
    {
        return ctype_digit($value) && (int) $value > 0 ? $value : '';
    }

    private function validDate(string $value): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }
}
