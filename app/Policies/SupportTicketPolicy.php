<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;

class SupportTicketPolicy
{
    public function __construct(private readonly FamilyAccountContext $familyAccounts) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SupportTicket $supportTicket): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        if ($user->role === 'family' && $supportTicket->family_account_id) {
            if (! $this->familyAccounts->canAccessAccount($user, (int) $supportTicket->family_account_id)) {
                return false;
            }

            return $supportTicket->family_visibility === 'shared_care'
                || ($supportTicket->family_visibility === 'owner_only' && $this->familyAccounts->isOwner($user))
                || ($supportTicket->family_visibility === 'opener_only'
                    && (int) $supportTicket->opener_user_id === (int) $user->id);
        }

        return (int) $supportTicket->opener_user_id === (int) $user->id;
    }

    public function reply(User $user, SupportTicket $supportTicket): bool
    {
        return $this->view($user, $supportTicket)
            && ! $user->isAdministrator()
            && $supportTicket->status !== SupportTicket::STATUS_CLOSED
            && $supportTicket->transcript_deleted_at === null;
    }

    public function replyAsAdmin(User $user, SupportTicket $supportTicket): bool
    {
        return $user->isAdministrator()
            && $supportTicket->status !== SupportTicket::STATUS_CLOSED
            && $supportTicket->transcript_deleted_at === null;
    }

    public function addInternalNote(User $user, SupportTicket $supportTicket): bool
    {
        return $this->replyAsAdmin($user, $supportTicket);
    }

    public function manage(User $user, SupportTicket $supportTicket): bool
    {
        return $user->isAdministrator();
    }
}
