<?php

namespace App\Policies;

use App\Models\MediaAsset;
use App\Models\User;

class MediaAssetPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdministrator() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isContentTeamMember();
    }

    public function view(User $user, MediaAsset $asset): bool
    {
        return $user->isContentTeamMember();
    }

    public function create(User $user): bool
    {
        return $user->isContentTeamMember();
    }

    public function update(User $user, MediaAsset $asset): bool
    {
        return $user->isContentTeamMember();
    }

    public function delete(User $user, MediaAsset $asset): bool
    {
        return $user->canPublishContent();
    }
}
