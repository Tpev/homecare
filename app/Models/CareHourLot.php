<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CareHourLot extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['total_minutes' => 'integer', 'paid_minutes' => 'integer', 'bonus_minutes' => 'integer', 'processing_fee_cents' => 'integer', 'issued_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CareHourAccount::class, 'care_hour_account_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(CareHourPurchase::class, 'care_hour_purchase_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CareHourAllocation::class);
    }

    public function feeForRange(int $start, int $minutes): ?int
    {
        if ($start < 0 || $minutes < 0 || $start + $minutes > $this->total_minutes) {
            throw new \InvalidArgumentException('Invalid care-hour allocation.');
        }
        if ($this->processing_fee_cents === null) {
            return null;
        }

        return intdiv($this->processing_fee_cents * ($start + $minutes), $this->total_minutes)
            - intdiv($this->processing_fee_cents * $start, $this->total_minutes);
    }
}
