<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class CareBookingPayment extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_AUTHORIZATION_REQUIRED = 'authorization_required';
    public const STATUS_REAUTH_REQUIRED = 'reauth_required';
    public const STATUS_AUTHORIZED = 'authorized';
    public const STATUS_CAPTURED = 'captured';
    public const STATUS_TRANSFERRED = 'transferred';
    public const STATUS_TRANSFER_FAILED = 'transfer_failed';
    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'care_booking_id',
        'family_user_id',
        'caregiver_user_id',
        'status',
        'currency',
        'stripe_customer_id',
        'stripe_payment_method_id',
        'stripe_payment_intent_id',
        'stripe_overage_payment_intent_id',
        'stripe_transfer_id',
        'stripe_last_refund_id',
        'stripe_last_transfer_reversal_id',
        'amount_authorized_cents',
        'amount_captured_cents',
        'amount_refunded_cents',
        'amount_overage_cents',
        'overage_pending_cents',
        'platform_fee_cents',
        'caregiver_amount_cents',
        'authorization_expires_at',
        'authorized_at',
        'reauthorized_at',
        'captured_at',
        'transferred_at',
        'failed_at',
        'last_error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'authorization_expires_at' => 'datetime',
            'authorized_at' => 'datetime',
            'reauthorized_at' => 'datetime',
            'captured_at' => 'datetime',
            'transferred_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(CareBooking::class, 'care_booking_id');
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(User::class, 'family_user_id');
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_user_id');
    }
}
