<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyPaymentSetupLink extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['token', 'token_hash', 'stripe_checkout_url'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'stripe_checkout_url' => 'encrypted',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'completed_at' => 'datetime',
            'consented_at' => 'datetime',
            'stripe_checkout_expires_at' => 'datetime',
            'checkout_attempt' => 'integer',
        ];
    }

    public function familyAccount(): BelongsTo
    {
        return $this->belongsTo(FamilyAccount::class);
    }

    public function status(): string
    {
        return match (true) {
            $this->completed_at !== null => 'completed',
            $this->revoked_at !== null => 'revoked',
            $this->expires_at->isPast() => 'expired',
            default => 'active',
        };
    }

    public function isUsable(): bool
    {
        return $this->status() === 'active';
    }
}
