<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareHourPaymentOperation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'authorization_expires_at' => 'datetime', 'livemode' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $operation) {
            if ($operation->livemode === null) {
                $secret = (string) config('services.stripe.secret');
                $operation->livemode = ! config('services.stripe.bypass') && (str_starts_with($secret, 'sk_live_') || str_starts_with($secret, 'rk_live_'));
            }
        });
    }

    public function assertProviderScope(?bool $livemode, ?string $connectedAccount): void
    {
        if ($connectedAccount || ($livemode !== null && (bool) $this->livemode !== $livemode)) {
            throw new \App\Exceptions\Payments\PaymentException('This provider event does not belong to the recorded platform payment environment.');
        }
    }

    public function funding(): BelongsTo
    {
        return $this->belongsTo(CareHourBookingFunding::class, 'care_hour_booking_funding_id');
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(CareHourSettlement::class, 'care_hour_settlement_id');
    }
}
