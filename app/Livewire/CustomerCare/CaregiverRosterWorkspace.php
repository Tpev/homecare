<?php

namespace App\Livewire\CustomerCare;

use App\Models\CaregiverRoster;
use App\Models\CareRequest;
use App\Services\CustomerCare\CareRosterAccess;
use App\Services\CustomerCare\CareRosterService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CaregiverRosterWorkspace extends Component
{
    public string $requestId = '';

    public string $search = '';

    public string $context = '';

    public string $urgency = 'normal';

    public array $drafts = [];

    #[Locked]
    public ?int $rosterId = null;

    #[Locked]
    public ?string $reviewToken = null;

    public function boot(): void
    {
        CareRosterAccess::authorize(auth()->user());
    }

    public function mount(): void
    {
        if (request()->filled('request')) {
            $request = CareRequest::findOrFail((int) request('request'));
            CareRosterAccess::authorize(auth()->user(), $request);
            $this->requestId = (string) $request->id;
        }
    }

    public function updatedRequestId(): void
    {
        $this->reset('rosterId', 'reviewToken', 'drafts');
    }

    public function generate(CareRosterService $service): void
    {
        $this->validate(['requestId' => ['required', 'integer'], 'context' => ['string', 'max:6000'], 'urgency' => ['in:normal,soon,urgent']]);
        $request = CareRequest::findOrFail($this->requestId);
        CareRosterAccess::authorize(auth()->user(), $request);
        $key = 'care-roster:generate:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('roster', 'Please wait a few minutes before generating another roster.');

            return;
        }
        RateLimiter::hit($key, 600);
        $roster = $service->start(auth()->user(), $request, $this->context, $this->urgency);
        $this->reset('drafts', 'reviewToken');
        $this->rosterId = $roster->id;
        $this->refreshRoster();
    }

    public function loadRoster(int $id): void
    {
        $roster = CaregiverRoster::findOrFail($id);
        CareRosterAccess::authorize(auth()->user(), $roster->careRequest);
        $this->requestId = (string) $roster->care_request_id;
        $this->context = $roster->context ?? '';
        $this->urgency = $roster->urgency;
        $this->reset('drafts', 'reviewToken');
        $this->rosterId = $id;
        $this->refreshRoster();
    }

    public function refreshRoster(): void
    {
        if (! $this->rosterId) {
            return;
        }
        $roster = $this->roster();
        if ($roster->status === 'ready' && ! $this->drafts) {
            foreach ($roster->entries as $entry) {
                $this->drafts[$entry->id] = ['selected' => false, 'send_sms' => false,
                    'invitation_body' => $entry->invitation_body, 'sms_body' => $entry->sms_body];
            }
        }
    }

    public function review(CareRosterService $service): void
    {
        $this->resetValidation();
        $selected = [];
        foreach ($this->drafts as $id => $draft) {
            if (! empty($draft['selected'])) {
                $selected[] = ['id' => $id, 'send_sms' => $draft['send_sms'] ?? false,
                    'invitation_body' => $draft['invitation_body'] ?? '', 'sms_body' => $draft['sms_body'] ?? ''];
            }
        }
        $this->reviewToken = $service->prepare(auth()->user(), $this->roster(), $selected);
    }

    public function editPreview(): void
    {
        $roster = $this->roster();
        if (! $roster->approved_at) {
            CaregiverRoster::whereKey($roster->id)->where('review_token', $this->reviewToken)
                ->where('reviewed_by_user_id', auth()->id())->update(['review_token' => null]);
        }
        $this->reviewToken = null;
    }

    public function approve(CareRosterService $service): void
    {
        $service->approve(auth()->user(), $this->roster(), $this->reviewToken ?? '');
        $this->reviewToken = null;
        $this->reset('drafts');
    }

    private function roster(): CaregiverRoster
    {
        $roster = CaregiverRoster::with(['careRequest.family', 'entries.caregiver', 'entries.invitation', 'entries.smsMessage'])->findOrFail($this->rosterId);
        CareRosterAccess::authorize(auth()->user(), $roster->careRequest);
        abort_unless((string) $roster->care_request_id === $this->requestId, 403);

        return $roster;
    }

    public function render()
    {
        CareRosterAccess::authorize(auth()->user());
        $roster = $this->rosterId ? $this->roster() : null;
        $search = mb_substr($this->search, 0, 120);
        $requests = CareRequest::with('family')->where('staff_managed', true)->whereNull('staff_archived_at')->where('status', 'open')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', '%'.$search.'%')
                ->orWhereHas('family', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
                ->orWhere('id', ctype_digit($search) ? (int) $search : 0)))
            ->orderByDesc('id')->limit(50)->get();
        $selectedRequest = $this->requestId ? CareRequest::find($this->requestId) : null;
        if ($selectedRequest) {
            CareRosterAccess::authorize(auth()->user(), $selectedRequest);
            if (! $requests->contains('id', $selectedRequest->id)) {
                $requests->prepend($selectedRequest->load('family'));
            }
        }

        return view('livewire.customer-care.caregiver-roster-workspace', [
            'roster' => $roster, 'requests' => $requests,
            // History is navigation only: never sort or hydrate the large AI evidence packet.
            'history' => $selectedRequest ? CaregiverRoster::select(['id', 'status', 'created_at'])
                ->where('care_request_id', $selectedRequest->id)
                ->orderByDesc('created_at')->orderByDesc('id')->limit(10)->get() : collect(),
            'applications' => $selectedRequest ? $selectedRequest->applications()->get()->keyBy('caregiver_user_id') : collect(),
            'invitations' => $selectedRequest ? $selectedRequest->invitations()->get()->keyBy('caregiver_user_id') : collect(),
        ])->layout('layouts.app');
    }
}
