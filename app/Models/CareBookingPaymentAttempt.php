<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareBookingPaymentAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'care_booking_payment_id',
        'care_booking_id',
        'family_account_id',
        'care_booking_time_correction_id',
        'purpose',
        'revision_key',
        'authorization_key',
        'stripe_payment_intent_id',
        'stripe_payment_method_id',
        'client_secret',
        'amount_cents',
        'currency',
        'status',
        'is_active',
        'last_error',
        'metadata',
        'authorized_at',
        'captured_at',
        'canceled_at',
        'superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
            'authorized_at' => 'datetime',
            'captured_at' => 'datetime',
            'canceled_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(CareBookingPayment::class, 'care_booking_payment_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(CareBooking::class, 'care_booking_id');
    }

    public function correction(): BelongsTo
    {
        return $this->belongsTo(CareBookingTimeCorrection::class, 'care_booking_time_correction_id');
    }
}
