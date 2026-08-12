<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentAuthor extends Model
{
    public const SCHEMA_PERSON = 'Person';

    public const SCHEMA_ORGANIZATION = 'Organization';

    public const SCHEMA_TYPES = [
        self::SCHEMA_PERSON => 'Person',
        self::SCHEMA_ORGANIZATION => 'Organization',
    ];

    protected $fillable = [
        'user_id', 'avatar_media_asset_id', 'name', 'schema_type', 'slug', 'headline', 'bio', 'credentials',
        'profile_url', 'same_as', 'is_active',
    ];

    protected $casts = [
        'same_as' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function avatar(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'avatar_media_asset_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'author_id');
    }

    public function reviewedPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'reviewer_id');
    }
}
