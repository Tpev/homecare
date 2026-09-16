<?php

namespace App\Livewire\Admin;

use App\Models\FamilyAccountActivityLog;
use App\Models\FamilyOnboarding;
use App\Models\FamilyOnboardingDelivery;
use App\Models\FamilyWelcomeVisit;
use App\Services\Family\FamilyOnboardingDeliveryService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class FamilyOnboardingShow extends Component
{
    #[Locked]
    public int $onboardingId;

    #[Locked]
    public int $visitRevision = 0;

    public string $visitStatus = 'requested';

    public string $confirmedStart = '';

    public string $staffNote = '';

    public bool $textConfirmed = false;

    public string $deliveryReason = '';

    public function mount(FamilyOnboarding $onboarding): void
    {
        $this->authorizeAdmin();
        $this->onboardingId = $onboarding->id;
        if ($visit = $onboarding->welcomeVisit) {
            $this->visitStatus = $visit->status;
            $this->visitRevision = $visit->revision;
            $this->staffNote = $visit->staff_note ?? '';
            $this->confirmedStart = $visit->confirmed_start_at?->setTimezone($visit->timezone)->format('Y-m-d\TH:i') ?? '';
        }
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);
    }

    public function saveVisit(): void
    {
        $this->authorizeAdmin();
        $this->validate([
            'visitStatus' => ['required', Rule::in(['requested', 'contacted', 'confirmed', 'completed', 'cancelled', 'unavailable'])],
            'confirmedStart' => [Rule::requiredIf(in_array($this->visitStatus, ['confirmed', 'completed'], true)), 'nullable', 'date_format:Y-m-d\TH:i'],
            'staffNote' => ['nullable', 'string', 'max:2000'],
            'textConfirmed' => $this->visitStatus === 'confirmed' ? ['accepted'] : ['boolean'],
        ], ['textConfirmed.accepted' => 'Confirm the time with the family by text before marking this visit confirmed.']);
        DB::transaction(function () {
            $visit = FamilyWelcomeVisit::query()->where('family_onboarding_id', $this->onboardingId)->lockForUpdate()->firstOrFail();
            if ($visit->revision !== $this->visitRevision) {
                throw ValidationException::withMessages(['visitStatus' => 'Another team member updated this visit. Reload before saving.']);
            }
            $confirmed = $this->confirmedStart !== '' ? CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $this->confirmedStart, $visit->timezone)->utc() : null;
            if ($this->visitStatus === 'confirmed' && $confirmed?->isPast()) {
                throw ValidationException::withMessages(['confirmedStart' => 'Choose a future time.']);
            }
            $previous = $visit->status;
            $visit->update([
                'status' => $this->visitStatus, 'confirmed_start_at' => $confirmed, 'staff_note' => trim($this->staffNote),
                'handled_by_user_id' => auth()->id(), 'handled_at' => now(), 'revision' => $visit->revision + 1,
            ]);
            $this->visitRevision = $visit->revision;
            $this->audit('welcome_visit_updated', ['visit_id' => $visit->id, 'from' => $previous, 'to' => $visit->status]);
        });
        session()->flash('status', 'Welcome visit updated.');
    }

    public function resolveDelivery(int $deliveryId, string $resolution): void
    {
        $this->authorizeAdmin();
        $this->validate(['deliveryReason' => ['required', 'string', 'min:10', 'max:500']]);
        abort_unless(in_array($resolution, ['retry', 'accepted'], true), 422);
        DB::transaction(function () use ($deliveryId, $resolution) {
            $delivery = FamilyOnboardingDelivery::query()->where('family_onboarding_id', $this->onboardingId)->lockForUpdate()->findOrFail($deliveryId);
            abort_unless(in_array($delivery->status, ['unconfirmed', 'failed'], true), 409);
            $delivery->update([
                'status' => $resolution === 'accepted' ? 'accepted' : 'pending',
                'accepted_at' => $resolution === 'accepted' ? now() : null,
                'attempts' => $resolution === 'retry' ? 0 : $delivery->attempts,
                'next_attempt_at' => null, 'queued_at' => null, 'last_error_code' => null,
            ]);
            $this->audit('onboarding_email_reconciled', ['delivery_id' => $delivery->id, 'resolution' => $resolution, 'reason' => $this->deliveryReason]);
            if ($resolution === 'retry') {
                DB::afterCommit(fn () => app(FamilyOnboardingDeliveryService::class)->dispatch($delivery->id));
            }
        });
        $this->deliveryReason = '';
        session()->flash('status', 'Email delivery record updated.');
    }

    private function audit(string $action, array $metadata): void
    {
        FamilyAccountActivityLog::query()->create([
            'family_account_id' => FamilyOnboarding::query()->findOrFail($this->onboardingId)->family_account_id,
            'actor_user_id' => auth()->id(), 'action' => $action, 'metadata' => $metadata,
        ]);
    }

    public function render()
    {
        $this->authorizeAdmin();

        return view('livewire.admin.family-onboarding-show', [
            'onboarding' => FamilyOnboarding::query()->with(['initiatedBy', 'welcomeVisit', 'deliveries'])->findOrFail($this->onboardingId),
        ]);
    }
}
