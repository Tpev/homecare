<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class CareRecipientProfileVersion extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'care_recipient_profile_id',
        'version_number',
        'created_by_user_id',
        'candidate_snapshot',
        'assigned_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'candidate_snapshot' => 'array',
            'assigned_snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Care profile versions are immutable.'));
        static::deleting(fn () => throw new LogicException('Care profile versions are immutable.'));
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CareRecipientProfile::class, 'care_recipient_profile_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function views(): HasMany
    {
        return $this->hasMany(CareRecipientProfileView::class);
    }
}
