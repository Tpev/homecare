<?php

namespace App\Livewire\Caregiver;

use App\Models\CaregiverProfile;
use App\Support\CaregiverOnboardingState;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

#[Layout('layouts.app')]
class InsuranceSetup extends Component
{
    use WithFileUploads;

    public CaregiverProfile $profile;
    public string $insurance_status = CaregiverProfile::INSURANCE_NOT_PROVIDED;
    public ?string $insurance_document_path = null;
    public $insurance_document = null;

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'caregiver', 403);

        $this->profile = CaregiverProfile::firstOrCreate(['user_id' => $user->id], ['status' => 'draft']);

        $this->insurance_status = (string) ($this->profile->insurance_status ?: CaregiverProfile::INSURANCE_NOT_PROVIDED);
        $this->insurance_document_path = $this->profile->insurance_document_path;

        app(CaregiverOnboardingState::class)->trackStepViewed($user, CaregiverOnboardingState::STEP_INSURANCE);
    }

    public function save(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'caregiver', 403);

        $rules = [
            'insurance_status' => ['required', Rule::in([
                CaregiverProfile::INSURANCE_NOT_PROVIDED,
                CaregiverProfile::INSURANCE_NO,
                CaregiverProfile::INSURANCE_YES,
            ])],
            'insurance_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:6144'],
        ];

        if ($this->insurance_status === CaregiverProfile::INSURANCE_YES && ! $this->insurance_document_path) {
            $rules['insurance_document'][] = 'required';
        }

        try {
            $this->validate($rules);
        } catch (ValidationException $exception) {
            app(CaregiverOnboardingState::class)->trackStepError(
                $user,
                CaregiverOnboardingState::STEP_INSURANCE,
                $exception->errors()
            );

            throw $exception;
        }

        if ($this->insurance_document) {
            $this->insurance_document_path = $this->insurance_document->store('caregiver-insurance', 'public');
        }

        if ($this->insurance_status !== CaregiverProfile::INSURANCE_YES) {
            $this->insurance_document_path = null;
        }

        $this->profile->update([
            'insurance_status' => $this->insurance_status,
            'insurance_document_path' => $this->insurance_document_path,
            'insurance_verified_at' => $this->insurance_status === CaregiverProfile::INSURANCE_YES && $this->insurance_document_path
                ? $this->profile->insurance_verified_at
                : null,
        ]);

        $onboardingState = app(CaregiverOnboardingState::class);
        $onboardingState->trackStepCompleted($user, CaregiverOnboardingState::STEP_INSURANCE);

        session()->flash('status', 'Insurance setup saved.');
        $this->redirect(route('caregiver.setup.index', absolute: false), navigate: true);
    }

    public function render()
    {
        $onboarding = app(CaregiverOnboardingState::class)->build(auth()->user());

        return view('livewire.caregiver.insurance-setup', compact('onboarding'));
    }
}
