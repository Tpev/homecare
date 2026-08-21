<?php

namespace App\Console\Commands;

use App\Models\ContentApiToken;
use Illuminate\Console\Command;

class ListContentApiTokens extends Command
{
    protected $signature = 'content:token:list
        {--active : Show only usable, unexpired tokens}
        {--actor= : Filter by actor user ID or email address}';

    protected $description = 'List Content API token metadata without displaying token secrets or hashes';

    public function handle(): int
    {
        $query = ContentApiToken::query()
            ->with(['actor:id,name,email', 'issuer:id,name,email'])
            ->when($this->option('active'), fn ($query) => $query->active())
            ->latest('id');

        if ($this->option('actor') !== null) {
            $actor = trim((string) $this->option('actor'));
            $query->whereHas('actor', function ($users) use ($actor): void {
                if (ctype_digit($actor)) {
                    $users->whereKey((int) $actor);
                } else {
                    $users->whereRaw('LOWER(email) = ?', [mb_strtolower($actor)]);
                }
            });
        }

        $tokens = $query->get();
        $this->table(
            ['ID', 'Name', 'Actor', 'Kind', 'Safe prefix', 'Abilities', 'Expires', 'Revoked', 'Last used', 'Issuer'],
            $tokens->map(fn (ContentApiToken $token): array => [
                $token->id,
                $token->name,
                $token->actor ? $token->actor->email.' (#'.$token->actor->id.')' : 'deleted user',
                $token->allows_actor_delegation ? 'hosted MCP service' : 'direct client',
                $token->token_prefix,
                implode(', ', $token->abilities ?? []),
                $token->expires_at?->toIso8601String() ?? 'invalid',
                $token->revoked_at?->toIso8601String() ?? 'no',
                $token->last_used_at?->toIso8601String() ?? 'never',
                $token->issuer?->email ?? 'console',
            ])->all(),
        );

        $this->info($tokens->count().' token(s). Secrets and hashes are never displayed.');

        return self::SUCCESS;
    }
}
