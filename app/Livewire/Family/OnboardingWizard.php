<?php

namespace App\Livewire\Family;

use App\Services\Family\FamilyOnboardingService;
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
            $this->redirectRoute('family.requests.index');

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
                $this->redirectRoute('family.requests.create');

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

    public function render()
    {
        return view('livewire.family.onboarding-wizard', [
            'ranges' => FamilyOnboardingService::RANGES,
            'relationships' => FamilyOnboardingService::RELATIONSHIPS,
            'timezone' => app(FamilyOnboardingService::class)->timezone(),
            'minimumDate' => now()->addSeconds(86400)->setTimezone(app(FamilyOnboardingService::class)->timezone())->toDateString(),
        ]);
    }
}
