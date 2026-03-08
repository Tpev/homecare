<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRequestMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'ai_request_session_id',
        'role',
        'content_text',
        'structured_json',
        'latency_ms',
        'prompt_tokens',
        'completion_tokens',
    ];

    protected function casts(): array
    {
        return [
            'structured_json' => 'array',
            'latency_ms' => 'integer',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AiRequestSession::class, 'ai_request_session_id');
    }
}

