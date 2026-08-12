<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentApiToken extends Model
{
    public const ABILITY_READ = 'content:read';

    public const ABILITY_DRAFT = 'content:draft';

    public const ABILITY_MEDIA = 'content:media';

    public const ABILITY_SUBMIT = 'content:submit';

    public const ABILITY_SCHEDULE = 'content:schedule';

    public const ABILITY_PUBLISH = 'content:publish';

    public const ABILITY_AUDIT = 'content:audit';

    /** @var list<string> */
    public const ABILITIES = [
        self::ABILITY_READ,
        self::ABILITY_DRAFT,
        self::ABILITY_MEDIA,
        self::ABILITY_SUBMIT,
        self::ABILITY_SCHEDULE,
        self::ABILITY_PUBLISH,
        self::ABILITY_AUDIT,
    ];

    protected $fillable = [
        'name',
        'token_prefix',
        'token_hash',
        'actor_user_id',
        'issued_by_user_id',
        'abilities',
        'expires_at',
        'last_used_at',
        'revoked_at',
        'revoked_by_user_id',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->whereHas('actor', fn (Builder $users): Builder => $users
                ->where(fn (Builder $contentTeam): Builder => $contentTeam
                    ->where('role', 'admin')
                    ->orWhereIn('content_role', ['author', 'editor', 'publisher'])));
    }

    public function hasAbility(string $ability): bool
    {
        return in_array($ability, $this->abilities ?? [], true);
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture()
            && $this->actor?->isContentTeamMember() === true;
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    public function idempotencyKeys(): HasMany
    {
        return $this->hasMany(ContentApiIdempotencyKey::class);
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(ContentApiAuditEvent::class);
    }
}
