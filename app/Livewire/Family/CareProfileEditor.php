<?php

namespace App\Livewire\Family;

use App\Models\CareRecipientProfile;
use App\Services\AiSupport\AiSupportPreparationService;
use App\Services\CareRecipientProfiles\CareRecipientProfileAttachmentService;
use App\Services\CareRecipientProfiles\CareRecipientProfilePresenter;
use App\Services\CareRecipientProfiles\CareRecipientProfileService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class CareProfileEditor extends Component
{
    #[Locked]
    public ?int $profileId = null;

    #[Locked]
    public int $revision = 0;

    public int $step = 1;

    public bool $recipientIsRequester = false;

    public string $fullName = '';

    public string $preferredName = '';

    public string $dateOfBirth = '';

    public string $ageRange = '';

    public string $pronouns = '';

    public string $relationshipToFamily = '';

    public string $aboutThem = '';

    public string $interestsAndComforts = '';

    public string $goodVisitNotes = '';

    public array $communicationPreferences = [];

    public string $communicationNotes = '';

    public string $everydayHealthContext = '';

    public array $supportAreas = [];

    public array $supportDetails = [];

    public string $mobilityLevel = '';

    public string $mobilityNotes = '';

    public string $routineNotes = '';

    public string $foodAndDrinkNotes = '';

    public string $personalCarePreferences = '';

    public string $sleepOvernightNotes = '';

    public array $comfortNeeds = [];

    public string $distressTriggers = '';

    public string $calmingApproaches = '';

    public array $safetyItems = [];

    public string $safetyNotes = '';

    public array $caregiverQualityPreferences = [];

    public string $caregiverDoNotes = '';

    public string $caregiverAvoidNotes = '';

    public bool $includeAdditionalContact = false;

    public string $additionalContactName = '';

    public string $additionalContactRelationship = '';

    public string $additionalContactPhone = '';

    public string $additionalContactEmail = '';

    public string $assignedEscalationNotes = '';

    public bool $sharingAcknowledged = false;

    public bool $savedReady = false;

    public array $affectedCare = [];

    public bool $aiPrepared = false;

    public function mount(?CareRecipientProfile $careRecipientProfile = null): void
    {
        if ($careRecipientProfile?->exists) {
            $this->authorize('update', $careRecipientProfile);
            $this->loadProfile($careRecipientProfile);
        } else {
            $this->authorize('create', CareRecipientProfile::class);
        }

        $requestedStep = (int) request()->query('step', 1);
        $this->step = max(1, min(5, $requestedStep));

        $prepared = app(AiSupportPreparationService::class)->consume(
            auth()->user(),
            'care_profile_v1',
            $this->profileId ? 'care_profile' : null,
            $this->profileId,
        );
        $map = [
            'preferred_name' => 'preferredName', 'full_name' => 'fullName', 'date_of_birth' => 'dateOfBirth',
            'pronouns' => 'pronouns', 'relationship_to_family' => 'relationshipToFamily', 'about_them' => 'aboutThem',
            'interests_and_comforts' => 'interestsAndComforts', 'good_visit_notes' => 'goodVisitNotes',
            'communication_notes' => 'communicationNotes', 'everyday_health_context' => 'everydayHealthContext',
            'mobility_level' => 'mobilityLevel', 'mobility_notes' => 'mobilityNotes', 'routine_notes' => 'routineNotes',
            'food_and_drink_notes' => 'foodAndDrinkNotes', 'personal_care_preferences' => 'personalCarePreferences',
            'sleep_overnight_notes' => 'sleepOvernightNotes', 'safety_notes' => 'safetyNotes',
            'additional_contact_name' => 'additionalContactName',
            'additional_contact_relationship' => 'additionalContactRelationship',
            'additional_contact_phone' => 'additionalContactPhone', 'additional_contact_email' => 'additionalContactEmail',
        ];
        foreach ($prepared as $key => $value) {
            if (isset($map[$key]) && is_scalar($value)) {
                $property = $map[$key];
                $this->{$property} = (string) $value;
            }
        }
        if ($prepared !== []) {
            $this->aiPrepared = true;
            $this->includeAdditionalContact = filled($this->additionalContactName)
                || filled($this->additionalContactPhone) || filled($this->additionalContactEmail);
        }
    }

    public function continue(CareRecipientProfileService $profiles): void
    {
        $this->validate($this->rulesForStep($this->step));
        $wasNew = $this->profileId === null;
        $profile = $profiles->saveDraft(auth()->user(), $this->currentProfile(), $this->profileData(), $this->profileId ? $this->revision : null);
        $this->profileId = $profile->id;
        $this->revision = $profile->revision;
        $next = min(5, $this->step + 1);

        if ($wasNew) {
            $this->redirectRoute('family.care-profiles.edit', ['careRecipientProfile' => $profile->id, 'step' => $next], navigate: true);

            return;
        }
        $this->step = $next;
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function saveAndFinishLater(CareRecipientProfileService $profiles): void
    {
        $this->validate(['preferredName' => ['required', 'string', 'max:80']]);
        $profile = $profiles->saveDraft(auth()->user(), $this->currentProfile(), $this->profileData(), $this->profileId ? $this->revision : null);
        session()->flash('status', 'Saved. You can finish '.$profile->displayName().'\'s profile later.');
        $this->redirectRoute('family.care-profiles.index', navigate: true);
    }

    public function saveReady(
        CareRecipientProfileService $profiles,
        CareRecipientProfileAttachmentService $attachments,
    ): void {
        $this->validate($this->allRules());
        $profile = $this->currentProfile();
        if (! $profile) {
            $profile = $profiles->saveDraft(auth()->user(), null, $this->profileData());
            $this->profileId = $profile->id;
            $this->revision = $profile->revision;
        }

        $profile = $profiles->makeReady(
            auth()->user(),
            $profile,
            $this->profileData(),
            $this->revision,
            $this->sharingAcknowledged,
        );
        $this->revision = $profile->revision;
        $this->savedReady = true;
        $this->affectedCare = $attachments->affectedActiveCare($profile, auth()->user());
        session()->flash('status', $profile->displayName().'\'s care profile was updated.');
    }

    public function updateCurrentCare(CareRecipientProfileAttachmentService $attachments): void
    {
        $profile = $this->currentProfile();
        $this->authorize('update', $profile);
        $result = $attachments->applyLatestToActiveCare($profile, auth()->user());
        session()->flash('status', 'Current care was updated. '.$result['notified'].' assigned caregiver'.($result['notified'] === 1 ? '' : 's').' notified.');
        $this->redirectRoute('family.care-profiles.index', navigate: true);
    }

    public function finishWithoutUpdating(): void
    {
        session()->flash('status', 'Care profile saved. Current care is still using its previous information.');
        $this->redirectRoute('family.care-profiles.index', navigate: true);
    }

    public function render(
        CareRecipientProfilePresenter $presenter,
        FamilyAccountContext $context,
    ) {
        $profile = $this->currentProfile();
        $account = $context->account(auth()->user());

        return view('livewire.family.care-profile-editor', [
            'profile' => $profile,
            'candidatePreview' => $profile ? $presenter->familyPreview($profile) : null,
            'assignedPreview' => $profile ? $presenter->familyPreview($profile, true) : null,
            'communicationOptions' => CareRecipientProfile::COMMUNICATION_OPTIONS,
            'supportOptions' => CareRecipientProfile::SUPPORT_AREAS,
            'mobilityOptions' => CareRecipientProfile::MOBILITY_LEVELS,
            'comfortOptions' => CareRecipientProfile::COMFORT_NEEDS,
            'safetyOptions' => CareRecipientProfile::SAFETY_ITEMS,
            'qualityOptions' => CareRecipientProfile::CAREGIVER_QUALITIES,
            'ageOptions' => CareRecipientProfile::AGE_RANGES,
            'hasAcknowledged' => (bool) $profile?->sharing_acknowledged_at,
            'accountOwnerName' => $account->owner?->name,
        ]);
    }

    private function currentProfile(): ?CareRecipientProfile
    {
        if (! $this->profileId) {
            return null;
        }
        $account = app(FamilyAccountContext::class)->account(auth()->user());

        return CareRecipientProfile::query()->forFamilyAccount($account)->with(['updatedBy:id,name', 'latestReadyVersion'])->findOrFail($this->profileId);
    }

    private function loadProfile(CareRecipientProfile $profile): void
    {
        $this->profileId = $profile->id;
        $this->revision = $profile->revision;
        $this->recipientIsRequester = (bool) $profile->recipient_is_requester;
        $this->fullName = (string) $profile->full_name;
        $this->preferredName = (string) $profile->preferred_name;
        $this->dateOfBirth = $profile->date_of_birth?->toDateString() ?? '';
        $this->ageRange = (string) $profile->age_range;
        $this->pronouns = (string) $profile->pronouns;
        $this->relationshipToFamily = (string) $profile->relationship_to_family;
        $this->aboutThem = (string) $profile->about_them;
        $this->interestsAndComforts = (string) $profile->interests_and_comforts;
        $this->goodVisitNotes = (string) $profile->good_visit_notes;
        $this->communicationPreferences = $profile->communication_preferences ?? [];
        $this->communicationNotes = (string) $profile->communication_notes;
        $this->everydayHealthContext = (string) $profile->everyday_health_context;
        $this->supportAreas = $profile->support_areas ?? [];
        $this->supportDetails = $profile->support_details ?? [];
        $this->mobilityLevel = (string) $profile->mobility_level;
        $this->mobilityNotes = (string) $profile->mobility_notes;
        $this->routineNotes = (string) $profile->routine_notes;
        $this->foodAndDrinkNotes = (string) $profile->food_and_drink_notes;
        $this->personalCarePreferences = (string) $profile->personal_care_preferences;
        $this->sleepOvernightNotes = (string) $profile->sleep_overnight_notes;
        $this->comfortNeeds = $profile->comfort_needs ?? [];
        $this->distressTriggers = (string) $profile->distress_triggers;
        $this->calmingApproaches = (string) $profile->calming_approaches;
        $this->safetyItems = $profile->safety_items ?? [];
        $this->safetyNotes = (string) $profile->safety_notes;
        $this->caregiverQualityPreferences = $profile->caregiver_quality_preferences ?? [];
        $this->caregiverDoNotes = (string) $profile->caregiver_do_notes;
        $this->caregiverAvoidNotes = (string) $profile->caregiver_avoid_notes;
        $this->includeAdditionalContact = (bool) $profile->include_additional_contact;
        $this->additionalContactName = (string) $profile->additional_contact_name;
        $this->additionalContactRelationship = (string) $profile->additional_contact_relationship;
        $this->additionalContactPhone = (string) $profile->additional_contact_phone;
        $this->additionalContactEmail = (string) $profile->additional_contact_email;
        $this->assignedEscalationNotes = (string) $profile->assigned_escalation_notes;
        $this->sharingAcknowledged = (bool) $profile->sharing_acknowledged_at;
    }

    /** @return array<string, mixed> */
    private function profileData(): array
    {
        return [
            'recipient_is_requester' => $this->recipientIsRequester,
            'full_name' => $this->fullName,
            'preferred_name' => $this->preferredName,
            'date_of_birth' => $this->dateOfBirth,
            'age_range' => $this->ageRange,
            'pronouns' => $this->pronouns,
            'relationship_to_family' => $this->recipientIsRequester ? 'Self' : $this->relationshipToFamily,
            'about_them' => $this->aboutThem,
            'interests_and_comforts' => $this->interestsAndComforts,
            'good_visit_notes' => $this->goodVisitNotes,
            'communication_preferences' => $this->communicationPreferences,
            'communication_notes' => $this->communicationNotes,
            'everyday_health_context' => $this->everydayHealthContext,
            'support_areas' => $this->supportAreas,
            'support_details' => $this->supportDetails,
            'mobility_level' => $this->mobilityLevel,
            'mobility_notes' => $this->mobilityNotes,
            'routine_notes' => $this->routineNotes,
            'food_and_drink_notes' => $this->foodAndDrinkNotes,
            'personal_care_preferences' => $this->personalCarePreferences,
            'sleep_overnight_notes' => $this->sleepOvernightNotes,
            'comfort_needs' => $this->comfortNeeds,
            'distress_triggers' => $this->distressTriggers,
            'calming_approaches' => $this->calmingApproaches,
            'safety_items' => $this->safetyItems,
            'safety_notes' => $this->safetyNotes,
            'caregiver_quality_preferences' => $this->caregiverQualityPreferences,
            'caregiver_do_notes' => $this->caregiverDoNotes,
            'caregiver_avoid_notes' => $this->caregiverAvoidNotes,
            'include_additional_contact' => $this->includeAdditionalContact,
            'additional_contact_name' => $this->additionalContactName,
            'additional_contact_relationship' => $this->additionalContactRelationship,
            'additional_contact_phone' => $this->additionalContactPhone,
            'additional_contact_email' => $this->additionalContactEmail,
            'assigned_escalation_notes' => $this->assignedEscalationNotes,
        ];
    }

    /** @return array<string, mixed> */
    private function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => [
                'preferredName' => ['required', 'string', 'max:80'],
                'fullName' => ['nullable', 'string', 'max:120'],
                'dateOfBirth' => ['nullable', 'date', 'before_or_equal:today'],
                'ageRange' => ['nullable', Rule::in(array_keys(CareRecipientProfile::AGE_RANGES))],
                'pronouns' => ['nullable', 'string', 'max:40'],
                'relationshipToFamily' => ['nullable', 'string', 'max:120'],
                'aboutThem' => ['nullable', 'string', 'max:600'],
                'interestsAndComforts' => ['nullable', 'string', 'max:400'],
                'goodVisitNotes' => ['nullable', 'string', 'max:400'],
            ],
            2 => [
                'communicationPreferences' => ['array'],
                'communicationPreferences.*' => [Rule::in(array_keys(CareRecipientProfile::COMMUNICATION_OPTIONS))],
                'communicationNotes' => ['nullable', 'string', 'max:600'],
                'everydayHealthContext' => ['nullable', 'string', 'max:600'],
                'supportAreas' => ['array'],
                'supportAreas.*' => [Rule::in(array_keys(CareRecipientProfile::SUPPORT_AREAS))],
                'supportDetails.*' => ['nullable', 'string', 'max:300'],
                'mobilityLevel' => ['nullable', Rule::in(array_keys(CareRecipientProfile::MOBILITY_LEVELS))],
                'mobilityNotes' => ['nullable', 'string', 'max:500'],
            ],
            3 => [
                'routineNotes' => ['nullable', 'string', 'max:800'],
                'foodAndDrinkNotes' => ['nullable', 'string', 'max:500'],
                'personalCarePreferences' => ['nullable', 'string', 'max:500'],
                'sleepOvernightNotes' => ['nullable', 'string', 'max:600'],
                'comfortNeeds' => ['array'],
                'comfortNeeds.*' => [Rule::in(array_keys(CareRecipientProfile::COMFORT_NEEDS))],
                'distressTriggers' => ['nullable', 'string', 'max:500'],
                'calmingApproaches' => ['nullable', 'string', 'max:500'],
            ],
            4 => [
                'safetyItems' => ['array'],
                'safetyItems.*' => [Rule::in(array_keys(CareRecipientProfile::SAFETY_ITEMS))],
                'safetyNotes' => ['nullable', 'string', 'max:800'],
                'caregiverQualityPreferences' => ['array', 'max:5'],
                'caregiverQualityPreferences.*' => [Rule::in(array_keys(CareRecipientProfile::CAREGIVER_QUALITIES))],
                'caregiverDoNotes' => ['nullable', 'string', 'max:500'],
                'caregiverAvoidNotes' => ['nullable', 'string', 'max:500'],
                'additionalContactName' => ['nullable', 'required_if:includeAdditionalContact,true', 'string', 'max:120'],
                'additionalContactRelationship' => ['nullable', 'string', 'max:120'],
                'additionalContactPhone' => ['nullable', 'string', 'max:30'],
                'additionalContactEmail' => ['nullable', 'email:rfc', 'max:255'],
                'assignedEscalationNotes' => ['nullable', 'string', 'max:500'],
            ],
            default => [],
        };
    }

    /** @return array<string, mixed> */
    private function allRules(): array
    {
        return array_merge(
            $this->rulesForStep(1),
            $this->rulesForStep(2),
            $this->rulesForStep(3),
            $this->rulesForStep(4),
            $this->currentProfile()?->sharing_acknowledged_at ? [] : ['sharingAcknowledged' => ['accepted']],
        );
    }
}
