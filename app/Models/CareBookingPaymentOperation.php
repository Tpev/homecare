<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareBookingPaymentOperation extends Model
{
    use HasFactory;

    public const TYPE_AUTHORIZATION = 'authorization';

    public const TYPE_CHARGE = 'charge';

    public const TYPE_PROCESSING_FEE = 'processing_fee';

    public const TYPE_AUTHORIZATION_RELEASE = 'authorization_release';

    public const TYPE_EARNING = 'earning';

    public const TYPE_TRANSFER = 'transfer';

    public const TYPE_REFUND = 'refund';

    public const TYPE_TRANSFER_REVERSAL = 'transfer_reversal';

    public const TYPE_DISPUTE = 'dispute';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'care_booking_payment_id',
        'care_booking_id',
        'family_account_id',
        'parent_operation_id',
        'financial_reference',
        'type',
        'status',
        'amount_cents',
        'currency',
        'stripe_object_id',
        'stripe_parent_object_id',
        'idempotency_key',
        'metadata',
        'occurred_at',
        'processed_at',
        'failed_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_operation_id');
    }
}
