<?php

namespace App\Console\Commands;

use App\Models\ContentMcpOAuthAccessToken;
use Illuminate\Console\Command;

class ListContentMcpSessions extends Command
{
    protected $signature = 'content-mcp:session:list
        {--active : Show only unexpired and unrevoked OAuth access sessions}
        {--actor= : Filter by actor user ID or email address}';

    protected $description = 'List hosted Content MCP OAuth session metadata without displaying credentials or hashes';

    public function handle(): int
    {
        $query = ContentMcpOAuthAccessToken::query()
            ->with(['user:id,name,email,role,content_role', 'client:id,client_id,name'])
            ->when($this->option('active'), fn ($tokens) => $tokens
                ->whereNull('revoked_at')->where('expires_at', '>', now()))
            ->latest('id');

        if ($this->option('actor') !== null) {
            $actor = trim((string) $this->option('actor'));
            $query->whereHas('user', function ($users) use ($actor): void {
                if (ctype_digit($actor)) {
                    $users->whereKey((int) $actor);
                } else {
                    $users->whereRaw('LOWER(email) = ?', [mb_strtolower($actor)]);
                }
            });
        }

        $sessions = $query->get();
        $this->table(
            ['Session', 'Actor', 'Codex client', 'Scopes', 'Expires', 'Revoked', 'Last used'],
            $sessions->map(fn (ContentMcpOAuthAccessToken $token): array => [
                $token->public_id,
                $token->user ? $token->user->email.' (#'.$token->user->id.')' : 'deleted user',
                $token->client?->name ?? 'deleted client',
                implode(', ', $token->scopes ?? []),
                $token->expires_at?->toIso8601String() ?? 'invalid',
                $token->revoked_at?->toIso8601String() ?? 'no',
                $token->last_used_at?->toIso8601String() ?? 'never',
            ])->all(),
        );
        $this->info($sessions->count().' session(s). OAuth credentials and hashes are never displayed.');

        return self::SUCCESS;
    }
}
