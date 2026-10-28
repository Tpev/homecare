<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareHourAllocation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['start_minute' => 'integer', 'minutes' => 'integer'];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(CareHourLot::class, 'care_hour_lot_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CareHourAccount::class, 'care_hour_account_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(CareBooking::class, 'care_booking_id');
    }

    public function feeCents(): ?int
    {
        return $this->lot->feeForRange($this->start_minute, $this->minutes);
    }
}
