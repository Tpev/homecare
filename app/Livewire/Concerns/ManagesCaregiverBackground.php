<?php

namespace App\Livewire\Concerns;

use App\Models\CaregiverProfile;
use App\Services\Caregiver\CaregiverBackgroundService;
use Illuminate\Support\Facades\Validator;

trait ManagesCaregiverBackground
{
    public array $experienceOptions = [];

    public array $certificationOptions = [];

    public array $selectedExperienceTypes = [];

    public string $care_experience_notes = '';

    public array $selectedCertificationTypes = [];

    public array $certificationDetails = [];

    public array $certificationDocuments = [];

    public array $existingCertificationRecords = [];

    public array $certificationDocumentsToRemove = [];

    protected function initializeCaregiverBackground(CaregiverProfile $profile): void
    {
        $service = app(CaregiverBackgroundService::class);
        $this->experienceOptions = $service->experienceOptions();
        $this->certificationOptions = $service->certificationOptions();
        $state = $service->formState($profile);

        $this->selectedExperienceTypes = $state['selected_experiences'];
        $this->care_experience_notes = $state['experience_notes'];
        $this->selectedCertificationTypes = $state['selected_certifications'];
        $this->certificationDetails = $state['certification_details'];
        $this->existingCertificationRecords = $state['existing_records'];
    }

    public function toggleExperience(string $value): void
    {
        $none = CaregiverBackgroundService::NONE;
        if ($value === $none) {
            $this->selectedExperienceTypes = [$none];

            return;
        }

        $id = (int) $value;
        if (! collect($this->experienceOptions)->contains(fn (array $option) => (int) $option['id'] === $id)) {
            return;
        }

        $selected = app(CaregiverBackgroundService::class)->numericSelections($this->selectedExperienceTypes);
        $this->selectedExperienceTypes = in_array($id, $selected, true)
            ? array_values(array_diff($selected, [$id]))
            : [...$selected, $id];
    }

    public function updatedSelectedExperienceTypes(mixed $value): void
    {
        $this->selectedExperienceTypes = $this->normalizeExclusiveSelection((array) $value);
    }

    public function toggleCertification(string $value): void
    {
        $none = CaregiverBackgroundService::NONE;
        if ($value === $none) {
            $this->selectedCertificationTypes = [$none];

            return;
        }

        $id = (int) $value;
        if (! collect($this->certificationOptions)->contains(fn (array $option) => (int) $option['id'] === $id)) {
            return;
        }

        $selected = app(CaregiverBackgroundService::class)->numericSelections($this->selectedCertificationTypes);
        if (in_array($id, $selected, true)) {
            $this->selectedCertificationTypes = array_values(array_diff($selected, [$id]));
            unset($this->certificationDocuments[$id]);
        } else {
            $this->selectedCertificationTypes = [...$selected, $id];
            $this->certificationDetails[$id] ??= [
                'custom_name' => '',
                'issuer' => '',
                'issuing_state' => '',
                'expires_at' => '',
            ];
        }
    }

    public function updatedSelectedCertificationTypes(mixed $value): void
    {
        $normalized = $this->normalizeExclusiveSelection((array) $value);
        $selectedIds = app(CaregiverBackgroundService::class)->numericSelections($normalized);
        foreach (array_keys($this->certificationDocuments) as $typeId) {
            if (! in_array((int) $typeId, $selectedIds, true)) {
                unset($this->certificationDocuments[$typeId]);
            }
        }
        $this->selectedCertificationTypes = $normalized;
    }

    public function removeCertificationDocument(int $typeId): void
    {
        if (! ($this->existingCertificationRecords[$typeId]['has_document'] ?? false)) {
            return;
        }

        $this->certificationDocumentsToRemove[$typeId] = true;
        unset($this->certificationDocuments[$typeId]);
    }

    public function undoCertificationDocumentRemoval(int $typeId): void
    {
        unset($this->certificationDocumentsToRemove[$typeId]);
    }

    protected function validateCaregiverBackground(bool $required): void
    {
        if (! $required && ! $this->caregiverBackgroundWasStarted()) {
            return;
        }

        $experienceIds = collect($this->experienceOptions)->pluck('id')->map(fn ($id) => (string) $id)->all();
        $certificationIds = collect($this->certificationOptions)->pluck('id')->map(fn ($id) => (string) $id)->all();
        $selectedCertificationIds = app(CaregiverBackgroundService::class)
            ->numericSelections($this->selectedCertificationTypes);

        $rules = [
            'selectedExperienceTypes' => ['required', 'array', 'min:1'],
            'selectedExperienceTypes.*' => [
                function (string $attribute, mixed $value, \Closure $fail) use ($experienceIds): void {
                    if ((string) $value !== CaregiverBackgroundService::NONE && ! in_array((string) $value, $experienceIds, true)) {
                        $fail('Select a valid care experience option.');
                    }
                },
            ],
            'care_experience_notes' => ['nullable', 'string', 'max:1000'],
            'selectedCertificationTypes' => ['required', 'array', 'min:1'],
            'selectedCertificationTypes.*' => [
                function (string $attribute, mixed $value, \Closure $fail) use ($certificationIds): void {
                    if ((string) $value !== CaregiverBackgroundService::NONE && ! in_array((string) $value, $certificationIds, true)) {
                        $fail('Select a valid certification or training option.');
                    }
                },
            ],
        ];

        foreach ($selectedCertificationIds as $typeId) {
            $option = collect($this->certificationOptions)->first(fn (array $row) => (int) $row['id'] === $typeId);
            $rules["certificationDetails.$typeId.custom_name"] = [
                ($option['slug'] ?? null) === 'other' ? 'required' : 'nullable',
                'string',
                'max:160',
            ];
            $rules["certificationDetails.$typeId.issuer"] = ['nullable', 'string', 'max:160'];
            $rules["certificationDetails.$typeId.issuing_state"] = ['nullable', 'string', 'size:2'];
            $rules["certificationDetails.$typeId.expires_at"] = ['nullable', 'date'];
            $rules["certificationDocuments.$typeId"] = [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'extensions:pdf,jpg,jpeg,png',
                'max:6144',
            ];
        }

        $this->resetValidation(array_keys($rules));

        $validator = Validator::make($this->caregiverBackgroundValidationData(), $rules, [
            'selectedExperienceTypes.required' => 'Choose at least one experience option or select that you do not have specialized experience yet.',
            'selectedCertificationTypes.required' => 'Choose at least one credential option or select that you have no current certifications.',
            '*.custom_name.required' => 'Enter the name of the other certification or training.',
            '*.issuing_state.size' => 'Use the two-letter state abbreviation.',
            '*.mimes' => 'Supporting documents must be PDF, JPG, or PNG files.',
            '*.extensions' => 'Supporting documents must use a PDF, JPG, JPEG, or PNG file extension.',
            '*.max' => 'Supporting documents may not be larger than 6 MB.',
        ]);

        $validator->after(function ($validator): void {
            if (in_array(CaregiverBackgroundService::NONE, $this->selectedExperienceTypes, true)
                && count($this->selectedExperienceTypes) > 1) {
                $validator->errors()->add('selectedExperienceTypes', 'The “no specialized experience yet” option cannot be combined with other experience.');
            }
            if (in_array(CaregiverBackgroundService::NONE, $this->selectedCertificationTypes, true)
                && count($this->selectedCertificationTypes) > 1) {
                $validator->errors()->add('selectedCertificationTypes', '“No current certifications” cannot be combined with another credential.');
            }
        });

        $validator->validate();
    }

    protected function caregiverBackgroundWasStarted(): bool
    {
        return $this->selectedExperienceTypes !== []
            || $this->selectedCertificationTypes !== []
            || filled($this->care_experience_notes)
            || $this->certificationDocuments !== []
            || $this->existingCertificationRecords !== [];
    }

    /** @return array<string, mixed> */
    protected function caregiverBackgroundValidationData(): array
    {
        return [
            'selectedExperienceTypes' => $this->selectedExperienceTypes,
            'care_experience_notes' => $this->care_experience_notes,
            'selectedCertificationTypes' => $this->selectedCertificationTypes,
            'certificationDetails' => $this->certificationDetails,
            'certificationDocuments' => $this->certificationDocuments,
        ];
    }

    /** @return array<int, int> */
    protected function selectedCertificationTypeIds(): array
    {
        return app(CaregiverBackgroundService::class)->numericSelections($this->selectedCertificationTypes);
    }

    /** @param array<int|string> $values @return array<int, int|string> */
    private function normalizeExclusiveSelection(array $values): array
    {
        $values = array_values(array_unique($values, SORT_REGULAR));
        if (! in_array(CaregiverBackgroundService::NONE, $values, true) || count($values) === 1) {
            return $values;
        }

        return (string) end($values) === CaregiverBackgroundService::NONE
            ? [CaregiverBackgroundService::NONE]
            : array_values(array_filter(
                $values,
                fn ($value) => (string) $value !== CaregiverBackgroundService::NONE,
            ));
    }
}
