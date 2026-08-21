<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentMcpOAuthClient extends Model
{
    protected $table = 'content_mcp_oauth_clients';

    protected $fillable = [
        'client_id',
        'name',
        'redirect_uris',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'redirect_uris' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    public function acceptsRedirectUri(string $uri): bool
    {
        return in_array($uri, $this->redirect_uris ?? [], true);
    }

    public function authorizationCodes(): HasMany
    {
        return $this->hasMany(ContentMcpOAuthAuthorizationCode::class, 'client_id');
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(ContentMcpOAuthAccessToken::class, 'client_id');
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(ContentMcpOAuthRefreshToken::class, 'client_id');
    }
}
