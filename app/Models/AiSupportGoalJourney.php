<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSupportGoalJourney extends Model
{
    use HasUuids;

    public const STATE_ACTIVE = 'active';

    public const STATE_AWAITING_CHOICE = 'awaiting_choice';

    public const STATE_TRANSFERRED = 'transferred';

    public const STATE_COMPLETED = 'completed';

    public const STATE_CANCELLED = 'cancelled';

    public const STATE_EXPIRED = 'expired';

    public const RESUMABLE_STATES = [
        self::STATE_ACTIVE,
        self::STATE_AWAITING_CHOICE,
        self::STATE_TRANSFERRED,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['context'];

    protected function casts(): array
    {
        return [
            'context' => 'encrypted:array',
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'transferred_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function familyAccount(): BelongsTo
    {
        return $this->belongsTo(FamilyAccount::class);
    }

    public function scopeResumable(Builder $query): Builder
    {
        return $query
            ->whereIn('state', self::RESUMABLE_STATES)
            ->where('expires_at', '>', now());
    }

    public function isResumable(): bool
    {
        return in_array($this->state, self::RESUMABLE_STATES, true)
            && $this->expires_at?->isFuture() === true;
    }
}
