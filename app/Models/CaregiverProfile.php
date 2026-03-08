<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CaregiverProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'slug',
        'profile_photo_path',
        'bio',
        'hourly_rate',
        'years_experience',
        'service_area_zip',
        'service_radius_miles',
        'is_accepting_new_clients',
        'status',
        'review_submitted_at',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
        'average_rating',
        'reviews_count',
    ];

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'is_accepting_new_clients' => 'boolean',
            'review_submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $profile): void {
            if (! $profile->slug && $profile->relationLoaded('user')) {
                $profile->slug = Str::slug($profile->user->name . '-' . $profile->user_id);
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

}
