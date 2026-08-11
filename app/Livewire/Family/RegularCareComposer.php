<?php

namespace App\Livewire\Family;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\RegularCare\CarePlanService;
use App\Support\WeeklySchedule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class RegularCareComposer extends Component
{
    public CareRequest $requestItem;

    public ?CareRequestApplication $hiredApplication = null;

    public string $title = '';

    public array $scheduleDays = [];

    public string $scheduleStartTime = '';

    public string $scheduleEndTime = '';

    public array $scheduleSlots = [];

    public string $startsOn = '';

    public string $endsOn = '';

    public string $careNotes = '';

    public string $familyMessage = '';

    public array $billingSummary = [];

    public function mount(int $careRequest): void
    {
        $this->requestItem = CareRequest::query()
            ->with([
                'recipient',
                'tasks',
                'booking',
                'applications.caregiver.caregiverProfile',
            ])
            ->findOrFail($careRequest);

        abort_unless(app(FamilyAccountContext::class)->canAccessRecord(auth()->user(), $this->requestItem), 403);

        $service = app(CarePlanService::class);
        abort_unless($service->sourceIsEligible($this->requestItem, auth()->user()), 404);

        $this->hiredApplication = $service->hiredApplicationFor($this->requestItem);
        $defaults = $service->defaultsFromRequest($this->requestItem);

        $this->title = (string) $defaults['title'];
        $this->scheduleDays = array_map('strval', $defaults['schedule_days']);
        $this->scheduleStartTime = (string) $defaults['schedule_start_time'];
        $this->scheduleEndTime = (string) $defaults['schedule_end_time'];
        $this->scheduleSlots = collect($defaults['schedule_slots'] ?? [])->mapWithKeys(fn (array $slot): array => [
            (string) $slot['day'] => [
                'start_time' => substr((string) $slot['start_time'], 0, 5),
                'end_time' => substr((string) $slot['end_time'], 0, 5),
            ],
        ])->all();
        $this->startsOn = (string) $defaults['starts_on'];
        $this->careNotes = (string) ($defaults['care_notes'] ?? '');
        $this->familyMessage = (string) $defaults['family_message'];

        try {
            $this->billingSummary = $service->billingSummaryFor(auth()->user());
        } catch (PaymentException) {
            $this->billingSummary = ['ready' => false, 'customer_id' => null, 'card' => null];
        }
    }

    public function sendOffer(): void
    {
        $this->syncScheduleSlots();
        $rules = [
            'title' => ['required', 'string', 'min:4', 'max:120'],
            'scheduleDays' => ['required', 'array', 'min:1'],
            'scheduleDays.*' => ['integer', 'between:0,6'],
            'startsOn' => ['required', 'date', 'after_or_equal:today'],
            'endsOn' => ['nullable', 'date', 'after:startsOn'],
            'careNotes' => ['nullable', 'string', 'max:3000'],
            'familyMessage' => ['nullable', 'string', 'max:1000'],
        ];
        foreach ($this->normalizedDays() as $day) {
            $rules['scheduleSlots.'.$day.'.start_time'] = ['required', 'date_format:H:i'];
            $rules['scheduleSlots.'.$day.'.end_time'] = ['required', 'date_format:H:i', 'after:scheduleSlots.'.$day.'.start_time'];
        }
        $this->validate($rules);

        try {
            $plan = app(CarePlanService::class)->sendOfferFromRequest($this->requestItem, auth()->user(), [
                'title' => $this->title,
                'schedule_days' => $this->scheduleDays,
                'schedule_start_time' => $this->scheduleStartTime,
                'schedule_end_time' => $this->scheduleEndTime,
                'schedule_slots' => $this->normalizedScheduleSlots(),
                'starts_on' => $this->startsOn,
                'ends_on' => $this->endsOn ?: null,
                'care_notes' => $this->careNotes,
                'family_message' => $this->familyMessage,
            ]);
        } catch (PaymentException $exception) {
            session()->flash('status', $exception->userMessage);

            return;
        } catch (ValidationException $exception) {
            throw $exception;
        }

        session()->flash('status', 'Regular-care offer sent to '.$plan->caregiver?->name.'.');
        $this->redirect(route('family.care.show', $plan->id, false), navigate: true);
    }

    public function render(CarePlanService $plans)
    {
        return view('livewire.family.regular-care-composer', [
            'scheduleService' => $plans,
            'hiredApplication' => $this->hiredApplication,
            'platformRate' => $plans->hourlyRateForFamily(auth()->user()),
        ]);
    }

    public function updatedScheduleDays(): void
    {
        $this->syncScheduleSlots();
    }

    public function updatedScheduleStartTime(string $value): void
    {
        foreach (array_keys($this->scheduleSlots) as $day) {
            $this->scheduleSlots[$day]['start_time'] = $value;
        }
    }

    public function updatedScheduleEndTime(string $value): void
    {
        foreach (array_keys($this->scheduleSlots) as $day) {
            $this->scheduleSlots[$day]['end_time'] = $value;
        }
    }

    private function syncScheduleSlots(): void
    {
        $existing = $this->scheduleSlots;
        $template = collect($existing)->first(fn (mixed $slot): bool => is_array($slot) && filled($slot['start_time'] ?? null)) ?? [
            'start_time' => $this->scheduleStartTime,
            'end_time' => $this->scheduleEndTime,
        ];

        $this->scheduleSlots = collect($this->normalizedDays())->mapWithKeys(function (int $day) use ($existing, $template): array {
            $slot = $existing[(string) $day] ?? $existing[$day] ?? $template;
            if (! filled($slot['start_time'] ?? null) && filled($template['start_time'] ?? null)) {
                $slot = $template;
            }

            return [(string) $day => [
                'start_time' => substr((string) ($slot['start_time'] ?? ''), 0, 5),
                'end_time' => substr((string) ($slot['end_time'] ?? ''), 0, 5),
            ]];
        })->all();

        $first = WeeklySchedule::first($this->normalizedScheduleSlots());
        $this->scheduleStartTime = (string) ($first['start_time'] ?? '');
        $this->scheduleEndTime = (string) ($first['end_time'] ?? '');
    }

    private function normalizedDays(): array
    {
        return collect($this->scheduleDays)
            ->map(fn ($day): int => (int) $day)
            ->filter(fn (int $day): bool => $day >= 0 && $day <= 6)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function normalizedScheduleSlots(): array
    {
        return WeeklySchedule::normalize(
            $this->scheduleSlots,
            $this->normalizedDays(),
            $this->scheduleStartTime,
            $this->scheduleEndTime,
        );
    }
}
