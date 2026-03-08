<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CareRequest extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_OPEN = 'open';
    public const STATUS_FILLED = 'filled';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'family_user_id',
        'title',
        'additional_info',
        'status',
        'budget_min',
        'budget_max',
        'requested_start_at',
        'requested_end_at',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'zip',
        'lat',
        'lng',
    ];

    protected function casts(): array
    {
        return [
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
            'requested_start_at' => 'datetime',
            'requested_end_at' => 'datetime',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(User::class, 'family_user_id');
    }

    public function recipient(): HasOne
    {
        return $this->hasOne(CareRecipient::class);
    }

    public function thirdPartyContact(): HasOne
    {
        return $this->hasOne(CareRequestThirdPartyContact::class);
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(CareTask::class, 'care_request_task')
            ->withPivot('task_note')
            ->withTimestamps();
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CareRequestApplication::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(CareRequestConversation::class);
    }
}
