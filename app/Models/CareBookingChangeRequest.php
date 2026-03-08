<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareBookingChangeRequest extends Model
{
    use HasFactory;

    public const TYPE_CANCEL = 'cancel';
    public const TYPE_RESCHEDULE = 'reschedule';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'care_booking_id',
        'requester_user_id',
        'type',
        'status',
        'reason',
        'proposed_start_at',
        'proposed_end_at',
        'resolved_at',
        'resolved_by_user_id',
        'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'proposed_start_at' => 'datetime',
            'proposed_end_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(CareBooking::class, 'care_booking_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
