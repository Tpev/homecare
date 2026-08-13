<?php

namespace App\Services\AiSupport;

use App\Models\KnowledgeBaseVersion;
use App\Models\User;
use Illuminate\Support\Collection;

class KnowledgeBaseRetrievalService
{
    /** @return Collection<int, KnowledgeBaseVersion> */
    public function applicable(
        User $user,
        string $capabilityId,
        ?string $membershipState = null,
        ?string $routeTargetId = null,
    ): Collection {
        return KnowledgeBaseVersion::query()
            ->retrievable()
            ->with(['entry:id,stable_id,published_version_id', 'sources'])
            ->get()
            ->filter(function (KnowledgeBaseVersion $version) use (
                $user,
                $capabilityId,
                $membershipState,
                $routeTargetId,
            ): bool {
                if (! in_array($user->role, (array) $version->roles, true)) {
                    return false;
                }

                if (! in_array($capabilityId, (array) $version->capability_ids, true)) {
                    return false;
                }

                if ($membershipState !== null
                    && (array) $version->membership_states !== []
                    && ! in_array($membershipState, (array) $version->membership_states, true)) {
                    return false;
                }

                return $routeTargetId === null
                    || (array) $version->route_target_ids === []
                    || in_array($routeTargetId, (array) $version->route_target_ids, true);
            })
            ->values();
    }
}
