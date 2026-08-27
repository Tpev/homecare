<?php

namespace App\Services\AiSupport;

use App\Models\CaregiverProfile;
use App\Models\CarePlan;
use App\Models\CareRecipientProfile;
use App\Models\CareRequest;
use App\Models\CareRequestConversation;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;

class NavigationTargetRegistry
{
    public function __construct(private readonly FamilyAccountContext $familyAccounts) {}

    public function has(string $targetId): bool
    {
        $definition = $this->definition($targetId);

        return $definition !== null && Route::has((string) $definition['route']);
    }

    /** @return array<string,mixed>|null */
    public function definition(string $targetId): ?array
    {
        $definition = ((array) config('ai_support.navigation_targets', []))[$targetId] ?? null;

        return is_array($definition) ? $definition : null;
    }

    /** @param array{resource_type?:string|null,resource_id?:int|string|null} $resource */
    public function allowedFor(User $user, string $targetId, array $resource = []): bool
    {
        $definition = $this->definition($targetId);

        if (! ($definition !== null
            && Route::has((string) $definition['route'])
            && in_array($user->role, (array) $definition['roles'], true)
            && (! ($definition['owner_only'] ?? false) || $this->familyAccounts->isOwner($user)))) {
            return false;
        }

        $expectedType = trim((string) ($definition['resource_type'] ?? ''));
        if ($expectedType === '') {
            return true;
        }

        $resourceType = trim((string) ($resource['resource_type'] ?? ''));
        $resourceId = (int) ($resource['resource_id'] ?? 0);

        return $resourceType === $expectedType
            && $resourceId > 0
            && $this->canAccessResource($user, $resourceType, $resourceId);
    }

    /** @param array{resource_type?:string|null,resource_id?:int|string|null} $resource */
    public function urlFor(User $user, string $targetId, array $resource = []): string
    {
        if (! $this->allowedFor($user, $targetId, $resource)) {
            throw new AuthorizationException('The navigation target is not registered for this user.');
        }

        $definition = $this->definition($targetId);
        $parameters = array_merge(
            (array) ($definition['route_parameters'] ?? []),
            (array) ($definition['query'] ?? []),
        );
        if (isset($definition['resource_type'])) {
            $resourceId = (int) $resource['resource_id'];
            $parameters[(string) $definition['route_parameter']] = $this->resourceRouteValue(
                (string) $definition['resource_type'],
                $resourceId,
                (string) ($definition['resource_route_key'] ?? 'id'),
            );
        }

        return route((string) $definition['route'], $parameters);
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_keys((array) config('ai_support.navigation_targets', []));
    }

    /** @return list<string> */
    public function idsFor(User $user): array
    {
        return collect($this->ids())
            ->filter(function (string $id) use ($user): bool {
                $definition = $this->definition($id);

                return ! isset($definition['resource_type']) && $this->allowedFor($user, $id);
            })
            ->values()
            ->all();
    }

    private function canAccessResource(User $user, string $resourceType, int $resourceId): bool
    {
        if ($user->role !== 'family') {
            return false;
        }

        $account = $this->familyAccounts->membershipFor($user, false)?->familyAccount;
        if (! $account) {
            return false;
        }

        return match ($resourceType) {
            'care_request' => CareRequest::query()
                ->forFamilyAccount($account)
                ->whereKey($resourceId)
                ->exists(),
            'care_plan' => CarePlan::query()->forFamilyAccount($account)->whereKey($resourceId)->exists(),
            'care_profile' => CareRecipientProfile::query()->forFamilyAccount($account)->whereKey($resourceId)->exists(),
            'conversation' => CareRequestConversation::query()->forUser($user)->whereKey($resourceId)->exists(),
            'caregiver_profile' => CaregiverProfile::query()
                ->whereKey($resourceId)
                ->where('status', 'active')
                ->whereNotNull('slug')
                ->where('slug', '!=', '')
                ->exists(),
            default => false,
        };
    }

    private function resourceRouteValue(string $resourceType, int $resourceId, string $routeKey): int|string
    {
        if ($resourceType === 'caregiver_profile' && $routeKey === 'slug') {
            return (string) CaregiverProfile::query()->whereKey($resourceId)->value('slug');
        }

        return $resourceId;
    }
}
