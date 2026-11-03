<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AdminRecordedVisit extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['input' => 'array', 'quote' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $visit): void {
            if ($visit->isDirty(['request_token', 'input_hash', 'created_by_user_id', 'care_booking_id', 'input', 'quote', 'payment_mode'])) {
                throw new LogicException('Recorded visit inputs and pricing are immutable.');
            }
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(CareBooking::class, 'care_booking_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
