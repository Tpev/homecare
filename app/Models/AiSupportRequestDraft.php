<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSupportRequestDraft extends Model
{
    public const STATE_COLLECTING = 'collecting';

    public const STATE_NEEDS_CLARIFICATION = 'needs_clarification';

    public const STATE_READY_FOR_RECAP = 'ready_for_recap';

    public const STATE_DISCARDED = 'discarded';

    public const STATE_EXPIRED = 'expired';

    public const STATE_TRANSFERRED = 'transferred';

    public const STATE_PUBLISHED = 'published';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'support_ticket_id', 'actor_user_id', 'family_account_id', 'request_type',
        'state', 'payload', 'material_hash', 'version', 'ready_at', 'expires_at',
        'discarded_at', 'published_care_request_id', 'published_at', 'last_error_code',
    ];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'version' => 'integer',
            'ready_at' => 'datetime',
            'expires_at' => 'datetime',
            'discarded_at' => 'datetime',
            'published_at' => 'datetime',
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

    public function publishedCareRequest(): BelongsTo
    {
        return $this->belongsTo(CareRequest::class, 'published_care_request_id');
    }

    public function isUsable(): bool
    {
        return ! $this->discarded_at
            && ! $this->published_at
            && $this->expires_at?->isFuture() === true;
    }
}
