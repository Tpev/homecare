<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareHourGift extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['code_hash', 'encrypted_code', 'claim_quote_key'];

    protected function casts(): array
    {
        return ['welcome_bonus_minutes' => 'integer', 'encrypted_code' => 'encrypted', 'ready_at' => 'datetime', 'claimed_at' => 'datetime'];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(CareHourPurchase::class, 'care_hour_purchase_id');
    }
}
