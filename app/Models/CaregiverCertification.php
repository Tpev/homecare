<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaregiverCertification extends Model
{
    use HasFactory;

    public const STATUS_SELF_REPORTED = 'self_reported';

    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED = 'rejected';

    /**
     * Columns that are safe to hydrate into family-facing Livewire components.
     *
     * @var array<int, string>
     */
    public const PUBLIC_TAG_COLUMNS = [
        'id',
        'caregiver_profile_id',
        'caregiver_certification_type_id',
        'custom_name',
        'expires_at',
        'verification_status',
    ];

    /**
     * Additional public fields used only on the full caregiver profile.
     *
     * @var array<int, string>
     */
    public const PUBLIC_DETAIL_COLUMNS = [
        ...self::PUBLIC_TAG_COLUMNS,
        'issuer',
        'issuing_state',
    ];

    protected $fillable = [
        'caregiver_profile_id',
        'caregiver_certification_type_id',
        'custom_name',
        'issuer',
        'issuing_state',
        'expires_at',
        'document_path',
        'document_original_name',
        'document_mime',
        'document_size',
        'verification_status',
        'verified_by_user_id',
        'verified_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'document_size' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    public function caregiverProfile(): BelongsTo
    {
        return $this->belongsTo(CaregiverProfile::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CaregiverCertificationType::class, 'caregiver_certification_type_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function displayName(): string
    {
        return filled($this->custom_name) ? (string) $this->custom_name : (string) $this->type?->label;
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isBefore(today(config('app.timezone'))) ?? false;
    }

    public function isCurrent(): bool
    {
        return in_array($this->verification_status, [
            self::STATUS_VERIFIED,
            self::STATUS_SELF_REPORTED,
            self::STATUS_PENDING,
        ], true) && ! $this->isExpired();
    }

    public function isCurrentlyVerified(): bool
    {
        return $this->verification_status === self::STATUS_VERIFIED && $this->isCurrent();
    }

    public function publicStatusLabel(): string
    {
        if ($this->isExpired()) {
            return 'Expired';
        }

        return $this->isCurrentlyVerified()
            ? 'LoLo verified'
            : 'Reported by caregiver';
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query
            ->whereIn('verification_status', [
                self::STATUS_VERIFIED,
                self::STATUS_SELF_REPORTED,
                self::STATUS_PENDING,
            ])
            ->where(function (Builder $expiration): void {
                $expiration
                    ->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', today(config('app.timezone'))->toDateString());
            });
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('verification_status', self::STATUS_VERIFIED);
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('verification_status', '!=', self::STATUS_REJECTED);
    }
}
