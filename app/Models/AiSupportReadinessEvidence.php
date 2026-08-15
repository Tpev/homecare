<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSupportReadinessEvidence extends Model
{
    use HasUuids;

    public const STATUS_PASSED = 'passed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PENDING = 'pending';

    public const STATUS_DEFERRED = 'deferred';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'safe_metadata' => 'array',
            'observed_at' => 'datetime',
            'expires_at' => 'datetime',
            'superseded_at' => 'datetime',
            'retain_until' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('superseded_at');
    }

    public function isEffectivePass(): bool
    {
        return $this->status === self::STATUS_PASSED
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isEffectiveDeferred(): bool
    {
        return $this->status === self::STATUS_DEFERRED
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
