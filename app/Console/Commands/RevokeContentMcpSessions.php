<?php

namespace App\Console\Commands;

use App\Models\ContentMcpOAuthAccessToken;
use App\Models\ContentMcpOAuthRefreshToken;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RevokeContentMcpSessions extends Command
{
    protected $signature = 'content-mcp:session:revoke
        {actor : Content-team user ID or email address}
        {--revoked-by= : Administrator user ID or email for attribution}';

    protected $description = 'Immediately revoke every hosted Content MCP OAuth session for one actor';

    public function handle(): int
    {
        $actor = $this->resolveUser((string) $this->argument('actor'));
        $revoker = $this->resolveUser((string) $this->option('revoked-by'));
        if (! $actor || ! $revoker) {
            return self::FAILURE;
        }
        if (! $revoker->isAdministrator()) {
            $this->error('The attributed revoker must be an administrator.');

            return self::FAILURE;
        }

        [$accessCount, $refreshCount] = DB::transaction(function () use ($actor): array {
            $access = ContentMcpOAuthAccessToken::query()
                ->where('user_id', $actor->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'updated_at' => now()]);
            $refresh = ContentMcpOAuthRefreshToken::query()
                ->where('user_id', $actor->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'updated_at' => now()]);

            return [$access, $refresh];
        });

        $this->info("Revoked {$accessCount} access session(s) and {$refreshCount} refresh session(s) for {$actor->email}.");
        $this->line("Attributed administrator: {$revoker->email} (#{$revoker->id}).");

        return self::SUCCESS;
    }

    private function resolveUser(string $identifier): ?User
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            $this->error('Provide both the actor and --revoked-by administrator.');

            return null;
        }

        $user = ctype_digit($identifier)
            ? User::query()->find((int) $identifier)
            : User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($identifier)])->first();
        if (! $user) {
            $this->error("No user matched {$identifier}.");
        }

        return $user;
    }
}
