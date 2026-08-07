<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FamilyAccount extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'owner_user_id',
        'stripe_customer_id',
        'status',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(FamilyAccountMember::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()->where('status', FamilyAccountMember::STATUS_ACTIVE);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(FamilyAccountInvitation::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(FamilyAccountActivityLog::class);
    }
}
