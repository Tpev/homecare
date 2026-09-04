<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamilyAccount;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareRequestInvitation extends Model
{
    use BelongsToFamilyAccount, HasFactory;

    public const SLA_HOURS = 12;

    public const EXPIRY_HOURS = 72;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'care_request_id',
        'family_account_id',
        'family_user_id',
        'invited_by_user_id',
        'caregiver_user_id',
        'care_request_application_id',
        'status',
        'message',
        'expires_at',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function careRequest(): BelongsTo
    {
        return $this->belongsTo(CareRequest::class);
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(User::class, 'family_user_id');
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_user_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CareRequestApplication::class, 'care_request_application_id');
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->effectiveExpiresAt()?->isPast();
    }

    public function responseDueAt(): ?CarbonInterface
    {
        if (! $this->created_at) {
            return $this->effectiveExpiresAt();
        }

        $request = $this->careRequest;
        $responseHours = $request
            ? max(1, min(self::EXPIRY_HOURS, (int) ($request->preferred_response_hours ?: self::SLA_HOURS)))
            : self::SLA_HOURS;
        $responseDeadline = $this->created_at->copy()->addHours($responseHours);
        $expiresAt = $this->effectiveExpiresAt();

        if ($expiresAt && $expiresAt->lt($responseDeadline)) {
            return $expiresAt;
        }

        return $responseDeadline;
    }

    public function effectiveExpiresAt(): ?CarbonInterface
    {
        $storedExpiry = $this->expires_at?->copy();
        $request = $this->careRequest;

        if (! $request) {
            return $storedExpiry;
        }

        if (! $storedExpiry && ($request->request_type !== CareRequest::TYPE_ONE_TIME || (! $request->requested_end_at && ! $request->requested_start_at))) {
            return null;
        }

        $scheduleSafeExpiry = self::expirationFor($request, $this->created_at ?: now());

        return ! $storedExpiry || $scheduleSafeExpiry->lt($storedExpiry)
            ? $scheduleSafeExpiry
            : $storedExpiry;
    }

    public static function expirationFor(CareRequest $request, ?CarbonInterface $sentAt = null): CarbonInterface
    {
        $sentAt = $sentAt?->copy() ?? now();
        $expiration = $sentAt->copy()->addHours(self::EXPIRY_HOURS);

        $visitEnd = $request->requested_end_at ?: $request->requested_start_at;
        if ($request->request_type !== CareRequest::TYPE_ONE_TIME || ! $visitEnd) {
            return $expiration;
        }

        return $visitEnd->lt($expiration) ? $visitEnd->copy() : $expiration;
    }

    public function isWithinSla(): bool
    {
        if (! $this->responded_at || ! $this->created_at) {
            return false;
        }

        return $this->responded_at->lte($this->responseDueAt());
    }
}
