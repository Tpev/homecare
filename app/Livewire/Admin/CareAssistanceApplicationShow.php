<?php

namespace App\Livewire\Admin;

use App\Models\CareAssistanceApplication;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class CareAssistanceApplicationShow extends Component
{
    #[Locked]
    public int $applicationId;

    #[Locked]
    public string $reviewVersion = '';

    public string $internalNotes = '';

    public function mount(CareAssistanceApplication $application): void
    {
        $this->authorizeAdministrator();
        $this->applicationId = $application->id;
        $this->syncApplication($application->fresh());
    }

    public function saveNotes(): void
    {
        $this->authorizeAdministrator();
        $this->persistReview();
        session()->flash('status', 'Private notes saved.');
    }

    public function updateStatus(string $status): void
    {
        $this->authorizeAdministrator();
        Validator::make(['status' => $status], [
            'status' => ['required', 'string', Rule::in(array_keys(CareAssistanceApplication::STATUS_LABELS))],
        ])->validate();

        $this->persistReview($status);
        session()->flash('status', 'Application marked '.strtolower(CareAssistanceApplication::STATUS_LABELS[$status]).'. Follow up with the applicant directly; no notification has been sent.');
    }

    public function reloadApplication(): void
    {
        $this->authorizeAdministrator();
        $this->syncApplication(CareAssistanceApplication::query()->findOrFail($this->applicationId));
        $this->resetValidation();
        session()->flash('status', 'The latest application and saved notes have been loaded.');
    }

    public function render(): View
    {
        $this->authorizeAdministrator();

        return view('livewire.admin.care-assistance-application-show', [
            'application' => CareAssistanceApplication::query()
                ->with('reviewer:id,name')->findOrFail($this->applicationId),
            'statusLabels' => CareAssistanceApplication::STATUS_LABELS,
        ])->title('Review care assistance | LoLo Care');
    }

    private function persistReview(?string $status = null): void
    {
        $validated = $this->validate(['internalNotes' => ['nullable', 'string', 'max:5000']]);

        $application = DB::transaction(function () use ($status, $validated): CareAssistanceApplication {
            $application = CareAssistanceApplication::query()->lockForUpdate()->findOrFail($this->applicationId);

            if (! hash_equals($this->reviewVersion, $this->versionFor($application))) {
                throw ValidationException::withMessages([
                    'review' => 'This application was changed in another session. Your notes have been kept here. Copy any unsaved text, then load the latest version before saving a decision.',
                ]);
            }

            $notes = trim($validated['internalNotes'] ?? '');
            $attributes = ['internal_notes' => $notes === '' ? null : $notes];

            if ($status !== null && $status !== $application->status) {
                $isDecision = in_array($status, ['approved', 'declined'], true);
                $attributes += [
                    'status' => $status,
                    'reviewed_by' => $isDecision ? auth()->id() : null,
                    'reviewed_at' => $isDecision ? now() : null,
                ];
            }

            $application->forceFill($attributes)->save();

            return $application->fresh();
        });

        $this->syncApplication($application);
        $this->resetValidation();
    }

    private function syncApplication(CareAssistanceApplication $application): void
    {
        $this->internalNotes = $application->internal_notes ?? '';
        $this->reviewVersion = $this->versionFor($application);
    }

    private function versionFor(CareAssistanceApplication $application): string
    {
        // Include review fields because some databases store updated_at with second precision.
        return hash('sha256', json_encode([
            $application->getRawOriginal('updated_at'),
            $application->status,
            $application->internal_notes,
            $application->reviewed_by,
            $application->getRawOriginal('reviewed_at'),
        ], JSON_THROW_ON_ERROR));
    }

    private function authorizeAdministrator(): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
    }
}
