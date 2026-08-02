<?php

namespace App\Livewire\Admin;

use App\Models\ContinuousCoverageEvent;
use App\Models\ContinuousCoveragePlan;
use App\Models\ContinuousCoverageReplacementCase;
use App\Models\ContinuousCoverageShift;
use App\Models\MarketplaceNotificationDelivery;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ContinuousCoverageIndex extends Component
{
    use WithPagination;

    public string $status = 'attention';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
    }

    public function updatedStatus(): void
    {
        $this->resetPage('plansPage');
    }

    public function render()
    {
        $plans = ContinuousCoveragePlan::query()
            ->with('family:id,name,email')
            ->withCount([
                'shifts as uncovered_count' => fn ($query) => $query->whereIn('status', [
                    ContinuousCoverageShift::STATUS_UNCOVERED,
                    ContinuousCoverageShift::STATUS_REPLACEMENT_NEEDED,
                ])->where('scheduled_start_at', '>=', now()),
                'shifts as payment_attention_count' => fn ($query) => $query->where('status', ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION),
            ])
            ->when($this->status === 'attention', fn ($query) => $query->whereHas('shifts', fn ($shift) => $shift
                ->whereIn('status', [
                    ContinuousCoverageShift::STATUS_UNCOVERED,
                    ContinuousCoverageShift::STATUS_REPLACEMENT_NEEDED,
                    ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION,
                ])->where('scheduled_start_at', '>=', now())))
            ->latest()
            ->paginate(20, ['*'], 'plansPage');
        $cases = ContinuousCoverageReplacementCase::query()
            ->with(['shift.plan.family:id,name,email', 'originalCaregiver:id,name', 'winningOffer.caregiver:id,name'])
            ->whereIn('status', [
                ContinuousCoverageReplacementCase::STATUS_OPEN,
                ContinuousCoverageReplacementCase::STATUS_AWAITING_FAMILY,
                ContinuousCoverageReplacementCase::STATUS_UNRESOLVED,
            ])
            ->latest('opened_at')
            ->paginate(10, ['*'], 'replacementCasesPage');

        $attentionShifts = ContinuousCoverageShift::query()
            ->with(['plan.family:id,name,email', 'assignedCaregiver:id,name', 'booking.payment'])
            ->whereIn('status', [
                ContinuousCoverageShift::STATUS_UNCOVERED,
                ContinuousCoverageShift::STATUS_REPLACEMENT_NEEDED,
                ContinuousCoverageShift::STATUS_AWAITING_FAMILY,
                ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION,
            ])
            ->where(fn ($query) => $query
                ->where('scheduled_start_at', '>=', now()->subDay())
                ->orWhereNotNull('care_booking_id'))
            ->orderBy('scheduled_start_at')
            ->paginate(20, ['*'], 'shiftExceptionsPage');

        $notificationFailures = MarketplaceNotificationDelivery::query()
            ->with('user:id,name,email')
            ->where('event_key', 'like', 'continuous_coverage_%')
            ->where('status', 'failed')
            ->latest()
            ->paginate(10, ['*'], 'notificationFailuresPage');

        $auditEvents = ContinuousCoverageEvent::query()
            ->with(['plan.family:id,name,email', 'shift', 'actor:id,name'])
            ->latest('happened_at')
            ->paginate(20, ['*'], 'auditEventsPage');

        return view('livewire.admin.continuous-coverage-index', compact(
            'plans', 'cases', 'attentionShifts', 'notificationFailures', 'auditEvents'
        ));
    }
}
