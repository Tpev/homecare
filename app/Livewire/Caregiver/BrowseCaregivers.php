<?php

namespace App\Livewire\Caregiver;

use App\Models\CaregiverProfile;
use App\Models\Language;
use App\Models\Skill;
use App\Support\CaregiverPrelaunch;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class BrowseCaregivers extends Component
{
    use WithPagination;

    public string $search = '';

    public string $zip = '';

    public ?float $rate_min = null;

    public ?float $rate_max = null;

    public array $skills = [];

    public array $languages = [];

    public string $trust = 'all';

    public string $sort = 'relevance';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingZip(): void
    {
        $this->resetPage();
    }

    public function updatingRateMin(): void
    {
        $this->resetPage();
    }

    public function updatingRateMax(): void
    {
        $this->resetPage();
    }

    public function updatingSkills(): void
    {
        $this->resetPage();
    }

    public function updatingLanguages(): void
    {
        $this->resetPage();
    }

    public function updatingTrust(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset([
            'search',
            'zip',
            'rate_min',
            'rate_max',
            'skills',
            'languages',
            'trust',
            'sort',
        ]);

        $this->trust = 'all';
        $this->sort = 'relevance';
        $this->resetPage();
    }

    public function render()
    {
        $prelaunchMode = CaregiverPrelaunch::enabled();

        $query = CaregiverProfile::query()
            ->with(['user', 'skills', 'languages', 'careExperiences', 'certifications.type'])
            ->where('status', 'active')
            ->whereNotNull('bio')
            ->whereNotNull('platform_hourly_rate')
            ->whereNotNull('years_experience')
            ->whereNotNull('service_area_zip')
            ->whereNotNull('service_radius_miles')
            ->whereHas('skills')
            ->whereHas('languages')
            ->whereHas('availabilities');

        if ($prelaunchMode) {
            $query->whereRaw('1 = 0');
        }

        if ($this->search !== '') {
            $term = trim($this->search);
            $query->where(function ($inner) use ($term) {
                $inner->where('bio', 'like', '%'.$term.'%')
                    ->orWhereHas('user', fn ($q) => $q
                        ->where('name', 'like', '%'.$term.'%')
                        ->orWhere('city', 'like', '%'.$term.'%')
                        ->orWhere('state', 'like', '%'.$term.'%'));
            });
        }

        if ($this->zip !== '') {
            $query->where('service_area_zip', $this->zip);
        }

        if ($this->rate_min !== null) {
            $query->where('platform_hourly_rate', '>=', $this->rate_min);
        }

        if ($this->rate_max !== null) {
            $query->where('platform_hourly_rate', '<=', $this->rate_max);
        }

        if (! empty($this->skills)) {
            $query->whereHas('skills', fn ($q) => $q->whereIn('skills.id', $this->skills));
        }

        if (! empty($this->languages)) {
            $query->whereHas('languages', fn ($q) => $q->whereIn('languages.id', $this->languages));
        }

        if ($this->trust === 'verified') {
            $query
                ->whereNotNull('identity_verified_at')
                ->whereNotNull('background_check_verified_at');
        }

        if ($this->trust === 'top') {
            $query->where('top_caregiver', true);
        }

        match ($this->sort) {
            'price_low' => $query->orderBy('platform_hourly_rate'),
            'price_high' => $query->orderByDesc('platform_hourly_rate'),
            'experience' => $query->orderByDesc('years_experience'),
            'reliability' => $query->orderByDesc('reliability_score')->orderByDesc('reviews_count'),
            'top' => $query->orderByDesc('top_caregiver')->orderByDesc('average_rating')->orderByDesc('reviews_count'),
            default => $query->orderByDesc('top_caregiver')->orderByDesc('average_rating')->orderByDesc('reviews_count'),
        };

        return view('livewire.caregiver.browse-caregivers', [
            'prelaunchMode' => $prelaunchMode,
            'caregivers' => $query->paginate(12),
            'skillOptions' => Skill::query()->orderBy('name')->get(['id', 'name']),
            'languageOptions' => Language::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
