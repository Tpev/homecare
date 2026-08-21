<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentMcpOAuthAccessToken extends Model
{
    protected $table = 'content_mcp_oauth_access_tokens';

    protected $fillable = [
        'public_id',
        'family_id',
        'client_id',
        'user_id',
        'token_prefix',
        'token_hash',
        'scopes',
        'resource',
        'expires_at',
        'last_used_at',
        'revoked_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture()
            && $this->resource === config('content_mcp.resource')
            && $this->client?->isUsable() === true
            && $this->user?->isContentTeamMember() === true;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ContentMcpOAuthClient::class, 'client_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
