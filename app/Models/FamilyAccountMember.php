<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyAccountMember extends Model
{
    use HasFactory;

    public const ACCESS_OWNER = 'owner';

    public const ACCESS_MEMBER = 'member';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_LEFT = 'left';

    public const STATUS_REMOVED = 'removed';

    protected $fillable = [
        'family_account_id',
        'user_id',
        'access_level',
        'status',
        'joined_at',
        'ended_at',
        'ended_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function familyAccount(): BelongsTo
    {
        return $this->belongsTo(FamilyAccount::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by_user_id');
    }

    public function isOwner(): bool
    {
        return $this->access_level === self::ACCESS_OWNER;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
