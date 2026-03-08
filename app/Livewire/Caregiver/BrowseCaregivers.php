<?php

namespace App\Livewire\Caregiver;

use App\Models\CaregiverProfile;
use App\Models\Language;
use App\Models\Skill;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class BrowseCaregivers extends Component
{
    use WithPagination;

    public string $zip = '';
    public ?float $rate_min = null;
    public ?float $rate_max = null;
    public array $skills = [];
    public array $languages = [];
    public string $sort = 'relevance';

    public function render()
    {
        $query = CaregiverProfile::query()
            ->with(['user','skills','languages'])
            ->where('status', 'active');

        if ($this->zip !== '') {
            $query->where('service_area_zip', $this->zip);
        }

        if ($this->rate_min !== null) {
            $query->where('hourly_rate', '>=', $this->rate_min);
        }

        if ($this->rate_max !== null) {
            $query->where('hourly_rate', '<=', $this->rate_max);
        }

        if (!empty($this->skills)) {
            $query->whereHas('skills', fn ($q) => $q->whereIn('skills.id', $this->skills));
        }

        if (!empty($this->languages)) {
            $query->whereHas('languages', fn ($q) => $q->whereIn('languages.id', $this->languages));
        }

        match ($this->sort) {
            'price_low' => $query->orderBy('hourly_rate'),
            'price_high' => $query->orderByDesc('hourly_rate'),
            'experience' => $query->orderByDesc('years_experience'),
            default => $query->orderByDesc('average_rating')->orderByDesc('reviews_count'),
        };

        return view('livewire.caregiver.browse-caregivers', [
            'caregivers' => $query->paginate(12),
            'skillOptions' => Skill::query()->orderBy('name')->get(['id','name']),
            'languageOptions' => Language::query()->orderBy('name')->get(['id','name']),
        ]);
    }
}
