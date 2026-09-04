<?php

namespace App\Livewire\Caregiver;

use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\FamilyCaregiverFavorite;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Marketplace\CaregiverInvitationDiscoveryService;
use App\Services\Marketplace\CareRequestInvitationService;
use App\Support\CaregiverPrelaunch;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ShowCaregiver extends Component
{
    public CaregiverProfile $caregiver;

    public bool $showInviteModal = false;

    public ?int $selectedCareRequestId = null;

    public string $inviteMessage = '';

    public array $familyRequestOptions = [];

    public bool $isFavorite = false;

    public ?int $contextCareRequestId = null;

    public ?array $contextRequestSummary = null;

    public ?array $contextRelationship = null;

    public bool $inviteIsReinvite = false;

    public function mount(string $slug): void
    {
        abort_if(CaregiverPrelaunch::enabled(), 404);

        $this->caregiver = CaregiverProfile::query()
            ->with(['user', 'skills', 'languages', 'availabilities', 'careExperiences', 'publicCertifications'])
            ->where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        $this->loadRequestContext();
        $this->loadFamilyRequestOptions();
        $this->loadFavoriteState();
    }

    public function openInviteModal(): void
    {
        abort_unless(auth()->user()?->role === 'family', 403);
        $this->resetValidation(['selectedCareRequestId', 'inviteMessage']);

        if ($this->contextCareRequestId) {
            $this->selectedCareRequestId = $this->contextCareRequestId;
            $this->inviteMessage = 'Hi '.str($this->caregiver->user?->name)->before(' ').', we would like to invite you to review “'.$this->contextRequestSummary['title'].'”.';
            $this->inviteIsReinvite = (bool) ($this->contextRelationship['can_reinvite'] ?? false);
        } else {
            $this->loadFamilyRequestOptions();

            $requestIds = collect($this->familyRequestOptions)
                ->pluck('value')
                ->map(fn ($id): int => (int) $id);

            if ($requestIds->count() === 1) {
                $this->selectedCareRequestId = $requestIds->first();
            } elseif (! $requestIds->contains($this->selectedCareRequestId)) {
                $this->selectedCareRequestId = null;
            }
        }
        $this->showInviteModal = true;
    }

    public function sendInvite(): void
    {
        if (CaregiverPrelaunch::enabled()) {
            session()->flash('status', CaregiverPrelaunch::message());

            return;
        }

        $user = auth()->user();
        abort_unless($user && $user->role === 'family', 403);

        if ($this->contextCareRequestId) {
            $this->selectedCareRequestId = $this->contextCareRequestId;
        }

        $this->validate([
            'selectedCareRequestId' => [
                'required',
                Rule::exists('care_requests', 'id')->where(function ($query) use ($user) {
                    $query->where('family_account_id', app(FamilyAccountContext::class)->account($user)->id)
                        ->where('status', CareRequest::STATUS_OPEN);
                }),
            ],
            'inviteMessage' => ['nullable', 'string', 'max:1200'],
        ]);

        $request = CareRequest::query()->findOrFail($this->selectedCareRequestId);
        $result = app(CareRequestInvitationService::class)->send(
            family: $user,
            careRequest: $request,
            caregiver: $this->caregiver->user,
            message: $this->inviteMessage,
            reinvite: $this->contextCareRequestId ? $this->inviteIsReinvite : false,
            source: $this->contextCareRequestId ? 'contextual_profile' : 'caregiver_profile',
        );

        if (! $result->sentNow) {
            $this->addError('selectedCareRequestId', $result->message);

            return;
        }

        $this->showInviteModal = false;
        $this->inviteMessage = '';
        $this->inviteIsReinvite = false;
        if ($this->contextCareRequestId) {
            $this->refreshContextRelationship();
        } else {
            $this->selectedCareRequestId = null;
        }
        session()->flash('status', $result->message);
    }

    public function toggleFavorite(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'family', 403);

        $existing = FamilyCaregiverFavorite::query()
            ->forFamilyAccount(app(FamilyAccountContext::class)->account($user))
            ->where('caregiver_user_id', $this->caregiver->user_id)
            ->first();

        if ($existing) {
            $existing->delete();
            $this->isFavorite = false;
            session()->flash('status', 'Removed from favorites.');

            return;
        }

        FamilyCaregiverFavorite::query()->create([
            ...app(FamilyAccountContext::class)->ownershipAttributes($user),
            'caregiver_user_id' => $this->caregiver->user_id,
        ]);

        $this->isFavorite = true;
        session()->flash('status', 'Saved to favorites.');
    }

    private function loadFamilyRequestOptions(): void
    {
        $user = auth()->user();
        if (! $user || $user->role !== 'family') {
            $this->familyRequestOptions = [];

            return;
        }

        $this->familyRequestOptions = CareRequest::query()
            ->forFamilyAccount(app(FamilyAccountContext::class)->account($user))
            ->where('status', CareRequest::STATUS_OPEN)
            ->acceptingApplications()
            ->whereDoesntHave(
                'applications',
                fn ($query) => $query->where('status', CareRequestApplication::STATUS_HIRED),
            )
            ->latest('created_at')
            ->get(['id', 'title', 'request_type', 'requested_start_at', 'requested_end_at'])
            ->map(function (CareRequest $request): array {
                $label = $request->title;

                if ($request->request_type === CareRequest::TYPE_ONE_TIME && $request->requested_start_at) {
                    $end = $request->requested_end_at
                        ? ($request->requested_start_at->isSameDay($request->requested_end_at)
                            ? $request->requested_end_at->format('g:i A')
                            : $request->requested_end_at->format('D, M j · g:i A'))
                        : null;
                    $schedule = $request->requested_start_at->format('D, M j · g:i A')
                        .($end ? '–'.$end : '');
                    $label = $schedule.' — '.$label;
                }

                return ['label' => $label, 'value' => $request->id];
            })
            ->all();
    }

    private function loadRequestContext(): void
    {
        $contextId = (int) request()->query('careRequest', 0);
        $user = auth()->user();
        if ($contextId <= 0 || ! $user || $user->role !== 'family') {
            return;
        }

        $request = CareRequest::query()
            ->whereKey($contextId)
            ->forFamilyAccount(app(FamilyAccountContext::class)->account($user))
            ->where('status', CareRequest::STATUS_OPEN)
            ->acceptingApplications()
            ->first();

        if (! $request) {
            return;
        }

        $this->contextCareRequestId = $request->id;
        $this->selectedCareRequestId = $request->id;
        $this->contextRequestSummary = [
            'title' => $request->title,
            'schedule' => $request->request_type === CareRequest::TYPE_ONE_TIME
                ? collect([$request->requested_start_at?->format('M j, Y g:i A'), $request->requested_end_at?->format('g:i A')])->filter()->implode(' – ')
                : 'Recurring · '.$request->recurringScheduleLabel(),
            'location' => collect([$request->city, $request->state])->filter()->implode(', '),
            'back_url' => route('family.requests.show', ['careRequest' => $request->id, 'tab' => 'applicants']),
        ];

        $this->refreshContextRelationship($request);
    }

    private function refreshContextRelationship(?CareRequest $request = null): void
    {
        $user = auth()->user();
        if (! $this->contextCareRequestId || ! $user) {
            $this->contextRelationship = null;

            return;
        }

        $request ??= CareRequest::query()
            ->whereKey($this->contextCareRequestId)
            ->forFamilyAccount(app(FamilyAccountContext::class)->account($user))
            ->where('status', CareRequest::STATUS_OPEN)
            ->acceptingApplications()
            ->first();

        $this->contextRelationship = $request
            ? app(CaregiverInvitationDiscoveryService::class)->caregiver($request, $user, (int) $this->caregiver->user_id)
            : null;
    }

    private function loadFavoriteState(): void
    {
        $user = auth()->user();
        if (! $user || $user->role !== 'family') {
            $this->isFavorite = false;

            return;
        }

        $this->isFavorite = FamilyCaregiverFavorite::query()
            ->forFamilyAccount(app(FamilyAccountContext::class)->account($user))
            ->where('caregiver_user_id', $this->caregiver->user_id)
            ->exists();
    }

    public function render()
    {
        return view('livewire.caregiver.show-caregiver');
    }
}
