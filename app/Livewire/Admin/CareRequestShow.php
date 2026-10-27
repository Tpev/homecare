<?php

namespace App\Livewire\Admin;

use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\CareRequestRecruitment;
use App\Services\Booking\BookingTrustService;
use App\Services\CareRecipientProfiles\CareRecipientProfilePresenter;
use App\Services\Marketplace\CareRequestRecruitmentService;
use App\Support\AdminCareRequestSchedule;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class CareRequestShow extends Component
{
    use WithPagination;

    public CareRequest $careRequest;

    public string $checkInOverrideReason = '';

    public bool $showRecruitmentPanel = false;

    public string $recruitmentSearch = '';

    public array $selectedRecruitmentCaregivers = [];

    public function mount(CareRequest $careRequest): void
    {
        $this->authorizeAdmin();
        $this->careRequest = $this->loadRequest($careRequest->id);
    }

    public function forceRequestStatus(string $status): void
    {
        $this->authorizeAdmin();
        if (! in_array($status, [
            CareRequest::STATUS_DRAFT,
            CareRequest::STATUS_OPEN,
            CareRequest::STATUS_FILLED,
            CareRequest::STATUS_CANCELLED,
            CareRequest::STATUS_EXPIRED,
        ], true)) {
            return;
        }

        $this->careRequest->update(['status' => $status]);
        $this->refreshRequest();
        session()->flash('status', 'Request status updated to '.strtoupper($status).'.');
    }

    public function forceBookingStatus(string $status): void
    {
        $this->authorizeAdmin();
        if (! in_array($status, [
            CareBooking::STATUS_SCHEDULED,
            CareBooking::STATUS_IN_PROGRESS,
            CareBooking::STATUS_PAUSED,
            CareBooking::STATUS_COMPLETED,
            CareBooking::STATUS_DISPUTED,
            CareBooking::STATUS_REVIEWED,
            CareBooking::STATUS_CANCELLED,
        ], true)) {
            return;
        }

        $booking = $this->careRequest->booking;
        if (! $booking) {
            $this->addError('booking', 'No booking exists for this request.');

            return;
        }

        $payload = ['status' => $status];
        if ($status === CareBooking::STATUS_COMPLETED && ! $booking->completed_at) {
            $payload['completed_at'] = now();
        }
        if ($status === CareBooking::STATUS_CANCELLED && ! $booking->cancelled_at) {
            $payload['cancelled_at'] = now();
            $payload['cancelled_by_user_id'] = auth()->id();
            $payload['cancellation_reason'] = 'Admin override from request detail panel.';
        }

        $booking->update($payload);
        $this->refreshRequest();
        session()->flash('status', 'Booking status updated to '.strtoupper($status).'.');
    }

    public function deleteRequest(): void
    {
        $this->authorizeAdmin();
        $id = $this->careRequest->id;

        try {
            $this->careRequest->delete();
        } catch (QueryException) {
            $this->addError('delete', 'Could not delete this request because related records are protected.');

            return;
        }

        session()->flash('status', 'Request #'.$id.' deleted.');
        $this->redirect(route('admin.requests.index', absolute: false), navigate: true);
    }

    public function allowRegularCareCheckIn(BookingTrustService $trust): void
    {
        $this->authorizeAdmin();
        $this->validate(['checkInOverrideReason' => ['required', 'string', 'min:8', 'max:1000']]);
        $booking = $this->careRequest->booking;
        if (! $booking?->care_plan_id) {
            $this->addError('checkInOverrideReason', 'This is not a recurring care visit.');

            return;
        }

        $booking->forceFill([
            'check_in_override_at' => now(),
            'check_in_override_by_user_id' => auth()->id(),
            'check_in_override_reason' => trim($this->checkInOverrideReason),
        ])->save();
        $trust->recordEvent($booking, auth()->id(), 'admin', 'regular_care_check_in_override', [
            'reason' => trim($this->checkInOverrideReason),
        ]);
        $this->checkInOverrideReason = '';
        $this->refreshRequest();
        session()->flash('status', 'Check-in override recorded for this visit.');
    }

    public function openRecruitmentPanel(): void
    {
        $this->authorizeAdmin();
        $this->refreshRequest();
        $this->showRecruitmentPanel = true;
    }

    public function closeRecruitmentPanel(): void
    {
        $this->authorizeAdmin();
        $this->showRecruitmentPanel = false;
    }

    public function updatedRecruitmentSearch(): void
    {
        $this->authorizeAdmin();
        $this->resetPage('recruitmentPage');
    }

    public function selectRecruitmentPage(CareRequestRecruitmentService $recruitment): void
    {
        $this->authorizeAdmin();
        $this->validateRecruitmentSelection();
        $this->refreshRequest();

        $ids = $this->recruitmentCandidates($recruitment)
            ->paginate(25, ['users.*'], 'recruitmentPage')
            ->getCollection()->pluck('id');

        $this->selectedRecruitmentCaregivers = collect($this->selectedRecruitmentCaregivers)
            ->merge($ids)->map(fn ($id) => (string) $id)->unique()->take(CareRequestRecruitmentService::BATCH_LIMIT)->values()->all();
    }

    public function selectMatchingRecruitmentCaregivers(CareRequestRecruitmentService $recruitment): void
    {
        $this->authorizeAdmin();
        $this->refreshRequest();
        $this->resetValidation('selectedRecruitmentCaregivers');
        $this->selectedRecruitmentCaregivers = $this->recruitmentCandidates($recruitment)
            ->limit(CareRequestRecruitmentService::BATCH_LIMIT)->pluck('users.id')->map(fn ($id) => (string) $id)->all();
    }

    public function clearRecruitmentSelection(): void
    {
        $this->authorizeAdmin();
        $this->selectedRecruitmentCaregivers = [];
        $this->resetValidation('selectedRecruitmentCaregivers');
    }

    public function sendRecruitmentInvitations(CareRequestRecruitmentService $recruitment): void
    {
        $this->authorizeAdmin();
        $this->validateRecruitmentSelection(required: true);
        $this->refreshRequest();

        if ($reason = $recruitment->unavailableReason($this->careRequest)) {
            $this->addError('recruitment', $reason);

            return;
        }

        $result = $recruitment->enqueue(auth()->user(), $this->careRequest, $this->selectedRecruitmentCaregivers);
        $this->selectedRecruitmentCaregivers = [];
        $this->resetPage('recruitmentPage');
        $this->resetValidation();

        $message = $result['queued'].' invitation'.($result['queued'] === 1 ? '' : 's').' queued for email from Lolo.';
        if ($result['skipped'] > 0) {
            $message .= ' '.$result['skipped'].' were not queued. They may already be contacted or ineligible; check recent outreach for any send failures.';
        }

        session()->flash('recruitmentStatus', $message);
    }

    public function refreshRecruitment(): void
    {
        $this->authorizeAdmin();
        $this->refreshRequest();
    }

    public function render(CareRecipientProfilePresenter $profiles, CareRequestRecruitmentService $recruitment, AdminCareRequestSchedule $schedules): View
    {
        $this->authorizeAdmin();
        $this->careRequest->loadMissing(AdminCareRequestSchedule::relations());
        $recruitmentCounts = $this->recruitmentHistory()->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')->pluck('aggregate', 'status');

        return view('livewire.admin.care-request-show', [
            'schedule' => $schedules->present($this->careRequest),
            'careProfileSnapshot' => $profiles->forCareRequest(auth()->user(), $this->careRequest),
            'recruitmentUnavailableReason' => $recruitment->unavailableReason($this->careRequest),
            'recruitmentCandidates' => $this->showRecruitmentPanel
                ? $this->recruitmentCandidates($recruitment)->paginate(25, ['users.*'], 'recruitmentPage')
                : null,
            'recruitmentHistory' => $this->recruitmentHistory()->with(['caregiver:id,name,email', 'inviter:id,name'])
                ->latest('id')->limit(10)->get(),
            'recruitmentCounts' => $recruitmentCounts,
            'recruitmentBatchLimit' => CareRequestRecruitmentService::BATCH_LIMIT,
            'hasPendingRecruitment' => (int) $recruitmentCounts->get('queued', 0) + (int) $recruitmentCounts->get('sending', 0) > 0,
        ]);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
    }

    private function validateRecruitmentSelection(bool $required = false): void
    {
        $this->validate([
            'selectedRecruitmentCaregivers' => [$required ? 'required' : 'present', 'array', 'max:'.CareRequestRecruitmentService::BATCH_LIMIT],
            'selectedRecruitmentCaregivers.*' => ['required', 'integer', 'min:1', 'distinct'],
            'recruitmentSearch' => ['nullable', 'string', 'max:200'],
        ], [
            'selectedRecruitmentCaregivers.required' => 'Select at least one caregiver to invite.',
            'selectedRecruitmentCaregivers.max' => 'Select up to '.CareRequestRecruitmentService::BATCH_LIMIT.' caregivers per send.',
        ]);
    }

    private function recruitmentCandidates(CareRequestRecruitmentService $recruitment): Builder
    {
        return $recruitment->candidates($this->careRequest, mb_substr(trim($this->recruitmentSearch), 0, 200));
    }

    private function recruitmentHistory(): Builder
    {
        return CareRequestRecruitment::query()->where('care_request_id', $this->careRequest->id);
    }

    private function refreshRequest(): void
    {
        $this->careRequest = $this->loadRequest($this->careRequest->id);
    }

    private function loadRequest(int $requestId): CareRequest
    {
        return CareRequest::query()
            ->with([
                'family:id,name,email,phone,city,state',
                'recipient',
                'thirdPartyContact',
                'tasks',
                'applications' => fn ($query) => $query->with([
                    'caregiver:id,name,email,phone,city,state',
                    'caregiver.caregiverProfile:id,user_id,status,average_rating,reviews_count,platform_hourly_rate',
                    'conversation:id,care_request_application_id,care_request_id,caregiver_user_id,last_message_at',
                    'booking:id,care_request_id,care_request_application_id,status,scheduled_start_at,scheduled_end_at,completed_at',
                ])->latest(),
                'invitations' => fn ($query) => $query->with(['caregiver:id,name,email'])->latest(),
                'conversations:id,care_request_id,family_user_id,caregiver_user_id,last_message_at',
                'booking',
                'booking.caregiver:id,name,email',
                'booking.payment',
                'booking.reviews.reviewer:id,name',
                'reviews.reviewer:id,name',
            ])
            ->withCount(['applications', 'invitations', 'conversations', 'tasks'])
            ->findOrFail($requestId);
    }
}
