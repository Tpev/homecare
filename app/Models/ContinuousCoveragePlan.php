<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContinuousCoveragePlan extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ENDED = 'ended';

    public const PATTERN_AROUND_THE_CLOCK = '24_7';

    public const PATTERN_OVERNIGHT = 'overnight';

    public const PATTERN_CUSTOM = 'custom';

    public const CONFIRM_FAMILY = 'family_confirmation';

    public const CONFIRM_APPROVED_BACKUP = 'approved_backup_auto';

    protected $fillable = [
        'family_user_id', 'created_by_user_id', 'status', 'title', 'timezone',
        'starts_on', 'ends_on', 'coverage_pattern', 'shift_length_minutes',
        'weekly_schedule', 'recipient_snapshot', 'address_snapshot',
        'task_snapshot', 'care_notes', 'hourly_rate',
        'replacement_confirmation_mode', 'marketplace_applications_enabled',
        'last_generated_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'shift_length_minutes' => 'integer',
            'weekly_schedule' => 'array',
            'recipient_snapshot' => 'array',
            'address_snapshot' => 'array',
            'task_snapshot' => 'array',
            'hourly_rate' => 'decimal:2',
            'marketplace_applications_enabled' => 'boolean',
            'last_generated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(User::class, 'family_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(ContinuousCoverageShiftTemplate::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(ContinuousCoverageShift::class);
    }

    public function rosterMembers(): HasMany
    {
        return $this->hasMany(ContinuousCoverageRosterMember::class);
    }

    public function laneRequests(): HasMany
    {
        return $this->hasMany(ContinuousCoverageLaneRequest::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ContinuousCoverageEvent::class)->latest('happened_at');
    }

    public function recipientName(): string
    {
        return trim((string) data_get($this->recipient_snapshot, 'full_name')) ?: 'Care recipient';
    }

    public function replacementRequiresFamilyConfirmation(): bool
    {
        return $this->replacement_confirmation_mode !== self::CONFIRM_APPROVED_BACKUP;
    }
}
