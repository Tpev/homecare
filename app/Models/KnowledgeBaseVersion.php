<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeBaseVersion extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_SUPERSEDED = 'superseded';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_DELETED = 'deleted';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'roles' => 'array',
            'membership_states' => 'array',
            'route_target_ids' => 'array',
            'capability_ids' => 'array',
            'facts_may_state' => 'array',
            'facts_must_not_infer' => 'array',
            'next_actions' => 'array',
            'escalation_conditions' => 'array',
            'retrieval_examples_match' => 'array',
            'retrieval_examples_no_match' => 'array',
            'evaluation_ids' => 'array',
            'validation_results' => 'array',
            'review_by' => 'date',
            'expires_on' => 'date',
            'validated_at' => 'datetime',
            'submitted_for_review_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'paused_at' => 'datetime',
            'superseded_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'retired_at' => 'datetime',
            'full_content_retain_until' => 'datetime',
            'content_deleted_at' => 'datetime',
            'tombstone_retain_until' => 'datetime',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseEntry::class, 'knowledge_base_entry_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(KnowledgeBaseSource::class)->orderBy('position');
    }

    public function dependencies(): HasMany
    {
        return $this->hasMany(KnowledgeBaseVersionDependency::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authored_by_user_id');
    }

    public function scopeRetrievable(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn (Builder $expiry): Builder => $expiry
                ->whereNull('expires_on')
                ->orWhere('expires_on', '>=', today()))
            ->whereHas('entry', fn (Builder $entry): Builder => $entry
                ->whereNull('deleted_at')
                ->whereColumn('knowledge_base_entries.published_version_id', 'knowledge_base_versions.id'));
    }

    public function wasReleased(): bool
    {
        return $this->published_at !== null;
    }
}
