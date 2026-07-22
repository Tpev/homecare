<?php

namespace App\Policies;

use App\Models\SupportTicketMessage;
use App\Models\User;

class SupportTicketMessagePolicy
{
    public function view(User $user, SupportTicketMessage $message): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        return $message->kind === SupportTicketMessage::KIND_PUBLIC
            && (int) $message->ticket?->opener_user_id === (int) $user->id;
    }
}
