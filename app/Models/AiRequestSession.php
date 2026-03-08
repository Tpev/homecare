<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiRequestSession extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATUS_DRAFTING = 'drafting';
    public const STATUS_READY_FOR_REVIEW = 'ready_for_review';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ABANDONED = 'abandoned';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'family_user_id',
        'status',
        'draft_json',
        'missing_required_json',
        'quality_score',
        'model',
        'published_care_request_id',
        'last_ai_at',
    ];

    protected function casts(): array
    {
        return [
            'draft_json' => 'array',
            'missing_required_json' => 'array',
            'quality_score' => 'integer',
            'last_ai_at' => 'datetime',
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(User::class, 'family_user_id');
    }

    public function publishedCareRequest(): BelongsTo
    {
        return $this->belongsTo(CareRequest::class, 'published_care_request_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiRequestMessage::class);
    }
}

