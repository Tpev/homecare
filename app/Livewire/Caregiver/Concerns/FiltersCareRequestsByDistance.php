<?php

namespace App\Livewire\Caregiver\Concerns;

use App\Services\Marketplace\CaregiverDistanceFilter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;

trait FiltersCareRequestsByDistance
{
    #[Url(as: 'radius', except: '25')]
    public string $radiusMiles = '25';

    public function updatedRadiusMiles(): void
    {
        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
        $this->validateOnly('radiusMiles', [
            'radiusMiles' => ['required', Rule::in([...CaregiverDistanceFilter::MILES, 'all'])],
        ]);
    }

    protected function selectedRadiusMiles(): ?int
    {
        if ($this->radiusMiles === 'all') {
            return null;
        }

        return in_array($this->radiusMiles, array_map('strval', CaregiverDistanceFilter::MILES), true)
            ? (int) $this->radiusMiles : -1;
    }

    protected function distanceFilterData(): array
    {
        $zip = auth()->user()?->caregiverProfile?->service_area_zip;
        $next = collect(CaregiverDistanceFilter::MILES)->first(fn ($miles) => $miles > (int) $this->radiusMiles);

        return [
            'serviceZip' => $zip,
            'distanceAvailable' => app(CaregiverDistanceFilter::class)->hasZip($zip),
            'nextRadius' => $this->radiusMiles === 'all' ? null : ($next === null ? 'all' : (string) $next),
        ];
    }
}
