<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyAccountInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'family_account_id',
        'invited_by_user_id',
        'email_normalized',
        'token_hash',
        'expires_at',
        'accepted_at',
        'accepted_by_user_id',
        'canceled_at',
        'canceled_by_user_id',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function familyAccount(): BelongsTo
    {
        return $this->belongsTo(FamilyAccount::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public function canceledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'canceled_by_user_id');
    }

    public function isUsable(): bool
    {
        return ! $this->accepted_at
            && ! $this->canceled_at
            && $this->expires_at->isFuture();
    }
}
