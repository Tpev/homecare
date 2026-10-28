<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CareHourBookingFunding extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['committed_at' => 'datetime', 'planned_minutes' => 'integer', 'reserved_minutes' => 'integer', 'shortfall_minutes' => 'integer', 'shortfall_cents' => 'integer'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(CareBooking::class, 'care_booking_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CareHourAccount::class, 'care_hour_account_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(CareHourPaymentOperation::class);
    }
}
