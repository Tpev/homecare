<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareHourGiftEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        // occurred_at is read explicitly as UTC by the report, not as app-local time.
        return ['available_at' => 'datetime', 'sending_at' => 'datetime', 'sent_at' => 'datetime',
            'amount_cents' => 'integer', 'hours' => 'integer', 'slack_attempts' => 'integer'];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(CareHourPurchase::class, 'care_hour_purchase_id');
    }
}
