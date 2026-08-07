<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamilyAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class CareBookingTimeCorrection extends Model
{
    use BelongsToFamilyAccount, HasFactory;

    public const STATUS_PENDING_FAMILY = 'pending_family';

    public const STATUS_CHANGES_REQUESTED = 'changes_requested';

    public const STATUS_APPROVED_PROCESSING = 'approved_processing';

    public const STATUS_PAYMENT_ACTION_REQUIRED = 'payment_action_required';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_APPROVED_ADMIN_REQUIRED = 'approved_admin_required';

    public const STATUS_ESCALATED = 'escalated';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_SUPERSEDED = 'superseded';

    public const REASON_FORGOT_START = 'forgot_start';

    public const REASON_FORGOT_END = 'forgot_end';

    public const REASON_FORGOT_BOTH = 'forgot_both';

    public const REASON_BREAK_WRONG = 'break_wrong';

    public const REASON_APP_OR_GPS = 'app_or_gps';

    public const REASON_OTHER = 'other';

    protected $fillable = [
        'client_request_id',
        'care_booking_id',
        'requester_user_id',
        'family_account_id',
        'family_user_id',
        'version',
        'supersedes_id',
        'status',
        'reason_code',
        'explanation',
        'proposed_started_at',
        'proposed_completed_at',
        'proposed_break_minutes',
        'proposed_worked_minutes',
        'original_snapshot',
        'financial_preview',
        'family_response_note',
        'approved_by_user_id',
        'support_ticket_id',
        'processing_attempts',
        'last_error',
        'submitted_at',
        'changes_requested_at',
        'approved_at',
        'processing_started_at',
        'payment_action_required_at',
        'first_reminded_at',
        'second_reminded_at',
        'escalated_at',
        'withdrawn_at',
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
            'original_snapshot' => 'array',
            'financial_preview' => 'array',
            'processing_attempts' => 'integer',
            'submitted_at' => 'datetime',
            'changes_requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'payment_action_required_at' => 'datetime',
            'first_reminded_at' => 'datetime',
            'second_reminded_at' => 'datetime',
            'escalated_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (CareBookingTimeCorrection $correction): void {
            $immutable = [
                'client_request_id',
                'care_booking_id',
                'requester_user_id',
                'family_user_id',
                'version',
                'supersedes_id',
                'reason_code',
                'explanation',
                'proposed_started_at',
                'proposed_completed_at',
                'proposed_break_minutes',
                'proposed_worked_minutes',
                'original_snapshot',
                'financial_preview',
                'submitted_at',
            ];

            if ($correction->isDirty($immutable)) {
                throw new LogicException('Submitted time-correction inputs and audit snapshots are immutable.');
            }
        });
    }

    /** @return list<string> */
    public static function activeStatuses(): array
    {
        return [
            self::STATUS_PENDING_FAMILY,
            self::STATUS_CHANGES_REQUESTED,
            self::STATUS_APPROVED_PROCESSING,
            self::STATUS_PAYMENT_ACTION_REQUIRED,
            self::STATUS_APPROVED_ADMIN_REQUIRED,
            self::STATUS_ESCALATED,
        ];
    }

    /** @return list<string> */
    public static function reasonCodes(): array
    {
        return [
            self::REASON_FORGOT_START,
            self::REASON_FORGOT_END,
            self::REASON_FORGOT_BOTH,
            self::REASON_BREAK_WRONG,
            self::REASON_APP_OR_GPS,
            self::REASON_OTHER,
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::activeStatuses());
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(CareBooking::class, 'care_booking_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(User::class, 'family_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function supportTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class);
    }

    public function appliedCorrection(): HasOne
    {
        return $this->hasOne(CareBookingCorrection::class, 'time_correction_request_id');
    }

    public function reasonLabel(): string
    {
        return match ($this->reason_code) {
            self::REASON_FORGOT_START => 'I forgot to check in',
            self::REASON_FORGOT_END => 'I forgot to end the visit',
            self::REASON_FORGOT_BOTH => 'I provided care — add my hours',
            self::REASON_BREAK_WRONG => 'The unpaid break is wrong',
            self::REASON_APP_OR_GPS => 'The app or location record is wrong',
            default => 'Something else happened',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING_FAMILY => 'Waiting for family review',
            self::STATUS_CHANGES_REQUESTED => 'Changes requested',
            self::STATUS_APPROVED_PROCESSING => 'Approved — finishing payment',
            self::STATUS_PAYMENT_ACTION_REQUIRED => 'Hours approved — payment confirmation needed',
            self::STATUS_APPLIED => 'Visit time updated',
            self::STATUS_APPROVED_ADMIN_REQUIRED => 'Approved — LoLo Care review needed',
            self::STATUS_ESCALATED => 'LoLo Care is reviewing',
            self::STATUS_WITHDRAWN => 'Withdrawn',
            self::STATUS_SUPERSEDED => 'Updated with a newer version',
            default => 'Time correction',
        };
    }

    public function durationLabel(): string
    {
        $minutes = max(0, (int) $this->proposed_worked_minutes);
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $hours > 0
            ? $hours.' hr'.($hours === 1 ? '' : 's').($remaining ? ' '.$remaining.' min' : '')
            : $remaining.' min';
    }

    public function familyAmountLabel(): string
    {
        return '$'.number_format(max(0, (int) data_get($this->financial_preview, 'target_charge_cents', 0)) / 100, 2);
    }

    public function caregiverAmountLabel(): string
    {
        return '$'.number_format(max(0, (int) data_get($this->financial_preview, 'caregiver_amount_cents', 0)) / 100, 2);
    }
}
