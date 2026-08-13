<?php

namespace App\Livewire\Admin\AiSupport;

use App\Models\KnowledgeBaseEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class KnowledgeIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $role = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->canManageKnowledgeBase(), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedRole(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $search = trim($this->search);
        $entries = KnowledgeBaseEntry::query()
            ->with(['workingVersion', 'publishedVersion'])
            ->when($search !== '', fn (Builder $query): Builder => $query
                ->where(fn (Builder $match): Builder => $match
                    ->where('stable_id', 'like', '%'.$search.'%')
                    ->orWhereHas('workingVersion', fn (Builder $version): Builder => $version
                        ->where('title', 'like', '%'.$search.'%')
                        ->orWhere('answer_body', 'like', '%'.$search.'%'))))
            ->when($this->status !== '', fn (Builder $query): Builder => $query
                ->whereHas('workingVersion', fn (Builder $version): Builder => $version->where('status', $this->status)))
            ->when($this->role !== '', fn (Builder $query): Builder => $query
                ->whereHas('workingVersion', fn (Builder $version): Builder => $version->whereJsonContains('roles', $this->role)))
            ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
            ->latest('updated_at')
            ->paginate(25);

        return view('livewire.admin.ai-support.knowledge-index', [
            'entries' => $entries,
            'publishedCount' => KnowledgeBaseEntry::query()->whereNotNull('published_version_id')->count(),
            'draftCount' => KnowledgeBaseEntry::query()->whereHas('workingVersion', fn (Builder $version): Builder => $version->where('status', 'draft'))->count(),
            'pausedCount' => KnowledgeBaseEntry::query()->whereHas('workingVersion', fn (Builder $version): Builder => $version->where('status', 'paused'))->count(),
            'overdueCount' => KnowledgeBaseEntry::query()->whereHas('workingVersion', fn (Builder $version): Builder => $version->whereDate('review_by', '<', today()))->count(),
        ]);
    }
}
