<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeBaseEntry extends Model
{
    protected $fillable = [
        'stable_id',
        'working_version_id',
        'published_version_id',
        'ever_released',
        'created_by_user_id',
        'deleted_by_user_id',
        'deleted_at',
        'deletion_reason',
    ];

    protected function casts(): array
    {
        return [
            'ever_released' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(KnowledgeBaseVersion::class)->orderByDesc('version_number');
    }

    public function workingVersion(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseVersion::class, 'working_version_id');
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseVersion::class, 'published_version_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function getRouteKeyName(): string
    {
        return 'stable_id';
    }
}
