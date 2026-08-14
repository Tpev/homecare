<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSupportMessageAction extends Model
{
    public const TYPE_NAVIGATE = 'navigate';

    public const TYPE_PATH_CHOICES = 'path_choices';

    public const TYPE_RECAP = 'recap';

    public const TYPE_RECEIPT = 'receipt';

    public const TYPE_RENEW_RECAP = 'renew_recap';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'support_ticket_message_id', 'support_ticket_id', 'actor_user_id',
        'action_type', 'payload', 'expires_at', 'consumed_at', 'invalidated_at',
        'invalidation_reason',
    ];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportTicketMessage::class, 'support_ticket_message_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function isActive(): bool
    {
        return ! $this->consumed_at
            && ! $this->invalidated_at
            && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
