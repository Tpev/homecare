<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaregiverPayout extends Model
{
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'caregiver_user_id',
        'period_start_on',
        'period_end_on',
        'scheduled_for',
        'paid_at',
        'status',
        'currency',
        'gross_amount',
        'adjustments_amount',
        'net_amount',
        'provider_reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start_on' => 'date',
            'period_end_on' => 'date',
            'scheduled_for' => 'datetime',
            'paid_at' => 'datetime',
            'gross_amount' => 'decimal:2',
            'adjustments_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
        ];
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caregiver_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CaregiverPayoutItem::class)->latest();
    }
}

