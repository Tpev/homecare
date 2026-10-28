<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CareHourSettlement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['allocation_snapshot' => 'array', 'approved_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(CareBooking::class, 'care_booking_id');
    }

    public function funding(): BelongsTo
    {
        return $this->belongsTo(CareHourBookingFunding::class, 'care_hour_booking_funding_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(CareHourPaymentOperation::class);
    }

    public function getOveragePendingCentsAttribute(): int
    {
        return (int) $this->amount_due_cents;
    }
}
