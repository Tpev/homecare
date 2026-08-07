<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamilyAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use LogicException;

class CompletedExtraVisitRequest extends Model
{
    use BelongsToFamilyAccount, HasFactory;

    public const STATUS_PENDING_FAMILY = 'pending_family';

    public const STATUS_CHANGES_REQUESTED = 'changes_requested';

    public const STATUS_APPROVED_PROCESSING = 'approved_processing';

    public const STATUS_PAYMENT_ACTION_REQUIRED = 'payment_action_required';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_DISPUTED = 'disputed';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_ESCALATED = 'escalated';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SUPERSEDED = 'superseded';

    public const REASON_FAMILY_REQUESTED = 'family_requested';

    public const REASON_INFORMAL_CHANGE = 'informal_schedule_change';

    public const REASON_FORGOT_TO_REQUEST = 'forgot_to_request';

    public const REASON_OTHER = 'other';

    protected $fillable = [
        'client_request_id', 'care_plan_id', 'family_account_id', 'family_user_id', 'caregiver_user_id',
        'care_booking_id', 'supersedes_id', 'approved_by_user_id', 'support_ticket_id',
        'version', 'status', 'reason_code', 'explanation', 'care_notes', 'timezone',
        'proposed_started_at', 'proposed_completed_at', 'proposed_break_minutes',
        'proposed_worked_minutes', 'financial_preview', 'final_financial_preview',
        'family_response_note', 'processing_attempts', 'last_error', 'submitted_at',
        'changes_requested_at', 'approved_at', 'processing_started_at',
        'payment_action_required_at', 'disputed_at', 'withdrawn_at', 'escalated_at',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'proposed_started_at' => 'datetime',
            'proposed_completed_at' => 'datetime',
            'proposed_break_minutes' => 'integer',
            'proposed_worked_minutes' => 'integer',
            'financial_preview' => 'array',
            'final_financial_preview' => 'array',
            'processing_attempts' => 'integer',
            'submitted_at' => 'datetime',
            'changes_requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'payment_action_required_at' => 'datetime',
            'disputed_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'escalated_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $request): void {
            $immutable = [
                'client_request_id', 'care_plan_id', 'family_user_id', 'caregiver_user_id',
                'version', 'supersedes_id', 'reason_code', 'explanation', 'care_notes',
                'timezone', 'proposed_started_at', 'proposed_completed_at',
                'proposed_break_minutes', 'proposed_worked_minutes', 'financial_preview',
                'submitted_at',
            ];

            if ($request->isDirty($immutable)) {
                throw new LogicException('Submitted completed-extra-visit details are immutable. Create a new version instead.');
            }
        });
    }

    /** @return list<string> */
    public static function reasonCodes(): array
    {
        return [
            self::REASON_FAMILY_REQUESTED,
            self::REASON_INFORMAL_CHANGE,
            self::REASON_FORGOT_TO_REQUEST,
            self::REASON_OTHER,
        ];
    }

    /** @return list<string> */
    public static function unresolvedStatuses(): array
    {
        return [
            self::STATUS_PENDING_FAMILY,
            self::STATUS_CHANGES_REQUESTED,
            self::STATUS_APPROVED_PROCESSING,
            self::STATUS_PAYMENT_ACTION_REQUIRED,
            self::STATUS_DISPUTED,
            self::STATUS_ESCALATED,
            self::STATUS_FAILED,
        ];
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_SUPERSEDED);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(CarePlan::class, 'care_plan_id');
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(User::class, 'family_user_id');
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_user_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(CareBooking::class, 'care_booking_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function supportTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function notificationDeliveries(): MorphMany
    {
        return $this->morphMany(MarketplaceNotificationDelivery::class, 'notifiable');
    }

    public function reasonLabel(): string
    {
        return match ($this->reason_code) {
            self::REASON_FAMILY_REQUESTED => 'The family requested an additional visit',
            self::REASON_INFORMAL_CHANGE => 'The schedule changed informally',
            self::REASON_FORGOT_TO_REQUEST => 'We forgot to request the visit in advance',
            default => 'Something else happened',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING_FAMILY => 'Awaiting family approval',
            self::STATUS_CHANGES_REQUESTED => 'Family requested changes',
            self::STATUS_APPROVED_PROCESSING => 'Approved — processing payment',
            self::STATUS_PAYMENT_ACTION_REQUIRED => 'Family payment action needed',
            self::STATUS_APPLIED => 'Family-approved extra visit',
            self::STATUS_DISPUTED => 'Disputed — LoLo Care review',
            self::STATUS_WITHDRAWN => 'Withdrawn',
            self::STATUS_ESCALATED => 'LoLo Care is reviewing',
            self::STATUS_FAILED => 'Processing paused — LoLo Care review',
            self::STATUS_SUPERSEDED => 'Replaced by an updated report',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function durationLabel(): string
    {
        $minutes = max(0, (int) $this->proposed_worked_minutes);
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return collect([
            $hours > 0 ? $hours.' '.($hours === 1 ? 'hour' : 'hours') : null,
            $remaining > 0 ? $remaining.' minutes' : null,
        ])->filter()->implode(' ') ?: '0 minutes';
    }
}
