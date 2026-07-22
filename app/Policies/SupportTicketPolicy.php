<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;

class SupportTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SupportTicket $supportTicket): bool
    {
        return $user->isAdministrator()
            || (int) $supportTicket->opener_user_id === (int) $user->id;
    }

    public function reply(User $user, SupportTicket $supportTicket): bool
    {
        return (int) $supportTicket->opener_user_id === (int) $user->id
            && $supportTicket->status !== SupportTicket::STATUS_CLOSED;
    }

    public function replyAsAdmin(User $user, SupportTicket $supportTicket): bool
    {
        return $user->isAdministrator()
            && $supportTicket->status !== SupportTicket::STATUS_CLOSED;
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
