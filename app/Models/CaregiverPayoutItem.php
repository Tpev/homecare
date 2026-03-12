<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaregiverPayoutItem extends Model
{
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_PAID = 'paid';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'caregiver_payout_id',
        'caregiver_user_id',
        'care_booking_id',
        'status',
        'currency',
        'amount',
        'included_at',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'included_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(CaregiverPayout::class, 'caregiver_payout_id');
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_user_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(CareBooking::class, 'care_booking_id');
    }
}

