<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamilyAccount;
use App\Support\WeeklySchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CareRequest extends Model
{
    use BelongsToFamilyAccount, HasFactory;

    public const TYPE_ONE_TIME = 'one_time';

    public const TYPE_RECURRING = 'recurring';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_FILLED = 'filled';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'family_account_id',
        'family_user_id',
        'created_by_user_id',
        'care_plan_id',
        'is_system_generated',
        'origin',
        'ai_support_ticket_id',
        'ai_support_action_evidence_id',
        'title',
        'additional_info',
        'scope_of_work',
        'time_expectations',
        'home_access_notes',
        'preferred_response_hours',
        'status',
        'is_private',
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
        'recurring_schedule',
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
            'is_private' => 'boolean',
            'requested_start_at' => 'datetime',
            'requested_end_at' => 'datetime',
            'first_applicant_at' => 'datetime',
            'first_shortlist_at' => 'datetime',
            'first_hire_at' => 'datetime',
            'is_system_generated' => 'boolean',
            'recurring_days' => 'array',
            'recurring_schedule' => 'array',
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

    public function scopeVisibleToCaregiver(Builder $query, User|int $caregiver): Builder
    {
        $caregiverId = $caregiver instanceof User ? (int) $caregiver->id : $caregiver;

        return $query->where(function (Builder $visibility) use ($caregiverId): void {
            $visibility
                ->where('is_private', false)
                ->orWhereHas('invitations', function (Builder $invitations) use ($caregiverId): void {
                    $invitations
                        ->where('caregiver_user_id', $caregiverId)
                        ->where(function (Builder $state): void {
                            $state
                                ->where('status', CareRequestInvitation::STATUS_ACCEPTED)
                                ->orWhere(function (Builder $pending): void {
                                    $pending
                                        ->where('status', CareRequestInvitation::STATUS_PENDING)
                                        ->where(function (Builder $expiry): void {
                                            $expiry->whereNull('expires_at')->orWhere('expires_at', '>=', now());
                                        });
                                });
                        });
                })
                ->orWhereHas('applications', fn (Builder $applications) => $applications
                    ->where('caregiver_user_id', $caregiverId));
        });
    }

    public function isRecurring(): bool
    {
        return $this->request_type === self::TYPE_RECURRING;
    }

    /** @return list<array{day:int,start_time:string,end_time:string}> */
    public function recurringScheduleSlots(): array
    {
        $slots = WeeklySchedule::normalize($this->recurring_schedule);
        $legacy = WeeklySchedule::normalize(
            null,
            $this->recurring_days,
            $this->recurring_start_time,
            $this->recurring_end_time,
        );

        if ($slots === [] || $legacy === []) {
            return $slots !== [] ? $slots : $legacy;
        }

        $firstSlot = WeeklySchedule::first($slots);
        $firstLegacy = WeeklySchedule::first($legacy);
        $legacyStillMatches = WeeklySchedule::days($slots) === WeeklySchedule::days($legacy)
            && $firstSlot['start_time'] === $firstLegacy['start_time']
            && $firstSlot['end_time'] === $firstLegacy['end_time'];

        return $legacyStillMatches ? $slots : $legacy;
    }

    public function recurringScheduleLabel(): string
    {
        return WeeklySchedule::label($this->recurringScheduleSlots());
    }
}
