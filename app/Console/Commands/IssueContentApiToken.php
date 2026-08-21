<?php

namespace App\Console\Commands;

use App\Models\ContentApiToken;
use App\Models\User;
use App\Services\Content\ContentApiTokenManager;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class IssueContentApiToken extends Command
{
    private const MAX_TTL_MINUTES = 525600;

    protected $signature = 'content:token:issue
        {actor : Content-team user ID or email address}
        {name : Descriptive token name}
        {--ability=* : Ability to grant; repeat for each ability}
        {--ttl= : Lifetime in minutes (maximum 525600)}
        {--expires= : ISO-8601 expiration timestamp}
        {--issued-by= : Administrator user ID or email for issuer attribution}
        {--hosted-mcp-service : Restrict this credential to signed hosted-MCP actor delegation}';

    protected $description = 'Issue a scoped, expiring Content API bearer token and display its secret once';

    public function handle(ContentApiTokenManager $tokens): int
    {
        try {
            $actor = $this->resolveUser((string) $this->argument('actor'), 'actor');
            $issuer = $this->option('issued-by') !== null
                ? $this->resolveUser((string) $this->option('issued-by'), 'issued-by')
                : null;
            $expiresAt = $this->expiration();
            $abilities = $this->abilities();

            $issued = $tokens->issue(
                $actor,
                (string) $this->argument('name'),
                $abilities,
                $expiresAt,
                $issuer,
                (bool) $this->option('hosted-mcp-service'),
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }

        $token = $issued['token'];
        $this->info('Content API token issued.');
        $this->line('ID: '.$token->id);
        $this->line('Name: '.$token->name);
        $this->line('Actor: '.$actor->name.' <'.$actor->email.'> (user '.$actor->id.')');
        $this->line('Abilities: '.implode(', ', $token->abilities));
        $this->line('Kind: '.($token->allows_actor_delegation ? 'hosted MCP delegation service' : 'direct machine client'));
        $this->line('Expires: '.$token->expires_at->toIso8601String());
        $this->newLine();
        $this->warn('Copy this bearer token now. It will not be shown again:');
        $this->line($issued['plain_text_token']);
        $this->warn('Store it in a secret manager. Do not place it in source control, shell history, or logs.');

        return self::SUCCESS;
    }

    private function resolveUser(string $identifier, string $field): User
    {
        $identifier = trim($identifier);
        $query = User::query();
        $user = ctype_digit($identifier)
            ? $query->find((int) $identifier)
            : $query->whereRaw('LOWER(email) = ?', [mb_strtolower($identifier)])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                $field => "No user matched the {$field} identifier.",
            ]);
        }

        return $user;
    }

    private function expiration(): CarbonInterface
    {
        $ttl = $this->option('ttl');
        $expires = $this->option('expires');
        if (($ttl === null && $expires === null) || ($ttl !== null && $expires !== null)) {
            throw ValidationException::withMessages([
                'expires_at' => 'Provide exactly one of --ttl or --expires.',
            ]);
        }

        if ($ttl !== null) {
            $ttl = filter_var($ttl, FILTER_VALIDATE_INT);
            if ($ttl === false || $ttl < 1 || $ttl > self::MAX_TTL_MINUTES) {
                throw ValidationException::withMessages([
                    'ttl' => 'The TTL must be between 1 and 525600 minutes.',
                ]);
            }

            return CarbonImmutable::now()->addMinutes($ttl);
        }

        try {
            $expiration = CarbonImmutable::createFromFormat(CarbonInterface::ATOM, (string) $expires);
        } catch (\Throwable) {
            $expiration = false;
        }

        if ($expiration === false || $expiration->format(CarbonInterface::ATOM) !== (string) $expires) {
            throw ValidationException::withMessages([
                'expires' => 'The expiration must be an ISO-8601 timestamp such as 2026-12-31T23:59:59+00:00.',
            ]);
        }

        if (! $expiration->isFuture() || $expiration->greaterThan(CarbonImmutable::now()->addMinutes(self::MAX_TTL_MINUTES))) {
            throw ValidationException::withMessages([
                'expires' => 'The expiration must be in the future and no more than one year away.',
            ]);
        }

        return $expiration;
    }

    /** @return list<string> */
    private function abilities(): array
    {
        $abilities = [];
        foreach ((array) $this->option('ability') as $option) {
            foreach (explode(',', (string) $option) as $ability) {
                $ability = trim($ability);
                if ($ability !== '') {
                    $abilities[] = $ability;
                }
            }
        }

        if ($abilities === []) {
            throw ValidationException::withMessages([
                'abilities' => 'Grant at least one --ability. Allowed: '.implode(', ', ContentApiToken::ABILITIES).'.',
            ]);
        }

        return array_values(array_unique($abilities));
    }
}
