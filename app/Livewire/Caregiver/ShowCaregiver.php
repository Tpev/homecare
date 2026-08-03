<?php

namespace App\Livewire\Caregiver;

use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\FamilyCaregiverFavorite;
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
            ->with(['user', 'skills', 'languages', 'availabilities', 'careExperiences', 'certifications.type'])
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
        if ($this->contextCareRequestId) {
            $this->selectedCareRequestId = $this->contextCareRequestId;
            $this->inviteMessage = 'Hi '.str($this->caregiver->user?->name)->before(' ').', we would like to invite you to review “'.$this->contextRequestSummary['title'].'”.';
            $this->inviteIsReinvite = (bool) ($this->contextRelationship['can_reinvite'] ?? false);
        } else {
            $this->loadFamilyRequestOptions();
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
                    $query->where('family_user_id', $user->id)
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
            ->where('family_user_id', $user->id)
            ->where('caregiver_user_id', $this->caregiver->user_id)
            ->first();

        if ($existing) {
            $existing->delete();
            $this->isFavorite = false;
            session()->flash('status', 'Removed from favorites.');

            return;
        }

        FamilyCaregiverFavorite::query()->create([
            'family_user_id' => $user->id,
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
            ->where('family_user_id', $user->id)
            ->where('status', CareRequest::STATUS_OPEN)
            ->latest('created_at')
            ->get(['id', 'title'])
            ->map(fn (CareRequest $request) => ['label' => $request->title, 'value' => $request->id])
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
            ->where('family_user_id', $user->id)
            ->where('status', CareRequest::STATUS_OPEN)
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
                : 'Recurring '.collect($request->recurring_days ?? [])->map(fn ($day) => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][(int) $day] ?? null)->filter()->implode(', ').' · '.substr((string) $request->recurring_start_time, 0, 5).'–'.substr((string) $request->recurring_end_time, 0, 5),
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
            ->where('family_user_id', $user->id)
            ->where('status', CareRequest::STATUS_OPEN)
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
            ->where('family_user_id', $user->id)
            ->where('caregiver_user_id', $this->caregiver->user_id)
            ->exists();
    }

    public function render()
    {
        return view('livewire.caregiver.show-caregiver');
    }
}
