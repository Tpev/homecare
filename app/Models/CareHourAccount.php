<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CareHourAccount extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['active' => true, 'currency' => 'usd'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function familyAccount(): BelongsTo
    {
        return $this->belongsTo(FamilyAccount::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CareRecipientProfile::class, 'care_recipient_profile_id');
    }

    public function recipientLinks(): HasMany
    {
        return $this->hasMany(CareHourRecipientLink::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(CareHourLot::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(CareHourPurchase::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CareHourLedgerEntry::class);
    }

    public function balance(): array
    {
        return app(\App\Services\CareHours\CareHourLedgerService::class)->balance($this);
    }

    public static function formatMinutes(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        return $remainder === 0 ? $hours.' h' : ($hours > 0 ? $hours.' h ' : '').$remainder.' min';
    }
}
