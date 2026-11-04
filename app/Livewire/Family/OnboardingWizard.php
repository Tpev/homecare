<?php

namespace App\Livewire\Family;

use App\Services\CareHours\CareHourAccess;
use App\Services\Family\FamilyOnboardingService;
use App\Support\CareHourGiftClaimIntent;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

#[Layout('layouts.family-onboarding')]
class OnboardingWizard extends Component
{
    #[Locked]
    public int $step = 1;

    #[Locked]
    public int $revision = 0;

    public array $form = [];

    public function mount(): void
    {
        $this->reloadSaved();
    }

    public function reloadSaved(): void
    {
        $record = app(FamilyOnboardingService::class)->forOwner(auth()->user());
        if (! $record || $record->status !== 'in_progress') {
            $this->finishNavigation('family.requests.index');

            return;
        }
        $this->form = $record->draft;
        $this->step = $record->current_step;
        $this->revision = $record->revision;
        $this->resetValidation();
    }

    public function next(): void
    {
        $this->save(false);
    }

    public function back(): void
    {
        $this->save(true);
    }

    private function save(bool $back): void
    {
        $this->resetValidation();
        $service = app(FamilyOnboardingService::class);
        try {
            if ($this->step === 5 && ! $back) {
                $service->complete(auth()->user(), $this->revision);
                $this->finishNavigation('family.requests.create');

                return;
            }
            $record = $service->saveStep(auth()->user(), $this->form, $this->step, $this->revision, $back);
            $this->step = $record->current_step;
            $this->revision = $record->revision;
            $this->form = $record->draft;
            $this->dispatch('onboarding-step-changed');
        } catch (ValidationException $exception) {
            if ($this->step === 5 && ! isset($exception->errors()['conflict'])) {
                $record = $service->saveStep(auth()->user(), [], 5, $this->revision, true);
                $this->step = $record->current_step;
                $this->revision = $record->revision;
            }
            $this->setErrorBag($exception->errors());
            $this->dispatch('onboarding-validation-failed');
        } catch (\Illuminate\Auth\Access\AuthorizationException|\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Family onboarding save failed', ['user_id' => auth()->id(), 'exception_type' => $exception::class]);
            $this->addError('save', 'We could not save your answers. Please try again. Your current answers are still here.');
            $this->dispatch('onboarding-validation-failed');
        }
    }

    private function prepaidPurchasePending(): bool
    {
        // Only this server-owned destination is eligible. General signup,
        // request drafts, and paid gift claims keep their existing behavior.
        return ! CareHourGiftClaimIntent::active()
            && session('url.intended') === route('family.care-hours.buy')
            && auth()->user()?->role === 'family'
            && app(CareHourAccess::class)->canBuy(auth()->user())
            && config('care_hours.enrollment_enabled', false);
    }

    private function finishNavigation(string $defaultRoute): void
    {
        if ($this->prepaidPurchasePending()) {
            session()->forget('url.intended');
            $this->redirectRoute('family.care-hours.buy');

            return;
        }
        $this->redirectRoute($defaultRoute);
    }

    public function render()
    {
        return view('livewire.family.onboarding-wizard', [
            'ranges' => FamilyOnboardingService::RANGES,
            'relationships' => FamilyOnboardingService::RELATIONSHIPS,
            'timezone' => app(FamilyOnboardingService::class)->timezone(),
            'minimumDate' => now()->addSeconds(86400)->setTimezone(app(FamilyOnboardingService::class)->timezone())->toDateString(),
            'prepaidPurchasePending' => $this->prepaidPurchasePending(),
        ]);
    }
}
