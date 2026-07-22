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

    public const TYPE_ONE_TIME = 'one_time';

    public const TYPE_RECURRING = 'recurring';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_FILLED = 'filled';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'family_user_id',
        'care_plan_id',
        'is_system_generated',
        'title',
        'additional_info',
        'scope_of_work',
        'time_expectations',
        'home_access_notes',
        'preferred_response_hours',
        'status',
        'first_applicant_at',
        'first_shortlist_at',
        'first_hire_at',
        'request_type',
        'budget_min',
        'budget_max',
        'requested_start_at',
        'requested_end_at',
        'recurring_days',
        'recurring_start_time',
        'recurring_end_time',
        'recurring_starts_on',
        'recurring_ends_on',
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
            'preferred_response_hours' => 'integer',
            'requested_start_at' => 'datetime',
            'requested_end_at' => 'datetime',
            'first_applicant_at' => 'datetime',
            'first_shortlist_at' => 'datetime',
            'first_hire_at' => 'datetime',
            'is_system_generated' => 'boolean',
            'recurring_days' => 'array',
            'recurring_starts_on' => 'date',
            'recurring_ends_on' => 'date',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(User::class, 'family_user_id');
    }

    public function carePlan(): BelongsTo
    {
        return $this->belongsTo(CarePlan::class);
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

    public function booking(): HasOne
    {
        return $this->hasOne(CareBooking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(CareReview::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(CareRequestInvitation::class);
    }

    public function isRecurring(): bool
    {
        return $this->request_type === self::TYPE_RECURRING;
    }
}
