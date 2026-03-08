<?php

namespace App\Policies;

use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\User;

class CareRequestConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['family', 'caregiver'], true);
    }

    public function view(User $user, CareRequestConversation $careRequestConversation): bool
    {
        return $careRequestConversation->isParticipant($user);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['family', 'caregiver'], true);
    }

    public function update(User $user, CareRequestConversation $careRequestConversation): bool
    {
        return $careRequestConversation->isParticipant($user);
    }

    public function delete(User $user, CareRequestConversation $careRequestConversation): bool
    {
        return false;
    }

    public function restore(User $user, CareRequestConversation $careRequestConversation): bool
    {
        return false;
    }

    public function forceDelete(User $user, CareRequestConversation $careRequestConversation): bool
    {
        return false;
    }

    public function sendMessage(User $user, CareRequestConversation $careRequestConversation): bool
    {
        return $careRequestConversation->canSendMessages($user);
    }

    public function openFromApplication(User $user, CareRequestApplication $application): bool
    {
        if (! in_array($application->status, [
            CareRequestApplication::STATUS_SHORTLISTED,
            CareRequestApplication::STATUS_HIRED,
        ], true)) {
            return false;
        }

        if ($user->role === 'family') {
            return (int) $application->careRequest->family_user_id === (int) $user->id;
        }

        if ($user->role === 'caregiver') {
            return (int) $application->caregiver_user_id === (int) $user->id;
        }

        return false;
    }
}
