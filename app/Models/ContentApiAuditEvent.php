<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentApiAuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'content_api_token_id',
        'actor_user_id',
        'blog_post_id',
        'action',
        'ability',
        'outcome',
        'response_status',
        'request_id',
        'idempotency_key_hash',
        'metadata',
        'occurred_at',
    ];

    protected $hidden = [
        'idempotency_key_hash',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'response_status' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(ContentApiToken::class, 'content_api_token_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function blogPost(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class);
    }
}
