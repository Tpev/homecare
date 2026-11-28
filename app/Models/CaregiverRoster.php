<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaregiverRoster extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['questions' => 'array', 'coverage' => 'array', 'usage' => 'array', 'generation_data' => 'array',
            'generated_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function careRequest(): BelongsTo
    {
        return $this->belongsTo(CareRequest::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CaregiverRosterEntry::class)->orderBy('rank')->orderBy('id');
    }
}
