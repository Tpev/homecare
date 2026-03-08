<?php

namespace App\Livewire\Family;

use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ManageCareRequest extends Component
{
    public CareRequest $requestItem;
    public string $applicationStatus = 'all';
    public string $applicationSort = 'latest';

    public array $applicationStatusOptions = [
        ['label' => 'All applicants', 'value' => 'all'],
        ['label' => 'Applied', 'value' => CareRequestApplication::STATUS_APPLIED],
        ['label' => 'Shortlisted', 'value' => CareRequestApplication::STATUS_SHORTLISTED],
        ['label' => 'Hired', 'value' => CareRequestApplication::STATUS_HIRED],
        ['label' => 'Rejected', 'value' => CareRequestApplication::STATUS_REJECTED],
        ['label' => 'Not selected', 'value' => CareRequestApplication::STATUS_NOT_SELECTED],
        ['label' => 'Withdrawn', 'value' => CareRequestApplication::STATUS_WITHDRAWN],
    ];

    public function mount(int $careRequest): void
    {
        $this->requestItem = CareRequest::query()
            ->with([
                'family:id,name,email,phone',
                'recipient',
                'thirdPartyContact',
                'tasks',
                'applications' => fn ($query) => $query->with([
                    'caregiver:id,name,email,phone,city,state',
                    'caregiver.caregiverProfile:id,user_id,status,average_rating,reviews_count,hourly_rate',
                    'conversation:id,care_request_application_id,care_request_id,caregiver_user_id',
                ]),
            ])
            ->findOrFail($careRequest);

        abort_unless(auth()->user()->can('manageApplicants', $this->requestItem), 403);
    }

    public function shortlist(int $applicationId): void
    {
        $application = $this->findOwnedApplication($applicationId);

        if (! in_array($application->status, [CareRequestApplication::STATUS_APPLIED, CareRequestApplication::STATUS_SHORTLISTED], true)) {
            return;
        }

        $application->update(['status' => CareRequestApplication::STATUS_SHORTLISTED]);
        $this->refreshRequestItem();
        session()->flash('status', 'Applicant shortlisted for follow-up.');
    }

    public function reject(int $applicationId): void
    {
        $application = $this->findOwnedApplication($applicationId);

        if (in_array($application->status, [CareRequestApplication::STATUS_HIRED, CareRequestApplication::STATUS_WITHDRAWN], true)) {
            return;
        }

        $application->update(['status' => CareRequestApplication::STATUS_REJECTED]);
        $this->refreshRequestItem();
        session()->flash('status', 'Applicant rejected.');
    }

    public function hire(int $applicationId): void
    {
        if ($this->requestItem->status !== CareRequest::STATUS_OPEN) {
            return;
        }

        $application = $this->findOwnedApplication($applicationId);

        DB::transaction(function () use ($application) {
            $application->update(['status' => CareRequestApplication::STATUS_HIRED]);

            CareRequestConversation::findOrCreateForApplication($application->loadMissing('careRequest'), auth()->id());

            $this->requestItem->applications()
                ->where('id', '!=', $application->id)
                ->whereIn('status', [
                    CareRequestApplication::STATUS_APPLIED,
                    CareRequestApplication::STATUS_SHORTLISTED,
                ])
                ->update(['status' => CareRequestApplication::STATUS_NOT_SELECTED]);

            $this->requestItem->update(['status' => CareRequest::STATUS_FILLED]);
        });

        $this->refreshRequestItem();
        session()->flash('status', 'Care request filled and caregiver hired.');
    }

    public function startConversation(int $applicationId): void
    {
        $application = $this->findOwnedApplication($applicationId);
        $application->loadMissing('careRequest');

        if ((int) $application->careRequest->family_user_id !== (int) auth()->id()
            || ! in_array($application->status, [
                CareRequestApplication::STATUS_SHORTLISTED,
                CareRequestApplication::STATUS_HIRED,
            ], true)) {
            session()->flash('status', 'You can chat after shortlisting this applicant.');
            return;
        }

        $conversation = CareRequestConversation::findOrCreateForApplication($application, auth()->id());
        $this->redirect(route('messages.show', $conversation->id, false), navigate: true);
    }

    private function findOwnedApplication(int $applicationId): CareRequestApplication
    {
        return CareRequestApplication::query()
            ->where('care_request_id', $this->requestItem->id)
            ->whereKey($applicationId)
            ->firstOrFail();
    }

    private function refreshRequestItem(): void
    {
        $this->requestItem = $this->requestItem->fresh([
            'family:id,name,email,phone',
            'recipient',
            'thirdPartyContact',
            'tasks',
            'applications' => fn ($query) => $query->with([
                'caregiver:id,name,email,phone,city,state',
                'caregiver.caregiverProfile:id,user_id,status,average_rating,reviews_count,hourly_rate',
                'conversation:id,care_request_application_id,care_request_id,caregiver_user_id',
            ]),
        ]);
    }

    public function getVisibleApplicationsProperty()
    {
        $applications = $this->requestItem->applications;

        if ($this->applicationStatus !== 'all') {
            $applications = $applications->where('status', $this->applicationStatus);
        }

        return match ($this->applicationSort) {
            'oldest' => $applications->sortBy('created_at')->values(),
            'rate_high' => $applications->sortByDesc('proposed_rate')->values(),
            'rate_low' => $applications->sortBy('proposed_rate')->values(),
            default => $applications->sortByDesc('created_at')->values(),
        };
    }

    public function render()
    {
        return view('livewire.family.manage-care-request');
    }
}
