<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarePlanScheduleChange extends Model
{
    use HasFactory;

    public const TYPE_SCHEDULE = 'schedule';

    public const TYPE_EXTRA_VISIT = 'extra_visit';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'care_plan_id',
        'requested_by_user_id',
        'responded_by_user_id',
        'type',
        'status',
        'effective_on',
        'current_schedule',
        'proposed_schedule',
        'note',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_on' => 'date',
            'current_schedule' => 'array',
            'proposed_schedule' => 'array',
            'responded_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(CarePlan::class, 'care_plan_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by_user_id');
    }
}
