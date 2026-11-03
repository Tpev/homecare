<?php

namespace App\Livewire\Admin;

use App\Models\AdminRecordedVisit;
use App\Models\CareRequest;
use App\Models\CareTask;
use App\Models\FamilyAccount;
use App\Models\User;
use App\Services\Booking\AdminRecordedVisitService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class RecordPastVisit extends Component
{
    public array $form = [];

    public string $familySearch = '';

    public string $caregiverSearch = '';

    public bool $acknowledged = false;

    #[Locked]
    public string $requestToken;

    #[Locked]
    public array $review = [];

    #[Locked]
    public ?int $visitId = null;

    public function mount(?int $recordedVisit = null): void
    {
        $this->authorizeAdmin();
        $this->requestToken = (string) Str::uuid();
        $this->form = [
            'family_account_id' => '', 'caregiver_user_id' => '', 'source_request_id' => null,
            'date' => now()->subDay()->format('Y-m-d'), 'start_time' => '09:00',
            'timezone' => config('app.timezone', 'America/New_York'), 'start_occurrence' => 'first',
            'duration_minutes' => 60, 'break_minutes' => 0, 'recipient_name' => '', 'relationship' => '',
            'title' => 'Completed care visit', 'scope_of_work' => '', 'address_line1' => '', 'address_line2' => '',
            'city' => '', 'state' => '', 'zip' => '', 'task_ids' => [], 'reason' => '',
        ];
        if ($recordedVisit !== null) {
            $this->visitId = AdminRecordedVisit::findOrFail($recordedVisit)->id;
        }
    }

    public function updatedForm(): void
    {
        $this->authorizeAdmin();
        $this->clearReview();
    }

    public function selectFamily(int $id): void
    {
        $this->authorizeAdmin();
        $family = FamilyAccount::where('status', 'active')->with(['owner', 'onboarding', 'defaultCareRecipientProfile'])->findOrFail($id);
        abort_unless($family->owner?->role === 'family', 422);
        $this->form['family_account_id'] = $family->id;
        $this->form['source_request_id'] = null;
        $this->form['task_ids'] = [];
        $snapshot = $family->onboarding?->submitted_snapshot ?? [];
        $profile = $family->defaultCareRecipientProfile;
        $this->form['recipient_name'] = $profile?->full_name ?: ($snapshot['recipient_name'] ?? (($snapshot['care_for'] ?? '') === 'me' ? $family->owner->name : ''));
        $this->form['relationship'] = $profile?->relationship_to_family ?: ($snapshot['relationship'] ?? (($snapshot['care_for'] ?? '') === 'me' ? 'Self' : ''));
        foreach (['address_line1', 'address_line2', 'city', 'state', 'zip'] as $field) {
            $this->form[$field] = $snapshot[$field] ?? '';
        }
        $this->form['title'] = 'Completed care visit';
        $this->form['scope_of_work'] = '';
        $latest = CareRequest::where('family_account_id', $id)->whereHas('recipient')->latest('id')->first();
        if ($latest) {
            $this->form['source_request_id'] = $latest->id;
            $this->loadSourceRequest();
        }
        $this->familySearch = '';
        $this->clearReview();
    }

    public function selectCaregiver(int $id): void
    {
        $this->authorizeAdmin();
        $caregiver = User::where('role', 'caregiver')->whereHas('caregiverProfile', fn ($q) => $q->where('status', 'active'))->findOrFail($id);
        $this->form['caregiver_user_id'] = $caregiver->id;
        $this->caregiverSearch = '';
        $this->clearReview();
    }

    public function loadSourceRequest(): void
    {
        $this->authorizeAdmin();
        $this->clearReview();
        if (! $this->form['source_request_id']) {
            return;
        }
        $source = CareRequest::where('family_account_id', $this->form['family_account_id'])->with(['recipient', 'tasks'])->findOrFail($this->form['source_request_id']);
        foreach (['title', 'scope_of_work', 'address_line1', 'address_line2', 'city', 'state', 'zip'] as $field) {
            $this->form[$field] = $source->$field ?? '';
        }
        $this->form['recipient_name'] = $source->recipient?->full_name ?? '';
        $this->form['relationship'] = $source->recipient?->relationship_to_family ?: ($source->recipient?->recipient_is_requester ? 'Self' : '');
        $this->form['task_ids'] = $source->tasks->pluck('id')->all();
    }

    public function reviewVisit(AdminRecordedVisitService $service): void
    {
        $this->authorizeAdmin();
        $this->clearReview();
        $this->review = $service->preview(auth()->user(), $this->form);
    }

    public function editVisit(): void
    {
        $this->authorizeAdmin();
        $this->clearReview();
    }

    public function createVisit(AdminRecordedVisitService $service): void
    {
        $this->authorizeAdmin();
        if ($this->visitId !== null) {
            $this->openSavedVisit();

            return;
        }
        $this->validate(['acknowledged' => ['accepted']]);
        if (empty($this->review['fingerprint'])) {
            $this->addError('visit', 'Review the visit and charge before creating it.');

            return;
        }
        $visit = $service->create(auth()->user(), $this->form, $this->requestToken, $this->review['fingerprint']);
        $this->visitId = $visit->id;
        $service->processPayment(auth()->user(), $visit);
        $this->openSavedVisit();
    }

    public function retryPayment(AdminRecordedVisitService $service): void
    {
        $this->authorizeAdmin();
        $service->processPayment(auth()->user(), AdminRecordedVisit::findOrFail($this->visitId));
    }

    public function refreshPayment(): void
    {
        $this->authorizeAdmin();
        $this->resetValidation();
    }

    public function render(): View
    {
        $this->authorizeAdmin();
        $familyTerm = mb_substr(trim($this->familySearch), 0, 100);
        $caregiverTerm = mb_substr(trim($this->caregiverSearch), 0, 100);
        $family = FamilyAccount::with('owner')->find($this->form['family_account_id'] ?? null);
        $caregiver = User::find($this->form['caregiver_user_id'] ?? null);
        $visit = $this->visitId ? AdminRecordedVisit::with(['booking.payment', 'booking.family', 'booking.caregiver', 'creator'])->findOrFail($this->visitId) : null;
        $payment = $visit?->booking?->payment;
        $paid = $payment && in_array($payment->status, ['captured', 'transferred', 'transfer_failed'], true)
            && (int) $payment->amount_captured_cents === (int) $visit->quote['total_charge_cents'] && ! $payment->overage_pending_cents && ! $payment->amount_refunded_cents;
        $settled = $paid && $payment->status === 'transferred' && $payment->fee_finalization_status === 'finalized'
            && $payment->stripe_transfer_id && $visit->booking->family_confirmed_at;

        return view('livewire.admin.record-past-visit', [
            'family' => $family, 'caregiver' => $caregiver, 'visit' => $visit, 'payment' => $payment, 'paid' => $paid, 'settled' => $settled,
            'families' => ! $visit && (! $family || $familyTerm !== '') ? FamilyAccount::with('owner')->where('status', 'active')
                ->whereHas('owner', fn ($q) => $q->where('role', 'family')->where(fn ($w) => $w->where('name', 'like', '%'.$familyTerm.'%')->orWhere('email', 'like', '%'.$familyTerm.'%')))
                ->latest('id')->limit(8)->get() : collect(),
            'caregivers' => ! $visit && (! $caregiver || $caregiverTerm !== '') ? User::where('role', 'caregiver')
                ->whereHas('caregiverProfile', fn ($q) => $q->where('status', 'active'))
                ->where(fn ($q) => $q->where('name', 'like', '%'.$caregiverTerm.'%')->orWhere('email', 'like', '%'.$caregiverTerm.'%'))
                ->orderBy('name')->limit(8)->get(['id', 'name', 'email']) : collect(),
            'sources' => $family && ! $visit ? CareRequest::where('family_account_id', $family->id)->with('recipient')->latest('id')->limit(30)->get() : collect(),
            'tasks' => ! $visit ? CareTask::orderBy('name')->get() : collect(),
            'recentVisits' => AdminRecordedVisit::with(['booking.family', 'booking.caregiver', 'booking.payment'])->latest('id')->limit(8)->get(),
            'timezones' => ['America/New_York' => 'Eastern · Raleigh / New York', 'America/Chicago' => 'Central · Chicago',
                'America/Denver' => 'Mountain · Denver', 'America/Phoenix' => 'Arizona · Phoenix', 'America/Los_Angeles' => 'Pacific · Los Angeles',
                'America/Anchorage' => 'Alaska · Anchorage', 'Pacific/Honolulu' => 'Hawaii · Honolulu', 'UTC' => 'UTC'],
        ]);
    }

    private function clearReview(): void
    {
        $this->review = [];
        $this->acknowledged = false;
        $this->resetValidation();
    }

    private function openSavedVisit(): void
    {
        $this->redirect(route('admin.requests.recorded-visit', $this->visitId), navigate: true);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->fresh()?->isAdministrator(), 403);
    }
}
