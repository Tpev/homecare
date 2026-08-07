<?php

namespace App\Livewire\Family;

use App\Models\FamilyAccountInvitation;
use App\Models\FamilyAccountMember;
use App\Services\FamilyAccounts\FamilyAccountAccessService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\FamilyAccounts\FamilyAccountInvitationService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class FamilyAccess extends Component
{
    public bool $showInviteForm = false;

    public string $inviteEmail = '';

    public ?int $managedInvitationId = null;

    public ?int $removingMemberId = null;

    public bool $confirmLeave = false;

    public function sendInvitation(FamilyAccountInvitationService $invitations): void
    {
        $this->validate(['inviteEmail' => ['required', 'string', 'email:rfc', 'max:255']]);
        $issued = $invitations->send(auth()->user(), $this->inviteEmail, request()->ip());

        $this->reset(['inviteEmail', 'showInviteForm', 'managedInvitationId']);
        session()->flash('status', $issued['delivered']
            ? 'Invitation sent to '.$issued['invitation']->email_normalized.'.'
            : 'The invitation is saved, but the email could not be sent. Open Manage invitation and choose Send again.');
    }

    public function resendInvitation(int $invitationId, FamilyAccountInvitationService $invitations): void
    {
        $invitation = $this->ownedInvitation($invitationId);
        $issued = $invitations->resend(auth()->user(), $invitation, request()->ip());
        $this->managedInvitationId = null;
        session()->flash('status', $issued['delivered']
            ? 'A new private invitation was sent to '.$invitation->email_normalized.'.'
            : 'The new invitation is saved, but the email could not be sent. Please try Send again.');
    }

    public function cancelInvitation(int $invitationId, FamilyAccountInvitationService $invitations): void
    {
        $invitation = $this->ownedInvitation($invitationId);
        $invitations->cancel(auth()->user(), $invitation);
        $this->managedInvitationId = null;
        session()->flash('status', 'Invitation canceled.');
    }

    public function removeAccess(FamilyAccountAccessService $access): void
    {
        $this->validate(['removingMemberId' => ['required', 'integer']]);
        $member = $this->accountMember((int) $this->removingMemberId);
        $name = $member->user->name;
        $access->remove(auth()->user(), $member);
        $this->removingMemberId = null;
        session()->flash('status', $name.' no longer has access.');
    }

    public function leaveAccount(FamilyAccountAccessService $access): void
    {
        $access->leave(auth()->user());
        auth()->guard()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        $this->redirect(route('login', absolute: false), navigate: true);
    }

    public function render(FamilyAccountContext $context)
    {
        $membership = $context->membership(auth()->user());
        $account = $membership->familyAccount;
        $account->load([
            'owner:id,name,email',
            'activeMemberships' => fn ($query) => $query
                ->with('user:id,name,email')
                ->orderByRaw("CASE WHEN access_level = 'owner' THEN 0 ELSE 1 END")
                ->orderBy('joined_at'),
            'invitations' => fn ($query) => $query
                ->whereNull('accepted_at')
                ->whereNull('canceled_at')
                ->latest(),
        ]);

        return view('livewire.family.family-access', [
            'account' => $account,
            'membership' => $membership,
            'isOwner' => $membership->isOwner(),
        ]);
    }

    private function ownedInvitation(int $invitationId): FamilyAccountInvitation
    {
        $accountId = app(FamilyAccountContext::class)->account(auth()->user())->id;

        return FamilyAccountInvitation::query()->where('family_account_id', $accountId)->findOrFail($invitationId);
    }

    private function accountMember(int $memberId): FamilyAccountMember
    {
        $accountId = app(FamilyAccountContext::class)->account(auth()->user())->id;

        return FamilyAccountMember::query()
            ->with('user:id,name,email')
            ->where('family_account_id', $accountId)
            ->where('status', FamilyAccountMember::STATUS_ACTIVE)
            ->findOrFail($memberId);
    }
}
