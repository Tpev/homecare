<?php

namespace App\Livewire\Family;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Services\RegularCare\CarePlanService;
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

        abort_unless((int) $this->requestItem->family_user_id === (int) auth()->id(), 403);

        $service = app(CarePlanService::class);
        abort_unless($service->sourceIsEligible($this->requestItem, auth()->user()), 404);

        $this->hiredApplication = $service->hiredApplicationFor($this->requestItem);
        $defaults = $service->defaultsFromRequest($this->requestItem);

        $this->title = (string) $defaults['title'];
        $this->scheduleDays = array_map('strval', $defaults['schedule_days']);
        $this->scheduleStartTime = (string) $defaults['schedule_start_time'];
        $this->scheduleEndTime = (string) $defaults['schedule_end_time'];
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
        $this->validate([
            'title' => ['required', 'string', 'min:4', 'max:120'],
            'scheduleDays' => ['required', 'array', 'min:1'],
            'scheduleDays.*' => ['integer', 'between:0,6'],
            'scheduleStartTime' => ['required', 'date_format:H:i'],
            'scheduleEndTime' => ['required', 'date_format:H:i'],
            'startsOn' => ['required', 'date', 'after_or_equal:today'],
            'endsOn' => ['nullable', 'date', 'after:startsOn'],
            'careNotes' => ['nullable', 'string', 'max:3000'],
            'familyMessage' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $plan = app(CarePlanService::class)->sendOfferFromRequest($this->requestItem, auth()->user(), [
                'title' => $this->title,
                'schedule_days' => $this->scheduleDays,
                'schedule_start_time' => $this->scheduleStartTime,
                'schedule_end_time' => $this->scheduleEndTime,
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
            'platformRate' => $plans->platformHourlyRate(),
        ]);
    }
}
