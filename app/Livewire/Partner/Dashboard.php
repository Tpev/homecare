<?php

namespace App\Livewire\Partner;

use App\Models\PartnerProfile;
use App\Services\Partners\PartnerBillingService;
use App\Support\StaffAccess;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function boot(): void
    {
        $user = auth()->user()?->fresh();
        abort_unless($user?->role === 'partner' && StaffAccess::allowsUser($user), 403);
    }

    public function render()
    {
        $profile = PartnerProfile::query()->where('user_id', auth()->id())->firstOrFail();
        $billing = app(PartnerBillingService::class);
        $subscription = $billing->subscriptionFor($profile);
        $card = $profile->payment_method_summary;

        return view('livewire.partner.dashboard', [
            'profile' => $profile,
            'remainingCredits' => $profile->remainingLeadCredits(),
            'deliveredCount' => $profile->leads()->count(),
            'latestLeads' => $profile->leads()->latest('assigned_at')->latest('id')->limit(5)->get(),
            'subscription' => $subscription,
            'hasSubscription' => $subscription?->blocksNewCheckout() ?? false,
            'canPurchase' => $profile->status === PartnerProfile::STATUS_APPROVED,
            'canSubscribe' => $profile->status === PartnerProfile::STATUS_APPROVED && ! ($subscription?->blocksNewCheckout() ?? false),
            'packageOptions' => $billing->packageOptions(),
            'billing' => ['ready' => $card !== null, 'card' => $card],
            'billingUnavailable' => false,
        ]);
    }
}
