<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CareBookingCorrection extends Model
{
    use HasFactory;

    public const ACTION_REOPEN = 'reopen';

    public const ACTION_COMPLETE_AND_BILL = 'complete_and_bill';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_REQUIRES_ACTION = 'requires_action';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'client_request_id',
        'care_booking_id',
        'support_ticket_id',
        'actor_admin_user_id',
        'action',
        'status',
        'attempt_count',
        'previous_charge_cents',
        'target_charge_cents',
        'payment_delta_cents',
        'caregiver_delta_cents',
        'family_approval_confirmed_at',
        'reason',
        'before_snapshot',
        'requested_changes',
        'preview',
        'after_snapshot',
        'provider_payload',
        'last_error',
        'internal_note_client_id',
        'public_reply_client_id',
        'booking_applied_at',
        'payout_applied_at',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'previous_charge_cents' => 'integer',
            'target_charge_cents' => 'integer',
            'payment_delta_cents' => 'integer',
            'caregiver_delta_cents' => 'integer',
            'family_approval_confirmed_at' => 'datetime',
            'before_snapshot' => 'array',
            'requested_changes' => 'array',
            'preview' => 'array',
            'after_snapshot' => 'array',
            'provider_payload' => 'array',
            'booking_applied_at' => 'datetime',
            'payout_applied_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (CareBookingCorrection $correction): void {
            $immutable = [
                'client_request_id',
                'care_booking_id',
                'support_ticket_id',
                'actor_admin_user_id',
                'action',
                'previous_charge_cents',
                'target_charge_cents',
                'payment_delta_cents',
                'caregiver_delta_cents',
                'family_approval_confirmed_at',
                'reason',
                'before_snapshot',
                'requested_changes',
                'preview',
                'internal_note_client_id',
                'public_reply_client_id',
            ];

            if ($correction->isDirty($immutable)) {
                throw new LogicException('Applied booking correction inputs and audit snapshots are immutable.');
            }
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(CareBooking::class, 'care_booking_id');
    }

    public function supportTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class);
    }

    public function actorAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_admin_user_id');
    }

    public function succeeded(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED;
    }
}
