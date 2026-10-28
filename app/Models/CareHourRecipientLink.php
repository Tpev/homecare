<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareHourRecipientLink extends Model
{
    protected $guarded = ['id'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CareHourAccount::class, 'care_hour_account_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(CareRecipient::class, 'care_recipient_id');
    }
}
