<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSupportReleaseDecision extends Model
{
    use HasUuids;

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REVOKED = 'revoked';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'approved_user_ids' => 'array',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'superseded_at' => 'datetime',
            'retain_until' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('superseded_at');
    }

    public function isEffective(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && ! $this->starts_at->isFuture()
            && $this->expires_at->isFuture()
            && $this->policy_version === (string) config('ai_support.initial_pilot.policy_version');
    }
}
