<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CareHourPurchase extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['request_key', 'checkout_url', 'success_url', 'cancel_url', 'last_error'];

    protected function casts(): array
    {
        return [
            'quote' => 'array', 'fee_components' => 'array', 'livemode' => 'boolean',
            'paid_minutes' => 'integer', 'bonus_minutes' => 'integer', 'total_minutes' => 'integer',
            'amount_cents' => 'integer', 'processing_fee_cents' => 'integer', 'amount_reversed_cents' => 'integer',
            'terms_accepted_at' => 'datetime', 'paid_at' => 'datetime', 'issued_at' => 'datetime',
            'last_reconciled_at' => 'datetime', 'checkout_requested_at' => 'datetime',
            'correction_requested_at' => 'datetime', 'corrected_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CareHourAccount::class, 'care_hour_account_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function familyAccount(): BelongsTo
    {
        return $this->belongsTo(FamilyAccount::class);
    }

    public function contributionLink(): BelongsTo
    {
        return $this->belongsTo(CareHourContributionLink::class, 'care_hour_contribution_link_id');
    }

    public function gift(): HasOne
    {
        return $this->hasOne(CareHourGift::class, 'care_hour_purchase_id');
    }
}
