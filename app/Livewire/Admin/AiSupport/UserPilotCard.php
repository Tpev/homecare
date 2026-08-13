<?php

namespace App\Livewire\Admin\AiSupport;

use App\Models\AiSupportPilotGrant;
use App\Models\User;
use App\Services\AiSupport\AiSupportEligibilityService;
use App\Services\AiSupport\AiSupportPilotGrantService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class UserPilotCard extends Component
{
    #[Locked]
    public int $targetUserId;

    public string $grantBundleKey = '';

    public string $grantStartsAt = '';

    public string $grantExpiresAt = '';

    public bool $grantNoExpiry = false;

    public bool $noExpiryAcknowledged = false;

    public string $grantReason = '';

    public bool $grantImpactConfirmed = false;

    #[Locked]
    public string $grantRequestKey = '';

    public string $revocationReason = '';

    public bool $revocationImpactConfirmed = false;

    public function mount(User $user): void
    {
        abort_unless(auth()->user()?->canViewAiSupportPilot(), 403);
        $this->targetUserId = $user->id;
        $this->grantBundleKey = $user->role === 'caregiver'
            ? 'caregiver_support_v1'
            : 'family_support_v1';
        $start = CarbonImmutable::now()->startOfMinute();
        $this->grantStartsAt = $start->format('Y-m-d\TH:i');
        $this->grantExpiresAt = $start
            ->addDays((int) config('ai_support.default_grant_days', 14))
            ->format('Y-m-d\TH:i');
        $this->grantRequestKey = (string) Str::uuid();
    }

    public function enablePilot(AiSupportPilotGrantService $grants): void
    {
        abort_unless(auth()->user()?->canManageAiSupportPilot(), 403);
        $validated = $this->validate([
            'grantBundleKey' => ['required', 'string', 'max:120'],
            'grantStartsAt' => ['required', 'date'],
            'grantExpiresAt' => [$this->grantNoExpiry ? 'nullable' : 'required', 'date'],
            'grantNoExpiry' => ['boolean'],
            'noExpiryAcknowledged' => [$this->grantNoExpiry ? 'accepted' : 'nullable'],
            'grantReason' => ['required', 'string', 'min:5', 'max:500'],
            'grantImpactConfirmed' => ['accepted'],
            'grantRequestKey' => ['required', 'uuid'],
        ]);

        $grants->grant(
            actor: auth()->user(),
            target: $this->targetUser,
            bundleKey: $validated['grantBundleKey'],
            startsAt: CarbonImmutable::parse($validated['grantStartsAt']),
            expiresAt: $this->grantNoExpiry ? null : CarbonImmutable::parse($validated['grantExpiresAt']),
            reason: $validated['grantReason'],
            requestKey: $validated['grantRequestKey'],
            noExpiryAcknowledged: $this->grantNoExpiry && $this->noExpiryAcknowledged,
        );

        $this->grantRequestKey = (string) Str::uuid();
        $this->reset(['grantReason', 'grantImpactConfirmed', 'noExpiryAcknowledged']);
        session()->flash('status', 'Exact-user AI pilot grant recorded. It does not extend to any family or account member.');
    }

    public function disablePilot(AiSupportPilotGrantService $grants): void
    {
        abort_unless(auth()->user()?->canManageAiSupportPilot(), 403);
        $validated = $this->validate([
            'revocationReason' => ['required', 'string', 'min:5', 'max:500'],
            'revocationImpactConfirmed' => ['accepted'],
        ]);

        $current = $this->currentGrant;
        abort_unless($current, 404);
        $grants->revoke(auth()->user(), $current, $validated['revocationReason']);
        $this->reset(['revocationReason', 'revocationImpactConfirmed']);
        session()->flash('status', 'AI pilot access revoked immediately. Human support remains available.');
    }

    public function getTargetUserProperty(): User
    {
        return User::query()->findOrFail($this->targetUserId);
    }

    public function getCurrentGrantProperty(): ?AiSupportPilotGrant
    {
        return AiSupportPilotGrant::query()
            ->where('user_id', $this->targetUserId)
            ->notRevoked()
            ->where(fn ($window) => $window
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->latest('starts_at')
            ->first();
    }

    public function render(AiSupportEligibilityService $eligibility): View
    {
        $history = AiSupportPilotGrant::query()
            ->with(['grantedBy:id,name', 'revokedBy:id,name'])
            ->where('user_id', $this->targetUserId)
            ->latest('created_at')
            ->limit(20)
            ->get();

        return view('livewire.admin.ai-support.user-pilot-card', [
            'targetUser' => $this->targetUser,
            'currentGrant' => $this->currentGrant,
            'history' => $history,
            'eligibility' => $eligibility->evaluate($this->targetUser),
            'bundles' => collect((array) config('ai_support.bundles', []))
                ->filter(fn (array $bundle): bool => in_array($this->targetUser->role, (array) $bundle['roles'], true)),
        ]);
    }
}
