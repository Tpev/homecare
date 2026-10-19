<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareAssistanceApplication extends Model
{
    public const STATUS_LABELS = [
        'new' => 'New',
        'under_review' => 'Under review',
        'needs_information' => 'More information needed',
        'approved' => 'Approved',
        'declined' => 'Declined',
    ];

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'relationship',
        'city',
        'zip',
        'care_needs',
        'weekly_hours',
        'start_timeframe',
        'hardship_details',
        'consented_at',
        'status',
        'internal_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $attributes = [
        'status' => 'new',
    ];

    protected function casts(): array
    {
        return [
            'care_needs' => 'array',
            'consented_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
