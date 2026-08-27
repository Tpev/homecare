<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamilyAccount;
use App\Support\WeeklySchedule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CarePlan extends Model
{
    use BelongsToFamilyAccount, HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_CAREGIVER = 'pending_caregiver';

    public const STATUS_COUNTERED = 'countered';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAYMENT_ATTENTION = 'payment_attention';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ENDED = 'ended';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_EXPIRED = 'expired';

    public const PAYMENT_UNCHECKED = 'unchecked';

    public const PAYMENT_AUTHORIZED = 'authorized';

    public const PAYMENT_ACTION_REQUIRED = 'action_required';

    protected $fillable = [
        'care_relationship_id',
        'family_account_id',
        'family_user_id',
        'caregiver_user_id',
        'source_care_request_id',
        'source_care_booking_id',
        'next_booking_id',
        'status',
        'title',
        'care_recipient_profile_id',
        'care_recipient_profile_version_id',
        'recipient_snapshot',
        'address_snapshot',
        'task_snapshot',
        'care_notes',
        'schedule_days',
        'schedule_start_time',
        'schedule_end_time',
        'schedule_slots',
        'starts_on',
        'ends_on',
        'timezone',
        'pause_starts_on',
        'resumes_on',
        'schedule_version',
        'counter_schedule_days',
        'counter_schedule_start_time',
        'counter_schedule_end_time',
        'counter_schedule_slots',
        'counter_starts_on',
        'counter_note',
        'hourly_rate',
        'family_message',
        'caregiver_note',
        'offered_at',
        'responded_at',
        'accepted_at',
        'declined_at',
        'activated_at',
        'paused_at',
        'ended_at',
        'expires_at',
        'last_generated_at',
        'payment_status',
        'last_error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'recipient_snapshot' => 'array',
            'address_snapshot' => 'array',
            'task_snapshot' => 'array',
            'schedule_days' => 'array',
            'schedule_slots' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'pause_starts_on' => 'date',
            'resumes_on' => 'date',
            'schedule_version' => 'integer',
            'counter_schedule_days' => 'array',
            'counter_schedule_slots' => 'array',
            'counter_starts_on' => 'date',
            'hourly_rate' => 'decimal:2',
            'offered_at' => 'datetime',
            'responded_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'activated_at' => 'datetime',
            'paused_at' => 'datetime',
            'ended_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_generated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(CareRelationship::class, 'care_relationship_id');
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

    public function sourceCareBooking(): BelongsTo
    {
        return $this->belongsTo(CareBooking::class, 'source_care_booking_id');
    }

    public function nextBooking(): BelongsTo
    {
        return $this->belongsTo(CareBooking::class, 'next_booking_id');
    }

    public function careRecipientProfile(): BelongsTo
    {
        return $this->belongsTo(CareRecipientProfile::class);
    }

    public function careRecipientProfileVersion(): BelongsTo
    {
        return $this->belongsTo(CareRecipientProfileVersion::class);
    }

    public function generatedRequests(): HasMany
    {
        return $this->hasMany(CareRequest::class, 'care_plan_id');
    }

    public function generatedBookings(): HasMany
    {
        return $this->hasMany(CareBooking::class, 'care_plan_id');
    }

    public function scheduleChanges(): HasMany
    {
        return $this->hasMany(CarePlanScheduleChange::class)->latest();
    }

    public function events(): HasMany
    {
        return $this->hasMany(CarePlanEvent::class)->latest();
    }

    public function completedExtraVisitRequests(): HasMany
    {
        return $this->hasMany(CompletedExtraVisitRequest::class)->latest('version');
    }

    public function pendingScheduleChanges(): HasMany
    {
        return $this->hasMany(CarePlanScheduleChange::class)
            ->where('status', CarePlanScheduleChange::STATUS_PENDING)
            ->latest();
    }

    public function recipientName(): string
    {
        return (string) data_get($this->recipient_snapshot, 'full_name', 'Care recipient');
    }

    public function displayTitle(): string
    {
        $title = trim((string) $this->title);

        if ($title === '') {
            return 'Recurring care';
        }

        return str_replace(
            ['Regular Care', 'Regular care', 'regular care', 'Regular-care', 'regular-care'],
            ['Recurring Care', 'Recurring care', 'recurring care', 'Recurring care', 'recurring care'],
            $title,
        );
    }

    public function recipientRelationship(): string
    {
        return (string) data_get($this->recipient_snapshot, 'relationship_to_family', '');
    }

    public function recipientReceivesCareAsRequester(): bool
    {
        return (bool) data_get($this->recipient_snapshot, 'recipient_is_requester', false)
            || strcasecmp($this->recipientRelationship(), 'self') === 0;
    }

    public function recipientContextLabel(): string
    {
        return $this->recipientReceivesCareAsRequester()
            ? 'Requester receives care'
            : 'Family member receives care';
    }

    public function recipientContextDescription(): string
    {
        if ($this->recipientReceivesCareAsRequester()) {
            return 'The person posting is also receiving care.';
        }

        $relationship = trim($this->recipientRelationship());

        return $relationship !== ''
            ? 'A family contact is coordinating care for their '.strtolower($relationship).'.'
            : 'A family contact is coordinating care for someone else.';
    }

    public function isLive(): bool
    {
        return in_array($this->status, [
            self::STATUS_ACTIVE,
            self::STATUS_PAYMENT_ATTENTION,
            self::STATUS_PAUSED,
        ], true);
    }

    /** @return list<array{day:int,start_time:string,end_time:string}> */
    public function weeklyScheduleSlots(bool $useCounter = false): array
    {
        if ($useCounter && $this->counter_schedule_days) {
            return $this->resolveWeeklySchedule(
                $this->counter_schedule_slots,
                $this->counter_schedule_days,
                $this->counter_schedule_start_time,
                $this->counter_schedule_end_time,
            );
        }

        return $this->resolveWeeklySchedule(
            $this->schedule_slots,
            $this->schedule_days,
            $this->schedule_start_time,
            $this->schedule_end_time,
        );
    }

    /** @return list<array{day:int,start_time:string,end_time:string}> */
    private function resolveWeeklySchedule(mixed $slotsValue, mixed $days, mixed $startTime, mixed $endTime): array
    {
        $slots = WeeklySchedule::normalize($slotsValue);
        $legacy = WeeklySchedule::normalize(null, $days, $startTime, $endTime);

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
}
