<?php

namespace App\Livewire\Caregiver;

use App\Models\CaregiverProfile;
use App\Models\CaregiverProfileVersion;
use App\Models\Language;
use App\Models\Skill;
use App\Support\MarketplacePricing;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

#[Layout('layouts.app')]
class ProfileEditor extends Component
{
    use WithFileUploads;

    private const PROFILE_PHOTO_RULES = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'];

    public CaregiverProfile $profile;
    public ?string $profile_photo_path = null;
    public $profile_photo = null;
    public string $bio = '';
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
    public array $pricingTiers = [];

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'caregiver', 403);

        $this->profile = CaregiverProfile::firstOrCreate(['user_id' => $user->id], ['status' => 'draft']);

        $this->skillOptions = Skill::query()->orderBy('name')->get(['id','name'])->toArray();
        $this->languageOptions = Language::query()->orderBy('name')->get(['id','name'])->toArray();
        $this->pricingTiers = app(MarketplacePricing::class)->tiers();

        $this->bio = (string) $this->profile->bio;
        $this->profile_photo_path = $this->profile->profile_photo_path;
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
            'profile_photo' => self::PROFILE_PHOTO_RULES,
            'bio' => ['required', 'string', 'min:40'],
            'years_experience' => ['required', 'integer', 'min:0'],
            'city' => ['required', 'string'],
            'state' => ['required', 'string', 'size:2'],
            'service_area_zip' => ['required', 'string'],
            'service_radius_miles' => ['required', 'integer', 'min:1'],
            'selectedSkills' => ['required', 'array', 'min:1'],
            'selectedLanguages' => ['required', 'array', 'min:1'],
        ], $this->validationMessages());

        DB::transaction(function () {
            $user = auth()->user();

            if ($this->profile_photo) {
                $this->profile_photo_path = $this->profile_photo->store('caregiver-photos', 'public');
            }

            $user->forceFill([
                'city' => $this->city,
                'state' => strtoupper($this->state),
            ])->save();

            $this->profile->update([
                'profile_photo_path' => $this->profile_photo_path,
                'bio' => $this->bio,
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

    public function updatedProfilePhoto(): void
    {
        $this->resetErrorBag('profile_photo');
        $this->validateOnly('profile_photo', ['profile_photo' => self::PROFILE_PHOTO_RULES], $this->validationMessages());
    }

    private function validationMessages(): array
    {
        return [
            'profile_photo.mimes' => 'Use a JPG, PNG, or WEBP image for your profile photo.',
            'profile_photo.max' => 'Your profile photo must be 10 MB or smaller.',
        ];
    }

    public function render()
    {
        $completeness = 0;
        $checks = [
            !empty($this->bio),
            !empty($this->years_experience),
            !empty($this->service_area_zip),
            count($this->selectedSkills) > 0,
            count($this->selectedLanguages) > 0,
        ];
        $completeness = (int) round((array_sum($checks) / count($checks)) * 100);

        return view('livewire.caregiver.profile-editor', compact('completeness'));
    }
}
