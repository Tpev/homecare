<?php

namespace App\Services\AiSupport;

use App\Models\KnowledgeBaseVersion;
use App\Models\User;
use Illuminate\Support\Collection;

class KnowledgeBaseRetrievalService
{
    /**
     * Resolve only the published, applicable KB versions explicitly linked to an intent.
     *
     * @param  list<string>  $stableIds
     * @return Collection<int, KnowledgeBaseVersion>
     */
    public function forIntent(
        User $user,
        string $capabilityId,
        array $stableIds,
        ?string $membershipState = null,
        ?string $routeTargetId = null,
    ): Collection {
        $stableIds = array_values(array_unique(array_filter(array_map('strval', $stableIds))));
        if ($stableIds === []) {
            return collect();
        }

        return $this->applicable($user, $capabilityId, $membershipState, $routeTargetId)
            ->filter(fn (KnowledgeBaseVersion $version): bool => in_array($version->entry?->stable_id, $stableIds, true))
            ->sortBy(fn (KnowledgeBaseVersion $version): int => array_search($version->entry?->stable_id, $stableIds, true))
            ->values();
    }

    /** @return Collection<int, KnowledgeBaseVersion> */
    public function relevant(
        User $user,
        string $capabilityId,
        string $queryText,
        ?string $membershipState = null,
        ?string $routeTargetId = null,
        int $limit = 5,
    ): Collection {
        $terms = collect(preg_split('/[^a-z0-9]+/i', mb_strtolower($queryText)) ?: [])
            ->filter(fn (string $term): bool => mb_strlen($term) >= 3)
            ->unique()
            ->values();

        return $this->applicable($user, $capabilityId, $membershipState, $routeTargetId)
            ->map(function (KnowledgeBaseVersion $version) use ($terms): array {
                $haystack = mb_strtolower(implode(' ', [
                    $version->title,
                    $version->answer_body,
                    implode(' ', (array) $version->retrieval_examples_match),
                    implode(' ', (array) $version->facts_may_state),
                ]));
                $score = $terms->sum(fn (string $term): int => substr_count($haystack, $term));

                return ['version' => $version, 'score' => $score];
            })
            ->filter(fn (array $candidate): bool => $candidate['score'] > 0)
            ->sortByDesc('score')
            ->take(max(1, min(8, $limit)))
            ->pluck('version')
            ->values();
    }

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
