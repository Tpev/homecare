<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceNotificationDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_key',
        'channel',
        'status',
        'notifiable_type',
        'notifiable_id',
        'dedupe_key',
        'payload',
        'sent_at',
        'open_count',
        'click_count',
        'opened_at',
        'clicked_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
