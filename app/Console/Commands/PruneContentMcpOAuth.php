<?php

namespace App\Console\Commands;

use App\Models\ContentMcpOAuthAccessToken;
use App\Models\ContentMcpOAuthAuthorizationCode;
use App\Models\ContentMcpOAuthClient;
use App\Models\ContentMcpOAuthRefreshToken;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneContentMcpOAuth extends Command
{
    protected $signature = 'content-mcp:prune';

    protected $description = 'Prune expired or revoked Content MCP OAuth records after their recovery window';

    public function handle(): int
    {
        $cutoff = now()->subDays(30);
        $counts = DB::transaction(function () use ($cutoff): array {
            $codes = ContentMcpOAuthAuthorizationCode::query()
                ->where(fn ($query) => $query->where('expires_at', '<', now()->subDay())
                    ->orWhere('used_at', '<', now()->subDay()))
                ->delete();
            $refresh = ContentMcpOAuthRefreshToken::query()
                ->where(fn ($query) => $query->where('expires_at', '<', $cutoff)
                    ->orWhere('revoked_at', '<', $cutoff))
                ->delete();
            $access = ContentMcpOAuthAccessToken::query()
                ->where(fn ($query) => $query->where('expires_at', '<', $cutoff)
                    ->orWhere('revoked_at', '<', $cutoff))
                ->delete();
            $clients = ContentMcpOAuthClient::query()
                ->where(fn ($query) => $query->where('expires_at', '<', $cutoff)
                    ->orWhere('revoked_at', '<', $cutoff))
                ->delete();

            return compact('codes', 'refresh', 'access', 'clients');
        });

        $this->info(sprintf(
            'Pruned %d authorization code(s), %d refresh token(s), %d access token(s), and %d client(s).',
            $counts['codes'],
            $counts['refresh'],
            $counts['access'],
            $counts['clients'],
        ));

        return self::SUCCESS;
    }
}
