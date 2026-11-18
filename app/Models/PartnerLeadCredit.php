<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerLeadCredit extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'used_quantity' => 'integer', 'amount_cents' => 'integer',
            'period_start' => 'datetime', 'period_end' => 'datetime', 'paid_at' => 'datetime', 'suspended_at' => 'datetime'];
    }

    public function partnerProfile(): BelongsTo
    {
        return $this->belongsTo(PartnerProfile::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PartnerSubscription::class, 'partner_subscription_id');
    }

    public function scopeAvailable(Builder $query): void
    {
        $query->whereNull('suspended_at')->whereColumn('used_quantity', '<', 'quantity');
    }

    public function remainingQuantity(): int
    {
        return $this->suspended_at ? 0 : max(0, $this->quantity - $this->used_quantity);
    }
}
