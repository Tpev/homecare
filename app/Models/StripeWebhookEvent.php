<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StripeWebhookEvent extends Model
{
    use HasFactory;

    public const STATUS_RECEIVED = 'received';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'stripe_event_id',
        'type',
        'object_id',
        'connected_account_id',
        'livemode',
        'status',
        'attempts',
        'payload',
        'last_error',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'livemode' => 'boolean',
            'attempts' => 'integer',
            'payload' => 'encrypted:array',
            'processed_at' => 'datetime',
        ];
    }
}
