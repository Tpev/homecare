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

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
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
}
