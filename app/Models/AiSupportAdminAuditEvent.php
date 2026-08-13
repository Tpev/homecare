<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSupportAdminAuditEvent extends Model
{
    use HasUuids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'event_family',
        'action',
        'actor_user_id',
        'target_user_id',
        'subject_type',
        'subject_id',
        'result',
        'reason_code',
        'reason',
        'metadata',
        'policy_version',
        'retain_until',
        'occurred_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'retain_until' => 'datetime',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
