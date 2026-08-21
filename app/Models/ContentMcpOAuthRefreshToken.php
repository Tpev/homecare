<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentMcpOAuthRefreshToken extends Model
{
    protected $table = 'content_mcp_oauth_refresh_tokens';

    protected $fillable = [
        'public_id',
        'family_id',
        'client_id',
        'user_id',
        'access_token_id',
        'replaced_by_id',
        'token_hash',
        'scopes',
        'resource',
        'expires_at',
        'used_at',
        'revoked_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ContentMcpOAuthClient::class, 'client_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accessToken(): BelongsTo
    {
        return $this->belongsTo(ContentMcpOAuthAccessToken::class, 'access_token_id');
    }

    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_id');
    }
}
