<?php

namespace App\Livewire\Caregiver;

use App\Livewire\Concerns\ManagesCaregiverBackground;
use App\Models\CaregiverModerationLog;
use App\Models\CaregiverProfile;
use App\Models\CaregiverProfileVersion;
use App\Models\Language;
use App\Models\Skill;
use App\Services\Caregiver\CaregiverBackgroundService;
use App\Services\Images\CaregiverProfilePhotoProcessor;
use App\Support\MarketplacePricing;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Throwable;

#[Layout('layouts.app')]
class ProfileEditor extends Component
{
    use ManagesCaregiverBackground;
    use WithFileUploads;

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
        $this->initializeCaregiverBackground($this->profile);

        $this->skillOptions = Skill::query()->orderBy('name')->get(['id', 'name'])->toArray();
        $this->languageOptions = Language::query()->orderBy('name')->get(['id', 'name'])->toArray();
        $this->pricingTiers = app(MarketplacePricing::class)->tiers();

        $this->bio = (string) $this->profile->bio;
        $this->profile_photo_path = $this->profile->profile_photo_path;
        $this->years_experience = $this->profile->years_experience;
        $this->city = (string) $user->city;
        $this->state = (string) $user->state;
        $this->service_area_zip = (string) $this->profile->service_area_zip;
        $this->service_radius_miles = $this->profile->service_radius_miles ?: 10;
        $this->is_accepting_new_clients = (bool) $this->profile->is_accepting_new_clients;
        $this->selectedSkills = $this->profile->skills()->pluck('skills.id')->all();
        $this->selectedLanguages = $this->profile->languages()->pluck('languages.id')->all();
    }

    public function save(): void
    {
        $saveBackground = $this->profile->requiresCareBackground()
            || $this->profile->careBackgroundIsAnswered()
            || $this->caregiverBackgroundWasStarted();
        $this->validateCaregiverBackground($saveBackground);

        $this->validate([
            'profile_photo' => $this->profilePhotoRules(),
            'bio' => ['required', 'string', 'min:40', 'max:2000'],
            'years_experience' => ['required', 'integer', 'min:0', 'max:60'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'size:2'],
            'service_area_zip' => ['required', 'string', 'max:15'],
            'service_radius_miles' => ['required', 'integer', 'min:1', 'max:60'],
            'selectedSkills' => ['required', 'array', 'min:1'],
            'selectedLanguages' => ['required', 'array', 'min:1'],
        ], $this->validationMessages());

        $photoProcessor = app(CaregiverProfilePhotoProcessor::class);
        $backgroundService = app(CaregiverBackgroundService::class);
        $newProfilePhotoPath = null;
        $previousProfilePhotoPath = $this->profile_photo_path;
        $backgroundUploads = [];
        $obsoleteBackgroundPaths = [];
        $backgroundChanged = false;

        try {
            if ($this->profile_photo) {
                $newProfilePhotoPath = $photoProcessor->store($this->profile_photo);
            }
            if ($saveBackground) {
                $backgroundUploads = $backgroundService->storeUploads(
                    $this->certificationDocuments,
                    $this->selectedCertificationTypeIds(),
                );
            }
        } catch (Throwable $exception) {
            $photoProcessor->deleteIfManaged($newProfilePhotoPath);
            $backgroundService->discardUploads($backgroundUploads);

            throw $exception;
        }

        try {
            DB::transaction(function () use (
                $newProfilePhotoPath,
                $saveBackground,
                $backgroundService,
                $backgroundUploads,
                &$obsoleteBackgroundPaths,
                &$backgroundChanged,
            ) {
                $user = auth()->user();

                if ($newProfilePhotoPath) {
                    $this->profile_photo_path = $newProfilePhotoPath;
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

                if ($saveBackground) {
                    $backgroundResult = $backgroundService->syncWithinTransaction(
                        $this->profile,
                        $this->selectedExperienceTypes,
                        $this->care_experience_notes,
                        $this->selectedCertificationTypes,
                        $this->certificationDetails,
                        $backgroundUploads,
                        $this->certificationDocumentsToRemove,
                    );
                    $backgroundChanged = $backgroundResult['changed'];
                    $obsoleteBackgroundPaths = $backgroundResult['obsolete_paths'];
                }

                CaregiverProfileVersion::create([
                    'caregiver_profile_id' => $this->profile->id,
                    'user_id' => $user->id,
                    'reason' => 'profile_edit',
                    'snapshot' => [
                        'profile' => $this->profile->fresh()->toArray(),
                        'skills' => $this->selectedSkills,
                        'languages' => $this->selectedLanguages,
                        'care_background' => $backgroundService->snapshot(
                            $this->profile->fresh(['careExperiences', 'certifications.type'])
                        ),
                    ],
                ]);

                if ($backgroundChanged) {
                    CaregiverModerationLog::query()->create([
                        'caregiver_profile_id' => $this->profile->id,
                        'actor_user_id' => $user->id,
                        'action' => 'care_background_updated',
                        'note' => 'Caregiver updated care experience or credential information.',
                        'meta' => [
                            'experience_count' => count($this->selectedExperienceTypes),
                            'certification_count' => count($this->selectedCertificationTypeIds()),
                        ],
                    ]);
                }
            });
        } catch (Throwable $exception) {
            $photoProcessor->deleteIfManaged($newProfilePhotoPath);
            $backgroundService->discardUploads($backgroundUploads);

            throw $exception;
        }

        if ($newProfilePhotoPath) {
            $photoProcessor->deleteIfManaged($previousProfilePhotoPath);
            $this->profile_photo = null;
        }

        $backgroundService->deletePaths($obsoleteBackgroundPaths);
        if ($saveBackground) {
            $this->profile->refresh();
            $this->initializeCaregiverBackground($this->profile);
            $this->certificationDocuments = [];
            $this->certificationDocumentsToRemove = [];
        }

        session()->flash('status', 'Profile updated successfully.');
    }

    public function updatedProfilePhoto(): void
    {
        $this->resetErrorBag('profile_photo');
        $this->validateOnly('profile_photo', ['profile_photo' => $this->profilePhotoRules()], $this->validationMessages());
    }

    private function profilePhotoRules(): array
    {
        return app(CaregiverProfilePhotoProcessor::class)->validationRules();
    }

    private function validationMessages(): array
    {
        return app(CaregiverProfilePhotoProcessor::class)->validationMessages('profile_photo');
    }

    public function render()
    {
        $completeness = 0;
        $checks = [
            ! empty($this->bio),
            ! empty($this->years_experience),
            ! empty($this->service_area_zip),
            count($this->selectedSkills) > 0,
            count($this->selectedLanguages) > 0,
        ];
        $completeness = (int) round((array_sum($checks) / count($checks)) * 100);

        return view('livewire.caregiver.profile-editor', compact('completeness'));
    }
}
