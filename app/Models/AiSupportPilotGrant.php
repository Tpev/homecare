<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSupportPilotGrant extends Model
{
    use HasUuids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'request_key',
        'user_id',
        'bundle_key',
        'capability_ids',
        'starts_at',
        'expires_at',
        'granted_by_user_id',
        'grant_reason',
        'revoked_at',
        'revoked_by_user_id',
        'revocation_reason',
        'retain_until',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'capability_ids' => 'array',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'retain_until' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    public function scopeNotRevoked(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function scopeEffectiveAt(Builder $query, mixed $at = null): Builder
    {
        $at ??= now();

        return $query
            ->notRevoked()
            ->where('starts_at', '<=', $at)
            ->where(fn (Builder $window): Builder => $window
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', $at));
    }

    public function status(): string
    {
        if ($this->revoked_at) {
            return 'revoked';
        }

        if ($this->expires_at && ! $this->expires_at->isFuture()) {
            return 'expired';
        }

        if ($this->starts_at->isFuture()) {
            return 'scheduled';
        }

        return 'active';
    }

    public function includesCapability(string $capabilityId): bool
    {
        return in_array($capabilityId, $this->capability_ids ?? [], true);
    }
}
