<?php

namespace App\Livewire\Admin;

use App\Models\CaregiverCertification;
use App\Models\CaregiverModerationLog;
use App\Models\CaregiverProfile;
use App\Models\CaregiverScreeningCase;
use App\Models\ScreeningBookingReview;
use App\Services\Caregiver\CaregiverCertificationReviewService;
use App\Services\Screening\CaregiverScreeningService;
use App\Services\Screening\DiditVerifiedIdentityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class CaregiverReviewsQueue extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $selectedId = null;

    #[Locked]
    public int $expectedVersion = 0;

    public string $filter = 'open';

    public string $query = '';

    public string $outcome = '';

    public string $note = '';

    public array $certificationRejectionReasons = [];

    public array $bookingResolutions = [];

    public array $bookingNotes = [];

    public function boot(): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
    }

    public function mount(): void
    {
        $this->selectNext();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
        $this->selectNext();
    }

    public function updatedQuery(): void
    {
        $this->resetPage();
        $this->selectNext();
    }

    public function selectCase(int $id): void
    {
        $case = $this->visibleCasesQuery()->findOrFail($id);
        $this->resetValidation();
        $this->selectedId = $case->id;
        $this->expectedVersion = (int) $case->lock_version;
        $this->outcome = $case->outcome ?? '';
        $this->note = '';
        $this->certificationRejectionReasons = [];
    }

    public function claim(): void
    {
        $case = app(CaregiverScreeningService::class)->claim($this->selectedCaseId(), auth()->user());
        $this->expectedVersion = (int) $case->lock_version;
        $this->resetValidation();
    }

    public function search(): void
    {
        $case = app(CaregiverScreeningService::class)->search($this->selectedCaseId(), auth()->user());
        $this->expectedVersion = (int) $case->lock_version;
        $this->outcome = '';
        $this->resetValidation();
        session()->flash('status', $case->search_status === 'completed'
            ? 'Search complete. Review the evidence and record your decision.'
            : 'Search could not be completed. Account access has not changed.');
    }

    public function decide(string $decision): void
    {
        abort_unless(in_array($decision, ['approve', 'suspend'], true), 422);
        $this->validate([
            'outcome' => ['nullable', Rule::in(['no_match', 'potential_match', 'match'])],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $case = app(CaregiverScreeningService::class)->decide(
            $this->selectedCaseId(), auth()->user(), $this->outcome, $decision, $this->note, $this->expectedVersion,
        );

        session()->flash('status', $decision === 'approve'
            ? 'Screening approved. Next review is due in six months.'
            : 'New work is blocked. Login remains available and existing commitments are flagged for admin review.');
        $this->selectNext($case->id);
    }

    public function keepPending(): void
    {
        $this->validate(['note' => ['nullable', 'string', 'max:5000']]);
        $case = app(CaregiverScreeningService::class)->defer(
            $this->selectedCaseId(), auth()->user(), $this->note, $this->expectedVersion,
        );
        session()->flash('status', 'Review kept pending. Account access and the review due date have not changed.');
        $this->selectNext($case->id);
    }

    public function suspendNewWork(): void
    {
        $this->validate(['note' => ['nullable', 'string', 'max:5000']]);
        $case = app(CaregiverScreeningService::class)->suspendNewWork(
            $this->selectedCaseId(), auth()->user(), $this->note, $this->expectedVersion,
        );
        $this->expectedVersion = (int) $case->lock_version;
        $this->note = '';
        session()->flash('status', 'New work suspended by your decision. Login remains available, existing commitments are flagged, and this screening review stays open.');
    }

    public function startFollowUp(): void
    {
        $case = CaregiverScreeningCase::query()->findOrFail($this->selectedCaseId());
        abort_unless($case->status === 'completed' && $case->profile, 422);
        if (! app(DiditVerifiedIdentityService::class)->snapshot($case->profile)) {
            throw ValidationException::withMessages(['screening' => 'Complete approved KYC before starting a screening review.']);
        }
        $followUp = app(CaregiverScreeningService::class)->enqueue($case->profile);
        unset($this->reviewableProfileIds);
        $this->filter = 'open';
        $this->query = '';
        $this->resetPage();
        $this->selectCase($followUp->id);
        session()->flash('status', 'Follow-up review opened. Account access has not changed.');
    }

    public function resolveBookingReview(int $id): void
    {
        $this->validate([
            'bookingResolutions.'.$id => ['required', Rule::in(['reviewed_no_change', 'action_taken'])],
            'bookingNotes.'.$id => ['required', 'string', 'min:5', 'max:5000'],
        ]);
        app(CaregiverScreeningService::class)->resolveBookingReview(
            $id, auth()->user(), $this->bookingResolutions[$id], $this->bookingNotes[$id],
        );
        unset($this->bookingResolutions[$id], $this->bookingNotes[$id]);
        session()->flash('status', 'Booking review resolved. This does not change the booking itself.');
    }

    public function toggleTopCaregiver(int $id): void
    {
        $profile = CaregiverProfile::query()->findOrFail($id);
        $enabled = ! $profile->top_caregiver;
        $profile->update(['top_caregiver' => $enabled]);
        CaregiverModerationLog::create([
            'caregiver_profile_id' => $profile->id,
            'actor_user_id' => auth()->id(),
            'action' => $enabled ? 'top_caregiver_enabled' : 'top_caregiver_removed',
            'note' => $enabled ? 'Top Caregiver badge enabled' : 'Top Caregiver badge removed',
        ]);
    }

    public function verifyCertification(int $id, CaregiverCertificationReviewService $review): void
    {
        $review->verify(auth()->user(), CaregiverCertification::query()->findOrFail($id));
        session()->flash('status', 'Credential verified.');
    }

    public function rejectCertification(int $id, CaregiverCertificationReviewService $review): void
    {
        $review->reject(auth()->user(), CaregiverCertification::query()->findOrFail($id), (string) ($this->certificationRejectionReasons[$id] ?? ''));
        unset($this->certificationRejectionReasons[$id]);
        session()->flash('status', 'Credential returned to the caregiver for attention.');
    }

    protected function selectedCaseId(): int
    {
        abort_unless($this->selectedId, 422, 'Select a screening case first.');
        $this->visibleCasesQuery()->findOrFail($this->selectedId);

        return $this->selectedId;
    }

    protected function selectNext(?int $exceptId = null): void
    {
        $id = $this->queueQuery()->when($exceptId, fn (Builder $query) => $query->whereKeyNot($exceptId))->value('id');
        if ($id) {
            $this->selectCase((int) $id);
        } else {
            $this->selectedId = null;
            $this->expectedVersion = 0;
            $this->outcome = '';
            $this->note = '';
            $this->resetValidation();
        }
    }

    #[Computed]
    public function reviewableProfileIds(): array
    {
        $profiles = CaregiverProfile::query()->whereIn('id', CaregiverScreeningCase::query()
            ->select('caregiver_profile_id')->where('status', '!=', 'completed'))
            ->get(['id', 'identity_verification_session_id']);

        return app(DiditVerifiedIdentityService::class)->snapshotsForProfiles($profiles)->keys()->all();
    }

    protected function visibleCasesQuery(): Builder
    {
        return CaregiverScreeningCase::query()->where(fn (Builder $query) => $query
            ->where('status', 'completed')
            ->orWhere(fn (Builder $query) => $query->whereIn('caregiver_profile_id', $this->reviewableProfileIds)
                ->where('status', '!=', 'awaiting_kyc')));
    }

    protected function queueQuery(): Builder
    {
        $query = $this->visibleCasesQuery()->with(['profile.user', 'claimant']);
        match ($this->filter) {
            'initial', 'legacy', 'recurring' => $query->where('kind', $this->filter)->where('status', '!=', 'completed'),
            'awaiting_investigation' => $query->where('status', $this->filter),
            'potential_matches' => $query->where('status', '!=', 'completed')->whereHas('candidates'),
            'overdue' => $query->where('status', '!=', 'completed')->where('due_at', '<', now()),
            'completed' => $query->where('status', 'completed'),
            default => $query->where('status', '!=', 'completed'),
        };
        if (trim($this->query) !== '') {
            $search = '%'.trim($this->query).'%';
            $query->whereHas('profile.user', fn (Builder $user) => $user->where(function (Builder $user) use ($search): void {
                $user->where('name', 'like', $search)->orWhere('email', 'like', $search);
            }));
        }

        return $query->orderBy('due_at')->orderBy('id');
    }

    public function render()
    {
        if ($this->selectedId && ! $this->visibleCasesQuery()->whereKey($this->selectedId)->exists()) {
            $this->selectNext();
        }
        $cases = $this->queueQuery()->paginate(20);
        $selectedCase = $this->selectedId ? CaregiverScreeningCase::query()->with([
            'profile.user', 'profile.latestIdentityVerification', 'profile.careExperiences',
            'profile.certifications.type', 'profile.certifications.verifier',
            'candidates', 'events', 'claimant', 'reviewer',
        ])->find($this->selectedId) : null;
        $history = $selectedCase?->caregiver_profile_id ? $this->visibleCasesQuery()
            ->where('caregiver_profile_id', $selectedCase->caregiver_profile_id)
            ->whereKeyNot($selectedCase->id)->with('reviewer')->latest('id')->limit(20)->get() : collect();
        $moderationLogs = $selectedCase?->profile?->moderationLogs()->with('actor')->latest('id')->limit(20)->get() ?? collect();
        $bookingReviews = ScreeningBookingReview::query()->where('screening_booking_reviews.status', 'open')
            ->leftJoin('care_bookings as flagged_booking', fn ($join) => $join
                ->on('flagged_booking.id', '=', 'screening_booking_reviews.subject_id')->where('screening_booking_reviews.subject_type', 'booking'))
            ->leftJoin('care_plans as flagged_plan', fn ($join) => $join
                ->on('flagged_plan.id', '=', 'screening_booking_reviews.subject_id')->where('screening_booking_reviews.subject_type', 'care_plan'))
            ->leftJoin('continuous_coverage_shifts as flagged_shift', fn ($join) => $join
                ->on('flagged_shift.id', '=', 'screening_booking_reviews.subject_id')->where('screening_booking_reviews.subject_type', 'coverage_shift'))
            ->select('screening_booking_reviews.*', 'flagged_booking.care_request_id as booking_request_id', 'flagged_booking.care_plan_id as booking_plan_id')
            ->selectRaw('COALESCE(flagged_booking.scheduled_start_at, flagged_shift.scheduled_start_at, flagged_plan.starts_on) as commitment_starts_at')
            ->selectRaw('COALESCE(flagged_booking.status, flagged_shift.status, flagged_plan.status) as commitment_status')
            ->orderByRaw("CASE WHEN flagged_booking.status = 'in_progress' OR flagged_shift.status = 'in_progress' THEN 0 ELSE 1 END")
            ->orderByRaw('COALESCE(flagged_booking.scheduled_start_at, flagged_shift.scheduled_start_at, flagged_plan.starts_on, screening_booking_reviews.created_at)')
            ->orderBy('screening_booking_reviews.id')->with('screeningCase.profile.user')->paginate(10, ['*'], 'bookingPage');
        $counts = $this->visibleCasesQuery()->where('status', '!=', 'completed')
            ->selectRaw('kind, COUNT(*) as aggregate')->groupBy('kind')->pluck('aggregate', 'kind');

        return view('livewire.admin.caregiver-reviews-queue', compact(
            'cases', 'selectedCase', 'history', 'moderationLogs', 'bookingReviews', 'counts',
        ));
    }
}
