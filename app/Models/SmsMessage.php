<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsMessage extends Model
{
    public const DIRECTION_INCOMING = 'incoming';

    public const DIRECTION_OUTGOING = 'outgoing';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_SENDING = 'sending';

    public const STATUS_CONFIRMATION_PENDING = 'confirmation_pending';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_UNDELIVERED = 'undelivered';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'lead_id',
        'request_key',
        'inbound_notified_at',
        'direction',
        'status',
        'from_phone',
        'to_phone',
        'body',
        'twilio_sid',
        'twilio_account_sid',
        'twilio_status',
        'error_code',
        'error_message',
        'num_media',
        'media',
        'raw_payload',
        'sent_by_user_id',
        'received_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'inbound_notified_at' => 'datetime',
            'media' => 'array',
            'raw_payload' => 'array',
            'num_media' => 'integer',
            'received_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function awaitingProviderConfirmation(): bool
    {
        return $this->direction === self::DIRECTION_OUTGOING && ! $this->twilio_sid
            && in_array($this->status, [self::STATUS_SENDING, self::STATUS_CONFIRMATION_PENDING], true);
    }

    public function deliveryLabel(): string
    {
        return $this->awaitingProviderConfirmation() ? 'Awaiting confirmation' : ucfirst($this->status);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
