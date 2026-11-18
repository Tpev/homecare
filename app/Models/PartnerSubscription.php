<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerSubscription extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price_cents' => 'integer', 'checkout_expires_at' => 'datetime',
            'current_period_start' => 'datetime', 'current_period_end' => 'datetime', 'canceled_at' => 'datetime',
            'cancel_at_period_end' => 'boolean', 'livemode' => 'boolean'];
    }

    public function partnerProfile(): BelongsTo
    {
        return $this->belongsTo(PartnerProfile::class);
    }

    public function credits(): HasMany
    {
        return $this->hasMany(PartnerLeadCredit::class);
    }

    public function blocksNewCheckout(): bool
    {
        if ($this->plan !== 'monthly') {
            return in_array($this->status, ['checkout_creating', 'checkout_pending'], true);
        }

        return ! in_array($this->status, ['canceled', 'incomplete_expired', 'checkout_expired', 'checkout_failed'], true);
    }

    public function blocksPurchase(): bool
    {
        return $this->blocksNewCheckout();
    }
}
