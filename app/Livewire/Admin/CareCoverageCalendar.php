<?php

namespace App\Livewire\Admin;

use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Services\Analytics\CareCoverageCalendarBuilder;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class CareCoverageCalendar extends Component
{
    #[Url(except: '')]
    public string $month = '';

    #[Url(except: 'all')]
    public string $view = 'all';

    #[Url(as: 'family', except: 'all')]
    public string $familyAccount = 'all';

    #[Url(except: 'all')]
    public string $caregiver = 'all';

    #[Url(except: 'all')]
    public string $status = 'all';

    #[Url(except: '')]
    public string $q = '';

    /** @var list<string> */
    private array $allowedViews = ['all', 'shifts', 'open_requests'];

    /** @var list<string> */
    private array $allowedStatuses = [
        'all',
        CareRequest::STATUS_OPEN,
        CareBooking::STATUS_SCHEDULED,
        CareBooking::STATUS_IN_PROGRESS,
        CareBooking::STATUS_PAUSED,
        CareBooking::STATUS_COMPLETED,
        CareBooking::STATUS_REVIEWED,
        CareBooking::STATUS_DISPUTED,
        CareBooking::STATUS_CANCELLED,
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
        $this->normalizeFilters();
    }

    public function previousMonth(): void
    {
        $this->month = $this->calendarMonth()->subMonthNoOverflow()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->calendarMonth()->addMonthNoOverflow()->format('Y-m');
    }

    public function goToToday(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function clearFilters(): void
    {
        $this->view = 'all';
        $this->familyAccount = 'all';
        $this->caregiver = 'all';
        $this->status = 'all';
        $this->q = '';
    }

    public function render(CareCoverageCalendarBuilder $builder): View
    {
        $this->normalizeFilters();
        $month = $this->calendarMonth();
        $gridStart = $month->copy()->startOfMonth()->startOfWeek(CarbonInterface::SUNDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(CarbonInterface::SATURDAY);
        $calendar = $builder->build($month, $gridStart, $gridEnd, [
            'view' => $this->view,
            'family_account' => $this->familyAccount,
            'caregiver' => $this->caregiver,
            'status' => $this->status,
            'q' => $this->q,
        ]);

        return view('livewire.admin.care-coverage-calendar', [
            ...$calendar,
            'calendarMonth' => $month,
            'gridStart' => $gridStart,
            'gridEnd' => $gridEnd,
            'familyOptions' => $builder->familyOptions(),
            'caregiverOptions' => $builder->caregiverOptions(),
            'viewOptions' => [
                ['value' => 'all', 'label' => 'Shifts + open requests'],
                ['value' => 'shifts', 'label' => 'Confirmed shifts only'],
                ['value' => 'open_requests', 'label' => 'Open requests only'],
            ],
            'statusOptions' => [
                ['value' => 'all', 'label' => 'All statuses'],
                ['value' => CareRequest::STATUS_OPEN, 'label' => 'Open · unassigned'],
                ['value' => CareBooking::STATUS_SCHEDULED, 'label' => 'Scheduled'],
                ['value' => CareBooking::STATUS_IN_PROGRESS, 'label' => 'In progress'],
                ['value' => CareBooking::STATUS_PAUSED, 'label' => 'Paused'],
                ['value' => CareBooking::STATUS_COMPLETED, 'label' => 'Completed'],
                ['value' => CareBooking::STATUS_REVIEWED, 'label' => 'Reviewed'],
                ['value' => CareBooking::STATUS_DISPUTED, 'label' => 'Disputed'],
                ['value' => CareBooking::STATUS_CANCELLED, 'label' => 'Cancelled'],
            ],
        ]);
    }

    private function normalizeFilters(): void
    {
        $this->view = in_array($this->view, $this->allowedViews, true) ? $this->view : 'all';
        $this->status = in_array($this->status, $this->allowedStatuses, true) ? $this->status : 'all';
        $this->familyAccount = $this->validIdentifierFilter($this->familyAccount);
        $this->caregiver = $this->validIdentifierFilter($this->caregiver);

        if ($this->month === '' || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month) !== 1) {
            $this->month = now()->format('Y-m');
        }
    }

    private function validIdentifierFilter(string $value): string
    {
        return ctype_digit($value) && (int) $value > 0 ? $value : 'all';
    }

    private function calendarMonth(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $this->month.'-01')->startOfMonth();
    }
}
