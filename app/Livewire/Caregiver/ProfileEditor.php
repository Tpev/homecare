<?php

namespace App\Livewire\Caregiver;

use App\Models\CaregiverProfile;
use App\Models\CaregiverProfileVersion;
use App\Models\Language;
use App\Models\Skill;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ProfileEditor extends Component
{
    public CaregiverProfile $profile;
    public string $bio = '';
    public ?float $hourly_rate = null;
    public ?int $years_experience = null;
    public string $city = '';
    public string $state = '';
    public string $service_area_zip = '';
    public ?int $service_radius_miles = 10;
    public bool $is_accepting_new_clients = true;
    public array $selectedSkills = [];
    public array $selectedLanguages = [];
    public array $skillOptions = [];
    public array $languageOptions = [];

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'caregiver', 403);

        $this->profile = CaregiverProfile::firstOrCreate(['user_id' => $user->id], ['status' => 'draft']);

        $this->skillOptions = Skill::query()->orderBy('name')->get(['id','name'])->toArray();
        $this->languageOptions = Language::query()->orderBy('name')->get(['id','name'])->toArray();

        $this->bio = (string) $this->profile->bio;
        $this->hourly_rate = $this->profile->hourly_rate ? (float)$this->profile->hourly_rate : null;
        $this->years_experience = $this->profile->years_experience;
        $this->city = (string)$user->city;
        $this->state = (string)$user->state;
        $this->service_area_zip = (string)$this->profile->service_area_zip;
        $this->service_radius_miles = $this->profile->service_radius_miles ?: 10;
        $this->is_accepting_new_clients = (bool)$this->profile->is_accepting_new_clients;
        $this->selectedSkills = $this->profile->skills()->pluck('skills.id')->all();
        $this->selectedLanguages = $this->profile->languages()->pluck('languages.id')->all();
    }

    public function save(): void
    {
        $this->validate([
            'bio' => ['required', 'string', 'min:40'],
            'hourly_rate' => ['required', 'numeric', 'min:15'],
            'years_experience' => ['required', 'integer', 'min:0'],
            'city' => ['required', 'string'],
            'state' => ['required', 'string', 'size:2'],
            'service_area_zip' => ['required', 'string'],
            'service_radius_miles' => ['required', 'integer', 'min:1'],
            'selectedSkills' => ['required', 'array', 'min:1'],
            'selectedLanguages' => ['required', 'array', 'min:1'],
        ]);

        DB::transaction(function () {
            $user = auth()->user();

            $user->forceFill([
                'city' => $this->city,
                'state' => strtoupper($this->state),
            ])->save();

            $this->profile->update([
                'bio' => $this->bio,
                'hourly_rate' => $this->hourly_rate,
                'years_experience' => $this->years_experience,
                'service_area_zip' => $this->service_area_zip,
                'service_radius_miles' => $this->service_radius_miles,
                'is_accepting_new_clients' => $this->is_accepting_new_clients,
                // stays active per your rule A
            ]);

            $this->profile->skills()->sync($this->selectedSkills);
            $this->profile->languages()->sync($this->selectedLanguages);

            CaregiverProfileVersion::create([
                'caregiver_profile_id' => $this->profile->id,
                'user_id' => $user->id,
                'reason' => 'profile_edit',
                'snapshot' => [
                    'profile' => $this->profile->fresh()->toArray(),
                    'skills' => $this->selectedSkills,
                    'languages' => $this->selectedLanguages,
                ],
            ]);
        });

        session()->flash('status', 'Profile updated successfully.');
    }

    public function render()
    {
        $completeness = 0;
        $checks = [
            !empty($this->bio),
            !empty($this->hourly_rate),
            !empty($this->years_experience),
            !empty($this->service_area_zip),
            count($this->selectedSkills) > 0,
            count($this->selectedLanguages) > 0,
        ];
        $completeness = (int) round((array_sum($checks) / count($checks)) * 100);

        return view('livewire.caregiver.profile-editor', compact('completeness'));
    }
}
