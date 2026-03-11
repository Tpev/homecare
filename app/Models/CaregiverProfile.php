<?php

namespace App\Models;

use App\Support\MarketplacePricing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class CaregiverProfile extends Model
{
    use HasFactory;

    public const INSURANCE_NOT_PROVIDED = 'not_provided';
    public const INSURANCE_NO = 'no_insurance';
    public const INSURANCE_YES = 'insured';

    protected $fillable = [
        'user_id',
        'slug',
        'profile_photo_path',
        'intro_video_path',
        'intro_video_uploaded_at',
        'bio',
        'hourly_rate',
        'pricing_tier',
        'platform_hourly_rate',
        'years_experience',
        'service_area_zip',
        'service_radius_miles',
        'is_accepting_new_clients',
        'insurance_status',
        'insurance_document_path',
        'insurance_verified_at',
        'status',
        'review_submitted_at',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
        'average_rating',
        'reviews_count',
        'identity_verified_at',
        'identity_verification_status',
        'identity_verification_session_id',
        'identity_verification_checked_at',
        'background_check_verified_at',
        'top_caregiver',
        'invite_response_rate',
        'avg_invite_response_minutes',
        'response_metrics_updated_at',
        'reliability_score',
        'completed_bookings_count',
        'cancellation_count',
        'dispute_count',
        'on_time_check_in_count',
    ];

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'platform_hourly_rate' => 'decimal:2',
            'is_accepting_new_clients' => 'boolean',
            'intro_video_uploaded_at' => 'datetime',
            'insurance_verified_at' => 'datetime',
            'review_submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'identity_verified_at' => 'datetime',
            'identity_verification_checked_at' => 'datetime',
            'background_check_verified_at' => 'datetime',
            'top_caregiver' => 'boolean',
            'invite_response_rate' => 'decimal:2',
            'avg_invite_response_minutes' => 'integer',
            'response_metrics_updated_at' => 'datetime',
            'reliability_score' => 'decimal:2',
            'completed_bookings_count' => 'integer',
            'cancellation_count' => 'integer',
            'dispute_count' => 'integer',
            'on_time_check_in_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $profile): void {
            if (! $profile->slug && $profile->relationLoaded('user')) {
                $profile->slug = Str::slug($profile->user->name . '-' . $profile->user_id);
            }

            $pricing = app(MarketplacePricing::class);
            $profile->pricing_tier = $pricing->normalizeTier($profile->pricing_tier);

            if (! $profile->platform_hourly_rate || (float) $profile->platform_hourly_rate <= 0) {
                $profile->platform_hourly_rate = $pricing->rateForTier($profile->pricing_tier);
            }

            if (! $profile->insurance_status) {
                $profile->insurance_status = self::INSURANCE_NOT_PROVIDED;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'caregiver_skill')->withTimestamps();
    }

    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class, 'caregiver_language')->withTimestamps();
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(CaregiverAvailability::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CaregiverProfileVersion::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function moderationLogs(): HasMany
    {
        return $this->hasMany(CaregiverModerationLog::class);
    }

    public function identityVerifications(): HasMany
    {
        return $this->hasMany(CaregiverIdentityVerification::class);
    }

    public function latestIdentityVerification(): HasOne
    {
        return $this->hasOne(CaregiverIdentityVerification::class)->latestOfMany();
    }

    public function hasIdentityVerifiedBadge(): bool
    {
        return (bool) $this->identity_verified_at
            || $this->identity_verification_status === CaregiverIdentityVerification::STATUS_APPROVED;
    }

    public function hasBackgroundCheckBadge(): bool
    {
        return (bool) $this->background_check_verified_at;
    }

    public function hasTopCaregiverBadge(): bool
    {
        if ((bool) $this->top_caregiver) {
            return true;
        }

        return $this->status === 'active'
            && (int) $this->reviews_count >= 8
            && (float) $this->average_rating >= 4.8;
    }

    public function marketplaceReadinessChecks(): array
    {
        return [
            'bio' => filled($this->bio),
            'years_experience' => ! is_null($this->years_experience),
            'service_area_zip' => filled($this->service_area_zip),
            'service_radius_miles' => ! is_null($this->service_radius_miles),
            'tasks' => $this->skills()->exists(),
            'languages' => $this->languages()->exists(),
            'availability' => $this->availabilities()->exists(),
            'identity_verification' => $this->hasIdentityVerifiedBadge(),
        ];
    }

    public function marketplaceCompletenessPercent(): int
    {
        $checks = $this->marketplaceReadinessChecks();

        return (int) round((collect($checks)->filter()->count() / count($checks)) * 100);
    }

    public function isMarketplaceReady(): bool
    {
        return $this->status === 'active'
            && $this->marketplaceCompletenessPercent() >= 100;
    }

    public function insuranceIsComplete(): bool
    {
        if ($this->insurance_status === self::INSURANCE_NO) {
            return true;
        }

        if ($this->insurance_status === self::INSURANCE_YES) {
            return filled($this->insurance_document_path);
        }

        return false;
    }

    public function resolvePlatformHourlyRate(): float
    {
        if ($this->platform_hourly_rate && (float) $this->platform_hourly_rate > 0) {
            return (float) $this->platform_hourly_rate;
        }

        return app(MarketplacePricing::class)->rateForTier($this->pricing_tier);
    }

    public function platformRateLabel(): string
    {
        return app(MarketplacePricing::class)->labelForTier($this->pricing_tier);
    }
}
