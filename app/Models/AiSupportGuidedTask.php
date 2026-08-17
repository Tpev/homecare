<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSupportGuidedTask extends Model
{
    use HasUuids;

    public const TYPE_PAYMENT_METHOD = 'family_payment_method';

    public const STATE_OFFERED = 'offered';

    public const STATE_NAVIGATING = 'navigating';

    public const STATE_ARRIVED = 'arrived';

    public const STATE_IN_PROGRESS = 'in_progress';

    public const STATE_COMPLETED = 'completed';

    public const STATE_FAILED = 'failed';

    public const STATE_CANCELLED = 'cancelled';

    public const STATE_EXPIRED = 'expired';

    public const OPEN_STATES = [
        self::STATE_OFFERED,
        self::STATE_NAVIGATING,
        self::STATE_ARRIVED,
        self::STATE_IN_PROGRESS,
    ];

    public const FOREGROUND_STATES = [
        self::STATE_NAVIGATING,
        self::STATE_ARRIVED,
        self::STATE_IN_PROGRESS,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'support_ticket_id', 'actor_user_id', 'family_account_id',
        'task_type', 'state', 'navigation_target_id', 'payload',
        'initial_state_hash', 'result_state_hash', 'last_result_code', 'version',
        'started_at', 'arrived_at', 'in_progress_at', 'completed_at',
        'presented_at', 'cancelled_at', 'expires_at',
    ];

    protected $hidden = ['payload', 'initial_state_hash', 'result_state_hash'];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'started_at' => 'datetime',
            'arrived_at' => 'datetime',
            'in_progress_at' => 'datetime',
            'completed_at' => 'datetime',
            'presented_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function scopeOpen(Builder $query): Builder
    {
        return $query
            ->whereIn('state', self::OPEN_STATES)
            ->where('expires_at', '>', now());
    }

    public function scopeForeground(Builder $query): Builder
    {
        return $query
            ->whereIn('state', self::FOREGROUND_STATES)
            ->where('expires_at', '>', now());
    }

    public function isOpen(): bool
    {
        return in_array($this->state, self::OPEN_STATES, true)
            && $this->expires_at?->isFuture() === true;
    }
}
