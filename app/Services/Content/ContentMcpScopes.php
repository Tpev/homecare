<?php

namespace App\Services\Content;

use App\Exceptions\ContentMcpOAuthException;
use App\Models\ContentApiToken;
use App\Models\User;

class ContentMcpScopes
{
    /** @return list<string> */
    public function supported(): array
    {
        return array_keys((array) config('content_mcp.scopes'));
    }

    /** @return list<string> */
    public function allowedFor(User $user): array
    {
        if (! $user->isContentTeamMember()) {
            return [];
        }

        return array_values(array_filter(
            $this->supported(),
            static fn (string $scope): bool => ! in_array($scope, [
                ContentApiToken::ABILITY_SCHEDULE,
                ContentApiToken::ABILITY_PUBLISH,
            ], true) || $user->canPublishContent(),
        ));
    }

    /** @return list<string> */
    public function parse(?string $value): array
    {
        $scopes = array_values(array_unique(array_filter(
            preg_split('/\s+/', trim((string) $value)) ?: [],
            static fn (string $scope): bool => $scope !== '',
        )));

        return $scopes === [] ? [ContentApiToken::ABILITY_READ] : $scopes;
    }

    /** @param list<string> $requested @return list<string> */
    public function authorize(User $user, array $requested): array
    {
        $unsupported = array_values(array_diff($requested, $this->supported()));
        if ($unsupported !== []) {
            throw new ContentMcpOAuthException('invalid_scope', 'Unsupported scope: '.implode(', ', $unsupported).'.');
        }

        $disallowed = array_values(array_diff($requested, $this->allowedFor($user)));
        if ($disallowed !== []) {
            throw new ContentMcpOAuthException('access_denied', 'Your LoLo Care content role does not permit: '.implode(', ', $disallowed).'.', 403);
        }

        return array_values(array_filter(
            $this->supported(),
            static fn (string $scope): bool => in_array($scope, $requested, true),
        ));
    }

    /** @param list<string> $granted @return list<string> */
    public function effective(User $user, array $granted): array
    {
        return array_values(array_intersect($this->supported(), $granted, $this->allowedFor($user)));
    }
}
