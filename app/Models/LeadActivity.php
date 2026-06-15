<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadActivity extends Model
{
    public const TYPE_NOTE = 'note';
    public const TYPE_CALL = 'call';
    public const TYPE_EMAIL = 'email';
    public const TYPE_SMS = 'sms';
    public const TYPE_MEETING = 'meeting';
    public const TYPE_STAGE_CHANGE = 'stage_change';
    public const TYPE_ASSIGNMENT = 'assignment';
    public const TYPE_CONVERSION = 'conversion';

    protected $fillable = [
        'lead_id',
        'actor_user_id',
        'type',
        'summary',
        'body',
        'occurred_at',
        'metadata',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
