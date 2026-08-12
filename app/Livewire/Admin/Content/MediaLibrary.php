<?php

namespace App\Livewire\Admin\Content;

use App\Models\MediaAsset;
use App\Services\Content\MediaAssetManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class MediaLibrary extends Component
{
    use WithFileUploads, WithPagination;

    public mixed $upload = null;

    public string $q = '';

    public ?int $editingId = null;

    public string $altText = '';

    public string $caption = '';

    public string $credit = '';

    public string $license = '';

    public string $sourceUrl = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', MediaAsset::class);
    }

    public function store(MediaAssetManager $manager): void
    {
        Gate::authorize('create', MediaAsset::class);
        $this->validate([
            'upload' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:20480'],
            'altText' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:1000'],
            'credit' => ['nullable', 'string', 'max:255'],
            'license' => ['nullable', 'string', 'max:255'],
            'sourceUrl' => ['nullable', 'url:http,https', 'max:2048'],
        ]);
        $asset = $manager->storeUpload($this->upload, auth()->user(), [
            'alt_text' => $this->altText,
            'caption' => $this->caption,
            'credit' => $this->credit,
            'license' => $this->license,
            'source_url' => $this->sourceUrl,
        ]);
        $this->reset(['upload', 'altText', 'caption', 'credit', 'license', 'sourceUrl']);
        session()->flash('status', 'Image uploaded with responsive WEBP renditions. Asset #'.$asset->id.'.');
    }

    public function edit(int $assetId): void
    {
        $asset = MediaAsset::query()->findOrFail($assetId);
        Gate::authorize('update', $asset);
        $this->editingId = $asset->id;
        $this->altText = $asset->alt_text ?? '';
        $this->caption = $asset->caption ?? '';
        $this->credit = $asset->credit ?? '';
        $this->license = $asset->license ?? '';
        $this->sourceUrl = $asset->source_url ?? '';
    }

    public function updateMetadata(): void
    {
        $asset = MediaAsset::query()->findOrFail($this->editingId);
        Gate::authorize('update', $asset);
        $this->validate([
            'altText' => ['required', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:1000'],
            'credit' => ['nullable', 'string', 'max:255'],
            'license' => ['nullable', 'string', 'max:255'],
            'sourceUrl' => ['nullable', 'url:http,https', 'max:2048'],
        ]);
        $asset->update([
            'alt_text' => trim($this->altText),
            'caption' => trim($this->caption) ?: null,
            'credit' => trim($this->credit) ?: null,
            'license' => trim($this->license) ?: null,
            'source_url' => trim($this->sourceUrl) ?: null,
        ]);
        $this->cancelEdit();
        session()->flash('status', 'Media metadata updated. Republish affected articles to refresh their immutable HTML revision.');
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'altText', 'caption', 'credit', 'license', 'sourceUrl']);
    }

    public function delete(int $assetId, MediaAssetManager $manager): void
    {
        $asset = MediaAsset::query()->findOrFail($assetId);
        Gate::authorize('delete', $asset);
        $manager->delete($asset);
        session()->flash('status', 'Unused media asset deleted from storage.');
    }

    public function render(): View
    {
        $assets = MediaAsset::query()
            ->with('variants')
            ->when($this->q !== '', fn ($query) => $query->where(function ($inner): void {
                $inner->where('original_filename', 'like', '%'.$this->q.'%')
                    ->orWhere('alt_text', 'like', '%'.$this->q.'%')
                    ->orWhere('caption', 'like', '%'.$this->q.'%');
            }))
            ->latest()
            ->paginate(24);

        return view('livewire.admin.content.media-library', compact('assets'));
    }
}
