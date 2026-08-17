<?php

namespace App\Services\AiSupport;

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

    public function allowedFor(User $user, string $targetId): bool
    {
        $definition = $this->definition($targetId);

        return $definition !== null
            && Route::has((string) $definition['route'])
            && in_array($user->role, (array) $definition['roles'], true)
            && (! ($definition['owner_only'] ?? false) || $this->familyAccounts->isOwner($user));
    }

    public function urlFor(User $user, string $targetId): string
    {
        if (! $this->allowedFor($user, $targetId)) {
            throw new AuthorizationException('The navigation target is not registered for this user.');
        }

        return route((string) $this->definition($targetId)['route']);
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
            ->filter(fn (string $id): bool => $this->allowedFor($user, $id))
            ->values()
            ->all();
    }
}
