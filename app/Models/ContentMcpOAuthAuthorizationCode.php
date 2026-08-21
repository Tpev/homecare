<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentMcpOAuthAuthorizationCode extends Model
{
    protected $table = 'content_mcp_oauth_authorization_codes';

    protected $fillable = [
        'client_id',
        'user_id',
        'code_hash',
        'redirect_uri',
        'scopes',
        'resource',
        'code_challenge',
        'expires_at',
        'used_at',
    ];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
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
}
