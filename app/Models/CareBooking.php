<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CareBooking extends Model
{
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DISPUTED = 'disputed';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'care_request_id',
        'care_request_application_id',
        'family_user_id',
        'caregiver_user_id',
        'agreement_snapshot',
        'family_terms_accepted_at',
        'caregiver_terms_accepted_at',
        'status',
        'scheduled_start_at',
        'scheduled_end_at',
        'started_at',
        'check_in_lat',
        'check_in_lng',
        'check_in_note',
        'heartbeat_pinged_at',
        'completed_at',
        'check_out_lat',
        'check_out_lng',
        'check_out_note',
        'timesheet_submitted_at',
        'expected_minutes',
        'worked_minutes',
        'family_confirmed_at',
        'dispute_opened_at',
        'dispute_opened_by_user_id',
        'dispute_reason',
        'dispute_status',
        'no_show_flag',
        'late_cancel_flag',
        'reviewed_at',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancellation_reason',
        'last_rescheduled_at',
        'last_reschedule_reason',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'agreement_snapshot' => 'array',
            'family_terms_accepted_at' => 'datetime',
            'caregiver_terms_accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'check_in_lat' => 'decimal:7',
            'check_in_lng' => 'decimal:7',
            'heartbeat_pinged_at' => 'datetime',
            'completed_at' => 'datetime',
            'check_out_lat' => 'decimal:7',
            'check_out_lng' => 'decimal:7',
            'timesheet_submitted_at' => 'datetime',
            'family_confirmed_at' => 'datetime',
            'dispute_opened_at' => 'datetime',
            'no_show_flag' => 'boolean',
            'late_cancel_flag' => 'boolean',
            'reviewed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_rescheduled_at' => 'datetime',
        ];
    }

    public function careRequest(): BelongsTo
    {
        return $this->belongsTo(CareRequest::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CareRequestApplication::class, 'care_request_application_id');
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(User::class, 'family_user_id');
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function disputeOpenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispute_opened_by_user_id');
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(CareBookingChangeRequest::class)->latest();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(CareReview::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CareBookingEvent::class)->orderByDesc('happened_at');
    }

    public function taskChecks(): HasMany
    {
        return $this->hasMany(CareBookingTaskCheck::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(CareBookingIncident::class)->orderByDesc('reported_at');
    }
}
