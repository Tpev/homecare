<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CaregiverCrmActivity extends Model
{
    public const UPDATED_AT = null;

    public const PUBLIC_TYPES = ['note', 'call', 'email', 'sms', 'meeting'];

    public const CALL_OUTCOMES = ['connected', 'no_answer', 'voicemail'];

    protected $fillable = [
        'caregiver_user_id',
        'actor_user_id',
        'type',
        'call_outcome',
        'body',
        'occurred_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Caregiver contact history is append-only.'));
        static::deleting(fn () => throw new LogicException('Caregiver contact history is append-only.'));
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function isConnectedCall(): bool
    {
        return $this->type === 'call' && $this->call_outcome === 'connected';
    }
}
