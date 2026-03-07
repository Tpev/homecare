<?php

namespace App\Livewire\Admin;

use App\Models\Lead;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class LeadsIndex extends Component
{
    use WithPagination;

    public string $q = '';
    public string $type = 'all';
    public string $status = 'all';
    public string $sort = 'created_at';
    public string $dir = 'desc';
    public int $perPage = 25;

    public ?int $selectedLeadId = null;

    protected $queryString = [
        'q' => ['except' => ''],
        'type' => ['except' => 'all'],
        'status' => ['except' => 'all'],
        'sort' => ['except' => 'created_at'],
        'dir' => ['except' => 'desc'],
        'perPage' => ['except' => 25],
    ];

    public function updatingQ(): void { $this->resetPage(); }
    public function updatingType(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }
    public function updatingPerPage(): void { $this->resetPage(); }

    public function setSort(string $field): void
    {
        $allowed = ['created_at', 'lead_type', 'status', 'email', 'name', 'company', 'location', 'zip'];

        if (! in_array($field, $allowed, true)) return;

        if ($this->sort === $field) {
            $this->dir = $this->dir === 'asc' ? 'desc' : 'asc';
            return;
        }

        $this->sort = $field;
        $this->dir = 'desc';
    }

    public function openLead(int $id): void
    {
        $this->selectedLeadId = $id;
        $this->dispatch('open-modal', 'lead-details');
    }

    public function updateStatus(int $leadId, string $status): void
    {
        $allowed = ['new', 'contacted', 'qualified', 'closed'];
        if (! in_array($status, $allowed, true)) return;

        Lead::whereKey($leadId)->update(['status' => $status]);

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Updated',
            'message' => 'Lead status updated.',
        ]);
    }

    public function deleteLead(int $leadId): void
    {
        Lead::whereKey($leadId)->delete();

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Deleted',
            'message' => 'Lead deleted.',
        ]);
    }

    public function getSelectedLeadProperty(): ?Lead
    {
        return $this->selectedLeadId ? Lead::find($this->selectedLeadId) : null;
    }

    public function render(): View
    {
        $query = Lead::query();

        if ($this->type !== 'all') $query->where('lead_type', $this->type);
        if ($this->status !== 'all') $query->where('status', $this->status);

        $q = trim($this->q);
        if ($q !== '') {
            $qLower = Str::lower($q);
            $query->where(function ($sub) use ($q, $qLower) {
                $sub->where('email', 'like', "%{$qLower}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('company', 'like', "%{$q}%")
                    ->orWhere('location', 'like', "%{$q}%")
                    ->orWhere('zip', 'like', "%{$q}%");
            });
        }

        $leads = $query->orderBy($this->sort, $this->dir)->paginate($this->perPage);

        return view('livewire.admin.leads-index', compact('leads'));
           
    }
}