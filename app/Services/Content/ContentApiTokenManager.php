<?php

namespace App\Services\Content;

use App\Models\ContentApiToken;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContentApiTokenManager
{
    public const TOKEN_PREFIX = 'lolo_content_';

    private const MAX_ISSUE_ATTEMPTS = 5;

    /**
     * @param  list<string>  $abilities
     * @return array{token: ContentApiToken, plain_text_token: string}
     */
    public function issue(
        User $actor,
        string $name,
        array $abilities,
        CarbonInterface $expiresAt,
        ?User $issuer = null,
        bool $allowsActorDelegation = false,
    ): array {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            throw ValidationException::withMessages([
                'name' => 'The token name is required and may not exceed 100 characters.',
            ]);
        }

        if (! $actor->isContentTeamMember()) {
            throw ValidationException::withMessages([
                'actor' => 'The token actor must be an active content-team user.',
            ]);
        }

        if ($issuer !== null && ! $issuer->isAdministrator()) {
            throw ValidationException::withMessages([
                'issuer' => 'The attributed issuer must be an administrator.',
            ]);
        }

        $abilities = $this->validateAbilities($actor, $abilities);
        if ($allowsActorDelegation && (! $actor->isAdministrator() || ! $issuer?->isAdministrator())) {
            throw ValidationException::withMessages([
                'delegation' => 'A hosted MCP service token requires an administrator actor and an explicitly attributed administrator issuer.',
            ]);
        }
        if (! $expiresAt->isFuture()) {
            throw ValidationException::withMessages([
                'expires_at' => 'The token expiration must be in the future.',
            ]);
        }

        for ($attempt = 0; $attempt < self::MAX_ISSUE_ATTEMPTS; $attempt++) {
            $identifier = bin2hex(random_bytes(8));
            $prefix = self::TOKEN_PREFIX.$identifier;
            $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $plainTextToken = $prefix.'_'.$secret;

            try {
                $token = ContentApiToken::query()->create([
                    'name' => $name,
                    'token_prefix' => $prefix,
                    'token_hash' => self::hash($plainTextToken),
                    'actor_user_id' => $actor->id,
                    'issued_by_user_id' => $issuer?->id,
                    'abilities' => $abilities,
                    'allows_actor_delegation' => $allowsActorDelegation,
                    'expires_at' => $expiresAt,
                ]);

                return ['token' => $token, 'plain_text_token' => $plainTextToken];
            } catch (QueryException $exception) {
                if (($exception->errorInfo[0] ?? null) !== '23000') {
                    throw $exception;
                }
            }
        }

        throw ValidationException::withMessages([
            'token' => 'A unique token could not be generated. Try issuing it again.',
        ]);
    }

    public function authenticate(string $plainTextToken): ?ContentApiToken
    {
        $plainTextToken = trim($plainTextToken);
        if (! preg_match('/^lolo_content_[a-f0-9]{16}_[A-Za-z0-9_-]{43}$/D', $plainTextToken)) {
            return null;
        }

        $token = ContentApiToken::query()
            ->with('actor')
            ->where('token_hash', self::hash($plainTextToken))
            ->first();

        if (! $token?->isUsable()) {
            return null;
        }

        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        return $token;
    }

    public function revoke(ContentApiToken $token, ?User $revoker = null): bool
    {
        if ($revoker !== null && ! $revoker->isAdministrator()) {
            throw ValidationException::withMessages([
                'revoker' => 'The attributed revoker must be an administrator.',
            ]);
        }

        return DB::transaction(function () use ($token, $revoker): bool {
            $token = ContentApiToken::query()->lockForUpdate()->findOrFail($token->id);
            if ($token->revoked_at !== null) {
                return false;
            }

            $token->forceFill([
                'revoked_at' => now(),
                'revoked_by_user_id' => $revoker?->id,
            ])->save();

            return true;
        });
    }

    public static function hash(string $value): string
    {
        return hash('sha256', $value);
    }

    /** @param list<string> $abilities @return list<string> */
    private function validateAbilities(User $actor, array $abilities): array
    {
        $abilities = array_values(array_unique(array_map(
            static fn (mixed $ability): string => trim((string) $ability),
            $abilities,
        )));

        if ($abilities === [] || in_array('', $abilities, true)) {
            throw ValidationException::withMessages([
                'abilities' => 'Select at least one content API ability.',
            ]);
        }

        $unknown = array_values(array_diff($abilities, ContentApiToken::ABILITIES));
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'abilities' => 'Unknown content API abilities: '.implode(', ', $unknown).'.',
            ]);
        }

        $publisherAbilities = [ContentApiToken::ABILITY_SCHEDULE, ContentApiToken::ABILITY_PUBLISH];
        if (array_intersect($abilities, $publisherAbilities) !== [] && ! $actor->canPublishContent()) {
            throw ValidationException::withMessages([
                'abilities' => 'Only a publisher or administrator actor may receive scheduling or publishing abilities.',
            ]);
        }

        return array_values(array_filter(
            ContentApiToken::ABILITIES,
            static fn (string $ability): bool => in_array($ability, $abilities, true),
        ));
    }
}
