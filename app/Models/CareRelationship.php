<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CareRelationship extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_ENDED = 'ended';

    protected $fillable = [
        'family_user_id',
        'caregiver_user_id',
        'source_care_request_id',
        'last_care_request_id',
        'last_care_booking_id',
        'recipient_name',
        'status',
        'last_visit_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'last_visit_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(User::class, 'family_user_id');
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_user_id');
    }

    public function sourceCareRequest(): BelongsTo
    {
        return $this->belongsTo(CareRequest::class, 'source_care_request_id');
    }

    public function lastCareRequest(): BelongsTo
    {
        return $this->belongsTo(CareRequest::class, 'last_care_request_id');
    }

    public function lastCareBooking(): BelongsTo
    {
        return $this->belongsTo(CareBooking::class, 'last_care_booking_id');
    }

    public function carePlans(): HasMany
    {
        return $this->hasMany(CarePlan::class);
    }
}
