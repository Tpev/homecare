<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareRecipientProfileView extends Model
{
    protected $fillable = ['care_recipient_profile_version_id', 'user_id', 'viewed_at'];

    protected function casts(): array
    {
        return ['viewed_at' => 'datetime'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(CareRecipientProfileVersion::class, 'care_recipient_profile_version_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
