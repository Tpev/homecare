<?php

namespace App\Livewire\Caregiver;

use App\Models\CareRequest;
use App\Models\CareRequestInvitation;
use App\Models\CaregiverProfile;
use App\Models\FamilyCaregiverFavorite;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Support\FunnelTracker;
use App\Support\MarketplaceEvent;
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

    public function mount(string $slug): void
    {
        $this->caregiver = CaregiverProfile::query()
            ->with(['user','skills','languages','availabilities'])
            ->where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        $this->loadFamilyRequestOptions();
        $this->loadFavoriteState();
    }

    public function openInviteModal(): void
    {
        abort_unless(auth()->user()?->role === 'family', 403);
        $this->loadFamilyRequestOptions();
        $this->showInviteModal = true;
    }

    public function sendInvite(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'family', 403);

        $this->validate([
            'selectedCareRequestId' => [
                'required',
                Rule::exists('care_requests', 'id')->where(function ($query) use ($user) {
                    $query->where('family_user_id', $user->id)
                        ->whereIn('status', [CareRequest::STATUS_OPEN, CareRequest::STATUS_DRAFT]);
                }),
            ],
            'inviteMessage' => ['nullable', 'string', 'max:1200'],
        ]);

        if (! $this->caregiver->isMarketplaceReady()) {
            $this->addError('selectedCareRequestId', 'Caregiver profile is not yet marketplace-ready.');
            return;
        }

        $invitation = CareRequestInvitation::query()->updateOrCreate(
            [
                'care_request_id' => $this->selectedCareRequestId,
                'caregiver_user_id' => $this->caregiver->user_id,
            ],
            [
                'family_user_id' => $user->id,
                'status' => CareRequestInvitation::STATUS_PENDING,
                'message' => trim($this->inviteMessage) ?: null,
                'expires_at' => now()->addHours(72),
                'responded_at' => null,
            ]
        );

        app(MarketplaceNotificationService::class)->notify(
            recipients: $this->caregiver->user,
            eventKey: MarketplaceEvent::MATCHING_REQUEST_REMINDER,
            title: 'You have a new invitation',
            body: 'A family invited you to a care request. Please respond within 12 hours for best visibility.',
            url: route('caregiver.invitations.index'),
            payload: ['care_request_id' => (int) $this->selectedCareRequestId],
            subject: $invitation
        );

        FunnelTracker::track('care_request_invitation_sent', $user, $invitation, [
            'care_request_id' => $this->selectedCareRequestId,
            'caregiver_user_id' => $this->caregiver->user_id,
        ]);

        $this->reset(['showInviteModal', 'selectedCareRequestId', 'inviteMessage']);
        session()->flash('status', 'Invitation sent successfully.');
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
            ->whereIn('status', [CareRequest::STATUS_OPEN, CareRequest::STATUS_DRAFT])
            ->latest('created_at')
            ->get(['id', 'title'])
            ->map(fn (CareRequest $request) => ['label' => $request->title, 'value' => $request->id])
            ->all();
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
