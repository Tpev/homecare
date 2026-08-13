<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DataRetentionHold extends Model
{
    use HasUuids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'review_at' => 'datetime',
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('released_at')
            ->where('starts_at', '<=', now())
            ->where(fn (Builder $window): Builder => $window
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()));
    }
}
