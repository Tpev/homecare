<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSupportControlVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'control_key',
        'version',
        'enabled',
        'configuration',
        'changed_by_user_id',
        'reason',
        'effective_at',
        'replaced_at',
        'retain_until',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'configuration' => 'array',
            'effective_at' => 'datetime',
            'replaced_at' => 'datetime',
            'retain_until' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('replaced_at')->where('effective_at', '<=', now());
    }
}
