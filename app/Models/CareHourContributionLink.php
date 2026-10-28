<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareHourContributionLink extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['revoked_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CareHourAccount::class, 'care_hour_account_id');
    }
}
