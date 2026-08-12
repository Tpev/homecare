<?php

namespace App\Console\Commands;

use App\Models\ContentApiToken;
use App\Models\User;
use App\Services\Content\ContentApiTokenManager;
use Illuminate\Console\Command;

class RevokeContentApiToken extends Command
{
    protected $signature = 'content:token:revoke
        {token : Token numeric ID or unambiguous safe-prefix beginning with lolo_content_}
        {--revoked-by= : Administrator user ID or email for revoker attribution}';

    protected $description = 'Revoke a Content API token by ID or unambiguous safe prefix';

    public function handle(ContentApiTokenManager $tokens): int
    {
        $token = $this->resolveToken(trim((string) $this->argument('token')));
        if (! $token) {
            return self::FAILURE;
        }

        $revoker = $this->resolveRevoker();
        if ($this->option('revoked-by') !== null && ! $revoker) {
            return self::FAILURE;
        }

        if (! $tokens->revoke($token, $revoker)) {
            $this->warn("Token {$token->id} ({$token->token_prefix}) was already revoked.");

            return self::SUCCESS;
        }

        $this->info("Revoked token {$token->id} ({$token->token_prefix}) for {$token->actor?->email}.");

        return self::SUCCESS;
    }

    private function resolveToken(string $identifier): ?ContentApiToken
    {
        if (ctype_digit($identifier)) {
            $token = ContentApiToken::query()->with('actor')->find((int) $identifier);
            if (! $token) {
                $this->error('No Content API token has that ID.');
            }

            return $token;
        }

        if (! preg_match('/^lolo_content_[a-f0-9]{4,16}$/', $identifier)) {
            $this->error('Use a numeric token ID or at least four hexadecimal characters of its displayed safe prefix.');

            return null;
        }

        $matches = ContentApiToken::query()
            ->with('actor')
            ->where('token_prefix', 'like', $identifier.'%')
            ->limit(2)
            ->get();

        if ($matches->isEmpty()) {
            $this->error('No Content API token matched that safe prefix.');

            return null;
        }

        if ($matches->count() > 1) {
            $this->error('That safe prefix is ambiguous. Provide more characters or use the numeric token ID.');

            return null;
        }

        return $matches->first();
    }

    private function resolveRevoker(): ?User
    {
        $identifier = $this->option('revoked-by');
        if ($identifier === null) {
            return null;
        }

        $identifier = trim((string) $identifier);
        $revoker = ctype_digit($identifier)
            ? User::query()->find((int) $identifier)
            : User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($identifier)])->first();

        if (! $revoker) {
            $this->error('No user matched the revoker identifier.');

            return null;
        }

        if (! $revoker->isAdministrator()) {
            $this->error('The attributed revoker must be an administrator.');

            return null;
        }

        return $revoker;
    }
}
