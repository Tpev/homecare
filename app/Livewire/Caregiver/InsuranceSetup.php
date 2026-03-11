<?php

namespace App\Livewire\Caregiver;

use App\Models\CaregiverProfile;
use Illuminate\Validation\Rule;
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
    }

    public function save(): void
    {
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

        $this->validate($rules);

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

        session()->flash('status', 'Insurance setup saved.');
    }

    public function render()
    {
        return view('livewire.caregiver.insurance-setup');
    }
}

