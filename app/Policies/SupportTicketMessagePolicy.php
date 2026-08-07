<?php

namespace App\Policies;

use App\Models\SupportTicketMessage;
use App\Models\User;

class SupportTicketMessagePolicy
{
    public function __construct(private readonly SupportTicketPolicy $tickets) {}

    public function view(User $user, SupportTicketMessage $message): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        return $message->kind === SupportTicketMessage::KIND_PUBLIC
            && $message->ticket
            && $this->tickets->view($user, $message->ticket);
    }
}
