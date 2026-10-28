<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareHourLedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Care-hour history is immutable.'));
        static::deleting(fn () => throw new \LogicException('Care-hour history is immutable.'));
    }

    protected function casts(): array
    {
        return ['metadata' => 'array', 'minutes' => 'integer'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CareHourAccount::class, 'care_hour_account_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(CareHourLot::class, 'care_hour_lot_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(CareHourPurchase::class, 'care_hour_purchase_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(CareBooking::class, 'care_booking_id');
    }
}
