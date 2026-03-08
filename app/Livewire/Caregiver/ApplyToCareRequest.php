<?php

namespace App\Livewire\Caregiver;

use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ApplyToCareRequest extends Component
{
    public CareRequest $requestItem;
    public ?float $proposed_rate = null;
    public string $cover_note = '';
    public ?CareRequestApplication $existingApplication = null;

    public function mount(int $careRequest): void
    {
        $this->requestItem = CareRequest::query()
            ->with(['recipient', 'tasks', 'thirdPartyContact'])
            ->where('status', CareRequest::STATUS_OPEN)
            ->findOrFail($careRequest);

        abort_unless(auth()->user()->can('apply', $this->requestItem), 403);

        $this->existingApplication = CareRequestApplication::query()
            ->where('care_request_id', $this->requestItem->id)
            ->where('caregiver_user_id', auth()->id())
            ->with(['conversation:id,care_request_application_id,care_request_id,caregiver_user_id'])
            ->first();

        if ($this->existingApplication) {
            $this->proposed_rate = $this->existingApplication->proposed_rate ? (float) $this->existingApplication->proposed_rate : null;
            $this->cover_note = (string) $this->existingApplication->cover_note;
        }
    }

    public function submit(): void
    {
        $this->validate([
            'proposed_rate' => ['required', 'numeric', 'min:15', 'max:200'],
            'cover_note' => ['required', 'string', 'min:40', 'max:2500'],
        ]);

        $existing = CareRequestApplication::query()
            ->where('care_request_id', $this->requestItem->id)
            ->where('caregiver_user_id', auth()->id())
            ->first();

        $status = $existing && in_array($existing->status, [
            CareRequestApplication::STATUS_SHORTLISTED,
            CareRequestApplication::STATUS_HIRED,
        ], true)
            ? $existing->status
            : CareRequestApplication::STATUS_APPLIED;

        $this->existingApplication = CareRequestApplication::query()->updateOrCreate(
            [
                'care_request_id' => $this->requestItem->id,
                'caregiver_user_id' => auth()->id(),
            ],
            [
                'status' => $status,
                'proposed_rate' => $this->proposed_rate,
                'cover_note' => trim($this->cover_note),
            ],
        );

        session()->flash('status', 'Application sent to family.');
        $this->redirect(route('care-requests.index', absolute: false), navigate: true);
    }

    public function openChat(): void
    {
        $application = CareRequestApplication::query()
            ->where('care_request_id', $this->requestItem->id)
            ->where('caregiver_user_id', auth()->id())
            ->with(['careRequest', 'conversation'])
            ->firstOrFail();

        if (! in_array($application->status, [
            CareRequestApplication::STATUS_SHORTLISTED,
            CareRequestApplication::STATUS_HIRED,
        ], true)) {
            session()->flash('status', 'Chat is available once you are shortlisted or hired.');
            return;
        }

        $conversation = CareRequestConversation::findOrCreateForApplication($application, auth()->id());
        $this->redirect(route('messages.show', $conversation->id, false), navigate: true);
    }

    public function render()
    {
        return view('livewire.caregiver.apply-to-care-request');
    }
}
