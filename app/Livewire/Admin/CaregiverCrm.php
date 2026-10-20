<?php

namespace App\Livewire\Admin;

use App\Models\CaregiverCrmActivity;
use App\Models\User;
use App\Services\CaregiverCrm\CaregiverCrmDirectory;
use App\Services\CaregiverCrm\CaregiverCrmService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class CaregiverCrm extends Component
{
    public string $search = '';

    public string $contact = 'all';

    public string $followUp = 'all';

    public string $assigned = 'all';

    #[Locked]
    #[Url(as: 'caregiver', except: null)]
    public ?int $selectedCaregiverId = null;

    #[Locked]
    public int $recordVersion = 0;

    public array $recordForm = [];

    public array $activityForm = [];

    #[Locked]
    public array $savedRecordForm = [];

    #[Locked]
    public array $savedActivityForm = [];

    public array $columnOffsets = [];

    public int $activityOffset = 0;

    public string $notice = '';

    public function boot(): void
    {
        $actor = auth()->user();
        abort_unless($actor?->canAccessCaregiverCrm() && $actor->fresh()?->canAccessCaregiverCrm(), 403);
    }

    public function mount(): void
    {
        $this->resetActivityForm();
        if ($this->selectedCaregiverId) {
            $this->openCaregiver($this->selectedCaregiverId);
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'contact', 'followUp', 'assigned'], true)) {
            $this->columnOffsets = [];
        }
        if ($property === 'activityForm.type') {
            $this->activityForm['call_outcome'] = '';
        }
        $this->notice = '';
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'contact', 'followUp', 'assigned', 'columnOffsets');
    }

    public function loadMore(string $stage): void
    {
        if (in_array($stage, ['new', 'applied', 'hired'], true)) {
            $this->columnOffsets[$stage] = max(0, (int) ($this->columnOffsets[$stage] ?? 0)) + 8;
        }
    }

    public function previousPage(string $stage): void
    {
        if (in_array($stage, ['new', 'applied', 'hired'], true)) {
            $this->columnOffsets[$stage] = max(0, (int) ($this->columnOffsets[$stage] ?? 0) - 8);
        }
    }

    public function openCaregiver(int $caregiverId): void
    {
        $this->resetValidation();
        $this->notice = '';
        $this->selectedCaregiverId = $caregiverId;
        $this->activityOffset = 0;
        $this->loadRecordForm();
        $this->resetActivityForm();
    }

    public function closeCaregiver(): void
    {
        $this->selectedCaregiverId = null;
        $this->recordForm = [];
        $this->resetValidation();
        $this->notice = '';
    }

    public function reloadCaregiver(): void
    {
        $this->resetValidation();
        $this->loadRecordForm();
        $this->notice = 'Details reloaded.';
    }

    public function saveRecord(CaregiverCrmService $service): void
    {
        abort_unless($this->selectedCaregiverId, 404);
        $service->saveRecord(auth()->user(), $this->selectedCaregiverId, $this->recordForm, $this->recordVersion);
        $this->resetValidation();
        $this->loadRecordForm();
        $this->notice = 'Caregiver details saved.';
    }

    public function completeNextAction(CaregiverCrmService $service): void
    {
        abort_unless($this->selectedCaregiverId, 404);
        if ($this->recordForm != $this->savedRecordForm) {
            throw ValidationException::withMessages([
                'record_draft' => 'Save your detail changes before completing the next action.',
            ]);
        }
        $service->completeNextAction(auth()->user(), $this->selectedCaregiverId, $this->recordVersion);
        $this->resetValidation();
        $this->loadRecordForm();
        $this->notice = 'Next action completed.';
    }

    public function logActivity(CaregiverCrmService $service): void
    {
        abort_unless($this->selectedCaregiverId, 404);
        $service->logActivity(auth()->user(), $this->selectedCaregiverId, $this->activityForm);
        $this->resetValidation();
        $this->resetActivityForm();
        $this->activityOffset = 0;
        $this->notice = 'Activity added to the timeline.';
    }

    public function loadMoreActivity(): void
    {
        $this->activityOffset = max(0, $this->activityOffset) + 20;
    }

    public function previousActivity(): void
    {
        $this->activityOffset = max(0, $this->activityOffset - 20);
    }

    public function render(CaregiverCrmDirectory $directory): View
    {
        $board = $directory->board([
            'search' => $this->search,
            'contact' => $this->contact,
            'follow_up' => $this->followUp,
            'assigned' => $this->assigned,
        ], [], $this->columnOffsets);
        foreach (array_keys($this->columnOffsets) as $stage) {
            if (isset($board['offsets'][$stage])) {
                $this->columnOffsets[$stage] = $board['offsets'][$stage];
            } else {
                unset($this->columnOffsets[$stage]);
            }
        }
        $selected = $this->selectedCaregiverId ? $directory->find($this->selectedCaregiverId) : null;
        $activities = $selected ? CaregiverCrmActivity::query()
            ->where('caregiver_user_id', $selected['user_id'])
            ->with('actor:id,name')
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->offset(max(0, $this->activityOffset))->limit(21)->get() : collect();

        return view('livewire.admin.caregiver-crm', [
            'board' => $board,
            'selected' => $selected,
            'activities' => $activities->take(20),
            'hasMoreActivity' => $activities->count() > 20,
            'owners' => User::query()->whereIn('role', ['admin', 'sdr'])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function loadRecordForm(): void
    {
        $row = $this->selectedCaregiverId
            ? app(CaregiverCrmDirectory::class)->find($this->selectedCaregiverId) : null;
        $this->recordVersion = (int) ($row['version'] ?? 0);
        $this->recordForm = [
            'assigned_admin_id' => $row['assigned_admin_id'] ?? '',
            'priority' => $row['priority'] ?? 'normal',
            'notes' => $row['notes'] ?? '',
            'next_action' => $row['next_action'] ?? '',
            'next_follow_up_at' => empty($row['next_follow_up_at']) ? ''
                : Carbon::parse($row['next_follow_up_at'])->format('Y-m-d\TH:i'),
        ];
        $this->savedRecordForm = $this->recordForm;
    }

    private function resetActivityForm(): void
    {
        $this->activityForm = [
            'type' => 'note',
            'call_outcome' => '',
            'body' => '',
            'occurred_at' => now()->format('Y-m-d\TH:i'),
        ];
        $this->savedActivityForm = $this->activityForm;
    }
}
